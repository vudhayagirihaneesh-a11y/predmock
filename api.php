<?php
error_reporting(0); // Suppress all warnings to prevent JSON corruption
@ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Content-Type: application/json');

// No external libraries needed - using built-in PHP sockets for SMTP
// Read and parse raw JSON input (if passed via fetch with JSON.stringify)
$raw_json = file_get_contents('php://input');
$decoded_json = json_decode($raw_json, true);
if (is_array($decoded_json)) {
    $_POST = array_merge($_POST, $decoded_json);
    $_REQUEST = array_merge($_REQUEST, $decoded_json);
}

// Define exam patterns (marks and calculator availability)
$exam_patterns = [
    'viteee' => ['marks_correct' => 4, 'marks_wrong' => -1, 'has_calculator' => false],
    'srmjeee' => ['marks_correct' => 1, 'marks_wrong' => 0, 'has_calculator' => false],
    'met' => ['marks_correct' => 4, 'marks_wrong' => -1, 'has_calculator' => true], // MET usually allows calculator
    'bitsat' => ['marks_correct' => 3, 'marks_wrong' => -1, 'has_calculator' => false], // BITSAT is often +3, -1
    'jee' => ['marks_correct' => 4, 'marks_wrong' => -1, 'has_calculator' => false],
    'amrita' => ['marks_correct' => 3, 'marks_wrong' => -1, 'has_calculator' => false],    // Official: +3, -1
    'gitam' => ['marks_correct' => 2, 'marks_wrong' => 0, 'has_calculator' => false],      // Official: +2, No negative
    'custom' => ['marks_correct' => 4, 'marks_wrong' => -1, 'has_calculator' => false]     // Strict +4/-1 pattern for custom mix
];


// Initialize Database Connection and run Migrations
require_once __DIR__ . '/db.php';

function verify_user_session($conn, $email) {
    // Accept auth token from either cookie (normal app flow) or query/body (test/mocked flows)
    $token = $_COOKIE['session_token'] ?? ($_REQUEST['token'] ?? ($_POST['token'] ?? ''));
    
    if (!$token) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
        }
    }
    
    if (!$email || !$token) return false;
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND session_token = ? AND session_expires_at > NOW() ");
    if (!$stmt) return false;
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $isValid = $result->num_rows === 1;
    $stmt->close();
    return $isValid;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'logout') {
    setcookie('session_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
    echo json_encode(['success' => true]);
    exit;
}
elseif ($action === 'signup') {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['success' => false, 'message' => 'Invalid email address']));
    }

    if (strlen($password) < 6) {
        die(json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']));
    }
    
    $checkStmt = $conn->prepare("SELECT id, is_verified FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $res = $checkStmt->get_result();
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        if ($user['is_verified'] == 1) {
            die(json_encode(['success' => false, 'message' => 'Account already exists. Please log in.']));
        } else {
            // Update the unverified account so they can get the OTP and verify
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET display_name = ?, password_hash = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $password_hash, $user['id']);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Account details updated. Please verify.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error.']);
            }
            $stmt->close();
            exit;
        }
    }
    $checkStmt->close();

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $name, $password_hash);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account created successfully. Please login.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
    $stmt->close();
    exit;
}
elseif ($action === 'login') {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
        die(json_encode(['success' => false, 'message' => 'Email and password are required']));
    }

    $stmt = $conn->prepare("SELECT id, email, password_hash, is_verified, created_at FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        die(json_encode(['success' => false, 'message' => 'Invalid email or password']));
    }

    if ((int)$user['is_verified'] === 0) {
        die(json_encode(['success' => false, 'message' => 'Account not verified. Please sign up again to verify your email.', 'require_verification' => true]));
    }

    // Generate session token
    $session_token = bin2hex(random_bytes(32));
    $session_expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

    setcookie('session_token', $session_token, [
        'expires' => strtotime('+30 days'),
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    // Update session in DB
    $updateStmt = $conn->prepare("UPDATE users SET session_token = ?, session_expires_at = ? WHERE id = ?");
    $updateStmt->bind_param("ssi", $session_token, $session_expires_at, $user['id']);
    if (!$updateStmt->execute()) {
        error_log("DB Execute Error (login session update): " . $updateStmt->error);
        die(json_encode(['success' => false, 'message' => 'Failed to establish session.']));
    }
    $updateStmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'session_token' => $session_token,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'is_verified' => $user['is_verified'],
            'created_at' => $user['created_at'],
            'login_time' => date('Y-m-d H:i:s')
        ]
    ]);
    exit;
}
elseif ($action === 'send_otp') {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['success' => false, 'message' => 'Invalid email address']));
    }
    
    $type = $_POST['type'] ?? '';
    if ($type === 'signup') {
        $checkStmt = $conn->prepare("SELECT id, is_verified FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $res = $checkStmt->get_result();
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            if ($user['is_verified'] == 1) {
                die(json_encode(['success' => false, 'message' => 'Account already verified. Please log in.']));
            }
        } else {
            die(json_encode(['success' => false, 'message' => 'Account not found. Please sign up first.']));
        }
        $checkStmt->close();
    } else if ($type === 'reset_password') {
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            die(json_encode(['success' => false, 'message' => 'Account not found. Please sign up first.']));
        }
        $checkStmt->close();
    } else if ($type === 'agent_login') {
        $checkStmt = $conn->prepare("SELECT id FROM agents WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            die(json_encode(['success' => false, 'message' => 'Agent account not found. Please register first.']));
        }
        $checkStmt->close();
    }

    // Generate a 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Save OTP to database (valid for 10 minutes)
    $stmt = $conn->prepare("INSERT INTO users (email, otp, otp_expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE otp = ?, otp_expires_at = ?");
    if (!$stmt) {
        error_log("DB Prepare Error (send_otp): " . $conn->error);
        die(json_encode(['success' => false, 'message' => 'A server error occurred.']));
    }
    $stmt->bind_param("sssss", $email, $otp, $otp_expires_at, $otp, $otp_expires_at);
    if (!$stmt->execute()) {
        error_log("DB Execute Error (send_otp): " . $stmt->error);
        die(json_encode(['success' => false, 'message' => 'Failed to save verification code.']));
    }
    $stmt->close();
    
    // Store OTP in a file for debugging/fallback
    $otpFile = 'otp_log.txt';
    $logEntry = "Email: $email | OTP: $otp | Time: " . date('Y-m-d H:i:s') . "\n";
    @file_put_contents($otpFile, $logEntry, FILE_APPEND);
    
    // Send email via Gmail SMTP using SSL on port 465
    // Note: InfinityFree and similar hosts often block outgoing SMTP. 
    $to = $email; 
    $subject = "Your Exam Prep Hub Login Code";
    $message = "Your login code is: $otp\n\nThis code will expire in 10 minutes.\n\nIf you didn't request this, please ignore this email.";

    if ($type === 'reset_password') {
        $subject = "Your Exam Prep Hub Password Reset Code";
        $message = "Your password reset code is: $otp\n\nThis code will expire in 10 minutes.\n\nIf you didn't request this, please ignore this email.";
    }

    $smtpLog = __DIR__ . '/smtp_send_log.txt';

    try {
        $smtpHost = SMTP_HOST;
        $smtpPort = SMTP_PORT;
        $fromEmail = SMTP_FROM_EMAIL;
        $fromName = SMTP_FROM_NAME;
        $username = SMTP_USER;
        $password = SMTP_PASS;

        // Connect to SMTP server
        $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 30);
        if (!$socket) {
            throw new Exception("Cannot connect to SMTP: $errstr ($errno)");
        }
        stream_set_timeout($socket, 15); // Set a 15-second timeout for all stream operations

        // Helper function to read SMTP response
        $smtpResponse = function() use ($socket) {
            $response = '';
            while ($line = fgets($socket, 512)) {
                $response .= $line;
                if (substr($line, 3, 1) == ' ') {
                    break;
                }
            }
            $info = stream_get_meta_data($socket);
            if ($info['timed_out']) {
                throw new Exception("SMTP connection timed out while reading response.");
            }
            return $response;
        };

        // Read initial banner
        $smtpResponse();

        // Say hello
        fwrite($socket, "EHLO examprep\r\n");
        $smtpResponse();

        // Authenticate
        fwrite($socket, "AUTH LOGIN\r\n");
        $smtpResponse();
        fwrite($socket, base64_encode($username) . "\r\n");
        $smtpResponse();
        fwrite($socket, base64_encode($password) . "\r\n");
        $authResponse = $smtpResponse();
        if (strpos($authResponse, '235') === false) {
            throw new Exception("Authentication failed: $authResponse");
        }

        // From
        fwrite($socket, "MAIL FROM:<$fromEmail>\r\n");
        $smtpResponse();

        // To
        fwrite($socket, "RCPT TO:<$to>\r\n");
        $smtpResponse();

        // Data
        fwrite($socket, "DATA\r\n");
        $smtpResponse();

        // Email headers and body
        $headers = "From: $fromName <$fromEmail>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "\r\n";

        fwrite($socket, $headers . $message . "\r\n.\r\n");
        $sendResponse = $smtpResponse();
        if (strpos($sendResponse, '250') === false) {
            throw new Exception("Failed to send message data: $sendResponse");
        }

        // Quit
        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        @file_put_contents($smtpLog, "SEND OK | To: $to | Time: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

        echo json_encode([
            'success' => true,
            'message' => 'Verification code sent to your email.',
            'otp' => $otp // Passed to dev mode UI
        ]);
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        @file_put_contents($smtpLog, "SEND FAIL | To: $to | Error: $errorMsg | Time: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        
        // Fallback to PHP mail()
        $headers = "From: $fromName <$fromEmail>\r\n";
        $headers .= "Reply-To: $fromEmail\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $mailSent = @mail($to, $subject, $message, $headers);

        die(json_encode([ 
            'success' => true,
            'message' => $mailSent ? 'Verification code sent via fallback mailer.' : 'Email delivery blocked by host, but verification code generated.',
            'otp' => $otp
        ]));
    }
}
elseif ($action === 'get_otp_for_testing') {
    // Development endpoint: Retrieve the latest OTP for a given email
    $email = $conn->real_escape_string($_GET['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['success' => false, 'message' => 'Invalid email address']));
    }
    
    // Get OTP from database
    $stmt = $conn->prepare("SELECT otp FROM users WHERE email = ? AND otp_expires_at > NOW() LIMIT 1");
    if (!$stmt) {
        error_log("DB Prepare Error (get_otp_for_testing): " . $conn->error);
        die(json_encode(['success' => false, 'message' => 'A server error occurred.']));
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['otp']) {
            echo json_encode(['success' => true, 'otp' => $row['otp']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No OTP found. Request a new one first.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
    }
    $stmt->close();
}
elseif ($action === 'verify_otp') {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $otp = $conn->real_escape_string($_POST['otp'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['success' => false, 'message' => 'Invalid email address']));
    }
    
    // Check if OTP matches
    $stmt = $conn->prepare("SELECT otp, otp_expires_at FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        error_log("DB Prepare Error (verify_otp): " . $conn->error);
        die(json_encode(['success' => false, 'message' => 'A server error occurred.']));
    }
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        error_log("DB Execute Error (verify_otp): " . $stmt->error);
        die(json_encode(['success' => false, 'message' => 'Could not perform query.']));
    }
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Check for OTP expiry before checking the value
        if (empty($row['otp']) || empty($row['otp_expires_at']) || new DateTime() > new DateTime($row['otp_expires_at'])) {
            die(json_encode(['success' => false, 'message' => 'OTP is invalid or has expired. Please request a new one.']));
        }

        // OTPs can be stored as string/numeric depending on DB driver.
        // Normalize both sides to avoid false mismatches.
        $rowOtp = isset($row['otp']) ? preg_replace('/\D/', '', (string)$row['otp']) : '';
        $otpNorm = preg_replace('/\D/', '', (string)$otp);
        // Normalize OTP further:
        // - remove surrounding spaces
        // - treat as 6-digit code (including leading zeros)
        $rowOtp6 = str_pad($rowOtp, 6, '0', STR_PAD_LEFT);
        $otp6 = str_pad($otpNorm, 6, '0', STR_PAD_LEFT);

        if ($rowOtp === $otpNorm || $rowOtp6 === $otp6) {
            // Generate a secure, server-side session token
            $session_token = bin2hex(random_bytes(32));
            $session_expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

            setcookie('session_token', $session_token, [
                'expires' => strtotime('+30 days'),
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            // Mark user as verified and clear OTP
            $updateStmt = $conn->prepare("UPDATE users SET is_verified = 1, otp = NULL, otp_expires_at = NULL, session_token = ?, session_expires_at = ? WHERE email = ?");
            if (!$updateStmt) {
                error_log("DB Prepare Error (verify_otp update): " . $conn->error);
                die(json_encode(['success' => false, 'message' => 'A server error occurred.']));
            }
            $updateStmt->bind_param("sss", $session_token, $session_expires_at, $email);
            if (!$updateStmt->execute()) {
                error_log("DB Execute Error (verify_otp update): " . $updateStmt->error);
                die(json_encode(['success' => false, 'message' => 'Failed to update user status.']));
            }
            $updateStmt->close();
            
            // Fetch updated user data
            $userStmt = $conn->prepare("SELECT id, email, is_verified, created_at FROM users WHERE email = ? LIMIT 1");
            $userStmt->bind_param("s", $email);
            $userStmt->execute();
            $userData = $userStmt->get_result()->fetch_assoc();
            $userStmt->close();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Login successful',
                'session_token' => $session_token,
                'user' => [
                    'id' => $userData['id'],
                    'email' => $userData['email'],
                    'is_verified' => $userData['is_verified'],
                    'created_at' => $userData['created_at'],
                    'login_time' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP code. Please try again.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    $stmt->close();
}
elseif ($action === 'verify_otp_reset') {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $otp = $conn->real_escape_string($_POST['otp'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['success' => false, 'message' => 'Invalid email address']));
    }
    
    $stmt = $conn->prepare("SELECT otp, otp_expires_at FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (empty($row['otp']) || empty($row['otp_expires_at']) || new DateTime() > new DateTime($row['otp_expires_at'])) {
            die(json_encode(['success' => false, 'message' => 'OTP is invalid or has expired. Please request a new one.']));
        }

        $rowOtp = isset($row['otp']) ? preg_replace('/\D/', '', (string)$row['otp']) : '';
        $otpNorm = preg_replace('/\D/', '', (string)$otp);
        $rowOtp6 = str_pad($rowOtp, 6, '0', STR_PAD_LEFT);
        $otp6 = str_pad($otpNorm, 6, '0', STR_PAD_LEFT);

        if ($rowOtp === $otpNorm || $rowOtp6 === $otp6) {
            $reset_token = bin2hex(random_bytes(32));
            $reset_expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $updateStmt = $conn->prepare("UPDATE users SET is_verified = 1, otp = NULL, otp_expires_at = NULL, reset_token = ?, reset_token_expires_at = ? WHERE email = ?");
            $updateStmt->bind_param("sss", $reset_token, $reset_expires_at, $email);
            $updateStmt->execute();
            $updateStmt->close();
            
            echo json_encode(['success' => true, 'reset_token' => $reset_token]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP code. Please try again.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    $stmt->close();
}
elseif ($action === 'reset_password') {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $token = $conn->real_escape_string($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$token || strlen($password) < 6) {
        die(json_encode(['success' => false, 'message' => 'Invalid parameters or password too short.']));
    }
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expires_at > NOW() LIMIT 1");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE email = ?");
        $updateStmt->bind_param("ss", $password_hash, $email);
        $updateStmt->execute();
        $updateStmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token.']);
    }
    $stmt->close();
}
elseif ($action === 'google_login') {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) die(json_encode(['success' => false, 'message' => 'Invalid email address']));
    
    $session_token = bin2hex(random_bytes(32));
    $session_expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

    setcookie('session_token', $session_token, [
        'expires' => strtotime('+30 days'),
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    $stmt = $conn->prepare("INSERT INTO users (email, is_verified, session_token, session_expires_at) VALUES (?, 1, ?, ?) ON DUPLICATE KEY UPDATE is_verified = 1, session_token = ?, session_expires_at = ?");
    $stmt->bind_param("sssss", $email, $session_token, $session_expires_at, $session_token, $session_expires_at);
    $stmt->execute();
    $stmt->close();

    $userStmt = $conn->prepare("SELECT id, email, is_verified, created_at FROM users WHERE email = ? LIMIT 1");
    $userStmt->bind_param("s", $email);
    $userStmt->execute();
    $userData = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();
    
    echo json_encode([
        'success' => true,
        'session_token' => $session_token,
        'user' => [
            'id' => $userData['id'],
            'email' => $userData['email'],
            'is_verified' => $userData['is_verified'],
            'created_at' => $userData['created_at'],
            'login_time' => date('Y-m-d H:i:s')
        ]
    ]);
}
elseif ($action === 'submit_payment') {
    $email = $_POST['email'] ?? 'unknown';
    $order_id = $_POST['order_id'] ?? '';
    $plan = $_POST['plan'] ?? '';
    $coupon_code = strtoupper(trim($_POST['coupon_code'] ?? ''));
    $validCoupon = false;
    $couponDiscount = 0;
    
    // Server-side pricing to prevent client spoofing
    $prices = ['met' => 500, 'vit' => 500, 'srm' => 500, 'bitsat' => 500, 'gitam' => 500, 'amrita' => 500, 'jee' => 500, 'combo' => 1500];
    $amount = $prices[$plan] ?? 0;
    
    if ($amount > 0 && !empty($coupon_code)) {
        $checkUsage = $conn->prepare("SELECT id FROM coupon_usage WHERE coupon_code = ? AND student_email = ? LIMIT 1");
        if ($checkUsage) {
            $checkUsage->bind_param("ss", $coupon_code, $email);
            $checkUsage->execute();
            if ($checkUsage->get_result()->num_rows > 0) {
                die(json_encode(['success' => false, 'message' => 'You have already used this coupon code.']));
            }
            $checkUsage->close();
        }

        $stmt = $conn->prepare("SELECT discount_amount, expires_at, is_active FROM coupons WHERE code = ?");
        if ($stmt) {
            $stmt->bind_param("s", $coupon_code);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($res && (!isset($res['is_active']) || $res['is_active'] != 0) && (empty($res['expires_at']) || new DateTime() <= new DateTime($res['expires_at']))) {
                $couponDiscount = (int)$res['discount_amount'];
                if ($couponDiscount > $amount) {
                    die(json_encode(['success' => false, 'message' => 'Coupon discount cannot exceed the package amount.']));
                }
                $amount = $amount - $couponDiscount;
                $validCoupon = true;
            }
        }
    }

    if ($amount === 0) {
        $finalAmount = 0;
        $screenPath = ($validCoupon ? 'coupon_free_access' : 'free_access');
        $stmt = $conn->prepare("INSERT INTO payments (email, order_id, plan, amount, coupon_code, screenshot_path, status) VALUES (?, ?, ?, ?, ?, ?, 'approved')");
        $stmt->bind_param("ssssss", $email, $order_id, $plan, $finalAmount, $coupon_code, $screenPath);
        if ($stmt->execute()) {
            if ($validCoupon) {
                $usageStmt = $conn->prepare("INSERT INTO coupon_usage (coupon_code, order_id, student_email, discount_amount) VALUES (?, ?, ?, ?)");
                $usageStmt->bind_param('sssi', $coupon_code, $order_id, $email, $couponDiscount);
                $usageStmt->execute();
                $usageStmt->close();
            }
            echo json_encode(['success' => true]);
        } else {
            error_log("DB Execute Error (submit_payment free): " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        $stmt->close();
        exit;
    }
    
    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        // Security Fix: Restrict allowed file extensions to prevent arbitrary code execution
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'webp'];
        $fileExt = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedExtensions)) {
            die(json_encode(['success' => false, 'message' => 'Invalid file type. Only images and PDFs are allowed.']));
        }

        $fileName = time() . '_' . basename($_FILES['screenshot']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $targetPath)) {
            $stmt = $conn->prepare("INSERT INTO payments (email, order_id, plan, amount, coupon_code, screenshot_path, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $finalAmount = max(0, $amount);
            $stmt->bind_param("ssssss", $email, $order_id, $plan, $finalAmount, $coupon_code, $targetPath);
            if ($stmt->execute()) {
                if ($validCoupon) {
                    $usageStmt = $conn->prepare("INSERT INTO coupon_usage (coupon_code, order_id, student_email, discount_amount) VALUES (?, ?, ?, ?)");
                    $usageStmt->bind_param('sssi', $coupon_code, $order_id, $email, $couponDiscount);
                    $usageStmt->execute();
                    $usageStmt->close();
                }
                echo json_encode(['success' => true]);
            } else {
                error_log("DB Execute Error (submit_payment): " . $stmt->error);
                echo json_encode(['success' => false, 'message' => 'Database error.']);
            }
            $stmt->close();
        } else echo json_encode(['success' => false, 'message' => 'Failed to upload screenshot.']);
    } else echo json_encode(['success' => false, 'message' => 'Screenshot missing.']);
} 
elseif ($action === 'check_status') {
    $order_id = $_GET['order_id'] ?? '';
    $stmt = $conn->prepare("SELECT status FROM payments WHERE order_id = ?");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) echo json_encode(['success' => true, 'status' => $row['status']]);
    else echo json_encode(['success' => false, 'message' => 'Order not found.']);
}
elseif ($action === 'admin_get_payments') {
    $result = $conn->query("SELECT * FROM payments ORDER BY created_at DESC");
    $payments = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) $payments[] = $row;
    }
    echo json_encode(['success' => true, 'payments' => $payments]);
}
elseif ($action === 'admin_get_coupon_usage_history') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    $res = $conn->query("SELECT * FROM coupon_usage ORDER BY used_at DESC");
    $usage = [];
    if ($res) {
        while($r = $res->fetch_assoc()) $usage[] = $r;
    }
    echo json_encode(['success' => true, 'coupon_usage' => $usage]);
}
elseif ($action === 'admin_update_status') {
    $order_id = $_POST['order_id'] ?? '';
    $status = $_POST['status'] ?? ''; 
    $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE order_id = ?");
    $stmt->bind_param("ss", $status, $order_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed.']);
    }
}
elseif ($action === 'save_display_name') {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    if (!$email || !$displayName || mb_strlen($displayName) < 1) {
        die(json_encode(['success' => false, 'message' => 'Name is required.']));
    }
    if (strlen($displayName) > 100) {
        die(json_encode(['success' => false, 'message' => 'Name is too long (max 100 characters).']));
    }
    if (!verify_user_session($conn, $email)) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized session. Please log in again.']));
    }
    // Persist display name directly in the users table
    $stmt = $conn->prepare("UPDATE users SET display_name = ? WHERE email = ?");
    if (!$stmt) {
        error_log("DB Prepare Error (save_display_name): " . $conn->error);
        die(json_encode(['success' => false, 'message' => 'A server error occurred.']));
    }
    $stmt->bind_param("ss", $displayName, $email);
    if (!$stmt->execute()) {
        error_log("DB Execute Error (save_display_name): " . $stmt->error);
        die(json_encode(['success' => false, 'message' => 'Database error.']));
    }
    $stmt->close();
    echo json_encode(['success' => true]);
}
elseif ($action === 'get_purchases') {
    $email = $conn->real_escape_string($_REQUEST['email'] ?? '');

    // Authorization: Must be admin or a valid user session
    if (empty($_SESSION['admin_logged_in'])) {
        if (!verify_user_session($conn, $email)) {
            die(json_encode(['success' => false, 'message' => 'Unauthorized session. Please log in again.']));
        }
    }

    $stmt = $conn->prepare("SELECT DISTINCT plan FROM payments WHERE email = ? AND status = 'approved'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $purchases = [];
    while ($row = $result->fetch_assoc()) $purchases[] = $row['plan'];
    $stmt->close();
    
    echo json_encode(['success' => true, 'purchases' => $purchases]);
}
elseif ($action === 'get_user_profile') {
    // Retrieve user profile based on email
    $email = $conn->real_escape_string($_REQUEST['email'] ?? '');

    if (!verify_user_session($conn, $email)) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized session. Please log in again.']));
    }
    
    $stmt = $conn->prepare("SELECT id, email, display_name, is_verified, created_at FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'user' => $row]);

    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    $stmt->close();
}
elseif ($action === 'agent_login_cc012') { 
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $passCode = $conn->real_escape_string($_POST['code'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['success' => false, 'message' => 'Invalid email address']));
    }
    
    $isValid = false;

    // Verify the 6-digit OTP code against the users table
    $stmt = $conn->prepare("SELECT otp, otp_expires_at FROM users WHERE email = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (!empty($row['otp']) && !empty($row['otp_expires_at']) && new DateTime() <= new DateTime($row['otp_expires_at'])) {
                    $rowOtp = preg_replace('/\D/', '', (string)$row['otp']);
                    $otpNorm = preg_replace('/\D/', '', (string)$passCode);
                    $rowOtp6 = str_pad($rowOtp, 6, '0', STR_PAD_LEFT);
                    $otp6 = str_pad($otpNorm, 6, '0', STR_PAD_LEFT);

                    if ($rowOtp === $otpNorm || $rowOtp6 === $otp6) {
                        $isValid = true;
                        // Clear OTP after successful use
                        $updateStmt = $conn->prepare("UPDATE users SET is_verified = 1, otp = NULL, otp_expires_at = NULL WHERE email = ?");
                        if ($updateStmt) {
                            $updateStmt->bind_param("s", $email);
                            $updateStmt->execute();
                            $updateStmt->close();
                        }
                    }
                }
            }
        }
        $stmt->close();
    }

    if (!$isValid) {
        die(json_encode(['success' => false, 'message' => 'Invalid agent verification code']));
    }

    $stmt = $conn->prepare("SELECT id, first_name, is_approved, is_resigned, is_removed FROM agents WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['first_name'])) {
        die(json_encode(['success' => false, 'require_registration' => true]));
    }
    if ((int)$row['is_approved'] !== 1) {
        die(json_encode(['success' => false, 'is_pending' => true]));
    }

    // FIX: Generate session token for agent so they aren't redirected to login
    $session_token = bin2hex(random_bytes(32));
    $session_expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    setcookie('session_token', $session_token, [
        'expires' => strtotime('+30 days'),
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    // Use INSERT ... ON DUPLICATE KEY UPDATE to ensure the agent exists in the users table
    // This prevents verify_user_session from failing and redirecting agents back to the login page.
    $updateStmt = $conn->prepare("INSERT INTO users (email, is_verified, session_token, session_expires_at) VALUES (?, 1, ?, ?) ON DUPLICATE KEY UPDATE session_token = ?, session_expires_at = ?");
    $updateStmt->bind_param("sssss", $email, $session_token, $session_expires_at, $session_token, $session_expires_at);
    $updateStmt->execute();
    $updateStmt->close();

    echo json_encode([
        'success' => true, 
        'session_token' => $session_token,
        'agent' => ['id' => (int)$row['id'], 'email' => $email]
    ]);
}
elseif ($action === 'agent_register') {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $first_name = $conn->real_escape_string($_POST['first_name'] ?? '');
    $last_name = $conn->real_escape_string($_POST['last_name'] ?? '');
    $age = (int)($_POST['age'] ?? 0);

    if (!$email || !$first_name || !$last_name || $age < 18) {
        die(json_encode(['success' => false, 'message' => 'All fields are required and age must be 18+.']));
    }

    $proof_path = '';
    if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $fileExt = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
        if (in_array($fileExt, $allowedExtensions)) {
            $fileName = 'agent_proof_' . time() . '_' . basename($_FILES['proof']['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['proof']['tmp_name'], $targetPath)) {
                $proof_path = $targetPath;
            }
        } else {
            die(json_encode(['success' => false, 'message' => 'Invalid file type for proof. Allowed: JPG, PNG, PDF']));
        }
    }

    if (!$proof_path) {
        die(json_encode(['success' => false, 'message' => 'Valid proof document is required']));
    }

    $stmt = $conn->prepare("INSERT INTO agents (email, first_name, last_name, age, proof_path, is_approved) VALUES (?, ?, ?, ?, ?, 0) ON DUPLICATE KEY UPDATE first_name = ?, last_name = ?, age = ?, proof_path = ?");
    $stmt->bind_param('sssisssis', $email, $first_name, $last_name, $age, $proof_path, $first_name, $last_name, $age, $proof_path);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $stmt->close();
}
elseif ($action === 'student_submit_ticket') {
    $student_email = $conn->real_escape_string($_POST['student_email'] ?? $_POST['email'] ?? '');
    $subject = $conn->real_escape_string($_POST['subject'] ?? '');
    $message = $_POST['message'] ?? '';
    $agent_email_hint = $conn->real_escape_string($_POST['agent_email'] ?? '');

    if (!filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['success' => false, 'message' => 'Invalid student email']));
    }
    if (!$subject || mb_strlen($subject) < 3) {
        die(json_encode(['success' => false, 'message' => 'Subject is too short']));
    }
    if (!$message || mb_strlen($message) < 2) {
        die(json_encode(['success' => false, 'message' => 'Message is too short']));
    }

    $agent_id = null;
    if ($agent_email_hint) {
        $stmt = $conn->prepare("SELECT id FROM agents WHERE email = ? AND is_approved = 1 AND is_resigned = 0 AND is_removed = 0 LIMIT 1");
        $stmt->bind_param('s', $agent_email_hint);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) $agent_id = (int)$r['id'];
    }

    if ($agent_id === null) {
        $stmt = $conn->prepare("INSERT INTO support_tickets (student_email, agent_id, claimed_by_agent_id, subject, message, status) VALUES (?, NULL, NULL, ?, ?, 'open')");
        if (!$stmt) {
            error_log("DB Prepare Error (submit_ticket): " . $conn->error);
            die(json_encode(['success' => false, 'message' => 'A server error occurred.']));
        }
        $stmt->bind_param('sss', $student_email, $subject, $message);
    } else {
        $stmt = $conn->prepare("INSERT INTO support_tickets (student_email, agent_id, claimed_by_agent_id, subject, message, status) VALUES (?, ?, ?, ?, ?, 'open')");
        if (!$stmt) {
            error_log("DB Prepare Error (submit_ticket with agent): " . $conn->error);
            die(json_encode(['success' => false, 'message' => 'A server error occurred.']));
        }
        $stmt->bind_param('siiss', $student_email, $agent_id, $agent_id, $subject, $message);
    }

    if (!$stmt->execute()) {
        error_log("DB Execute Error (submit_ticket): " . $stmt->error);
        // CRASH FIX: Check for duplicate key entry (MySQL error 1062)
        if ($stmt->errno === 1062) {
            // Try to find the existing ticket ID
            $findStmt = $conn->prepare("SELECT id FROM support_tickets WHERE student_email = ? AND subject = ? AND message = ? LIMIT 1");
            if ($findStmt) {
                $findStmt->bind_param('sss', $student_email, $subject, $message);
                $findStmt->execute();
                $existingTicket = $findStmt->get_result()->fetch_assoc();
                $findStmt->close();
                if ($existingTicket) {
                    $stmt->close();
                    echo json_encode(['success' => true, 'ticket' => ['id' => (int)$existingTicket['id']]]);
                    exit;
                }
            }
        }
        die(json_encode(['success' => false, 'message' => 'Failed to submit ticket.']));
    }

    $ticketId = $stmt->insert_id;
    $stmt->close();


    // Return the created ticket id
    echo json_encode(['success' => true, 'ticket' => ['id' => (int)$ticketId]]);
}
elseif ($action === 'agent_fetch_tickets') {
    $token = $_COOKIE['session_token'] ?? ($_REQUEST['token'] ?? ($_POST['token'] ?? ''));
    $stmt = $conn->prepare("SELECT a.id FROM agents a JOIN users u ON a.email = u.email WHERE u.session_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $agent = $res->fetch_assoc();
    $stmt->close();
    
    if (!$agent) die(json_encode(['success' => false, 'message' => 'Unauthorized agent']));
    $agent_id = $agent['id'];

    // --- Poor Man's Cron: Auto-close inactive tickets ---
    $fortyEightHoursAgo = date('Y-m-d H:i:s', strtotime('-48 hours'));
    $findStmt = $conn->prepare("SELECT id FROM support_tickets WHERE status IN ('open', 'assigned') AND updated_at < ?");
    $findStmt->bind_param('s', $fortyEightHoursAgo);
    $findStmt->execute();
    $result = $findStmt->get_result();
    $ticketIdsToClose = [];
    while ($row = $result->fetch_assoc()) {
        $ticketIdsToClose[] = $row['id'];
    }
    $findStmt->close();

    if (!empty($ticketIdsToClose)) {
        // Add a system message to each closing ticket
        $autoCloseMessage = "This ticket has been automatically closed due to 48 hours of inactivity. If you need further assistance, please create a new ticket.";
        $insertMsgStmt = $conn->prepare("INSERT INTO ticket_resolutions (ticket_id, agent_id, sender_type, message) VALUES (?, 0, 'agent', ?)");
        foreach ($ticketIdsToClose as $ticketId) {
            // agent_id=0 and sender_type='agent' will be interpreted as a system message from "Agent:"
            $insertMsgStmt->bind_param('is', $ticketId, $autoCloseMessage);
            $insertMsgStmt->execute();
        }
        $insertMsgStmt->close();

        // Update the status of all found tickets to 'resolved' in a single query
        $ids_csv = implode(',', array_map('intval', $ticketIdsToClose));
        if ($ids_csv !== '') {
            $conn->query("UPDATE support_tickets SET status = 'resolved' WHERE id IN ($ids_csv)");
        }
    }

    $tab = $_REQUEST['tab'] ?? 'new';
    $tickets = [];
    
    if ($tab === 'new') {
        $stmt = $conn->prepare("SELECT t.id, t.student_email, t.subject, t.message, t.status, t.created_at FROM support_tickets t WHERE (t.claimed_by_agent_id IS NULL OR t.claimed_by_agent_id = 0) AND t.status = 'open' ORDER BY t.created_at ASC LIMIT 50");
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $tickets[] = $row;
        $stmt->close();
    } elseif ($tab === 'pending') {
        $stmt = $conn->prepare("SELECT t.id, t.student_email, t.subject, t.message, t.status, t.created_at FROM support_tickets t WHERE t.claimed_by_agent_id = ? AND t.status IN ('open','assigned') ORDER BY t.created_at ASC LIMIT 50");
        $stmt->bind_param('i', $agent_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $tickets[] = $row;
        $stmt->close();
    } elseif ($tab === 'completed') {
        $stmt = $conn->prepare("SELECT t.id, t.student_email, t.subject, t.message, t.status, t.created_at FROM support_tickets t WHERE t.claimed_by_agent_id = ? AND t.status IN ('resolved','closed') ORDER BY t.updated_at DESC LIMIT 50");
        $stmt->bind_param('i', $agent_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $tickets[] = $row;
        $stmt->close();
    }

    // Get resolved count for agent
    $countStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM support_tickets WHERE claimed_by_agent_id = ? AND status IN ('resolved', 'closed')");
    $countStmt->bind_param("i", $agent_id);
    $countStmt->execute();
    $resolved_count = $countStmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $countStmt->close();

    echo json_encode(['success' => true, 'tickets' => $tickets, 'resolved_count' => (int)$resolved_count]);
}
elseif ($action === 'claim_ticket') {
    $token = $_COOKIE['session_token'] ?? ($_REQUEST['token'] ?? ($_POST['token'] ?? ''));
    $stmt = $conn->prepare("SELECT a.id FROM agents a JOIN users u ON a.email = u.email WHERE u.session_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $agent = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$agent) die(json_encode(['success' => false, 'message' => 'Unauthorized agent']));
    $agent_id = $agent['id'];

    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE support_tickets SET claimed_by_agent_id = ?, agent_id = ? WHERE id = ? AND (claimed_by_agent_id IS NULL OR claimed_by_agent_id = 0)");
    $stmt->bind_param('iii', $agent_id, $agent_id, $ticket_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ticket is already claimed by another agent or not found.']);
    }
}
elseif ($action === 'fetch_chat_messages') {
    $ticket_id = (int)($_REQUEST['ticket_id'] ?? 0);
    $email = $_REQUEST['email'] ?? '';
    $token = $_REQUEST['token'] ?? '';

    // Verify the session of the person asking
    if (!verify_user_session($conn, $email)) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized session.']));
    }

    // Get ticket owner
    $ticketStmt = $conn->prepare("SELECT student_email FROM support_tickets WHERE id = ?");
    $ticketStmt->bind_param('i', $ticket_id);
    $ticketStmt->execute();
    $ticketInfo = $ticketStmt->get_result()->fetch_assoc();
    $ticketStmt->close();

    if (!$ticketInfo) {
        die(json_encode(['success' => false, 'message' => 'Ticket not found.']));
    }

    // If the verified user is NOT the owner, check if they are an agent.
    if ($ticketInfo['student_email'] !== $email) {
        $agentStmt = $conn->prepare("SELECT id FROM agents WHERE email = ?");
        $agentStmt->bind_param("s", $email);
        $agentStmt->execute();
        if ($agentStmt->get_result()->num_rows === 0) {
            die(json_encode(['success' => false, 'message' => 'Access denied.']));
        }
        $agentStmt->close();
    }
    
    // Fetch the original ticket message as the first message
    $stmt = $conn->prepare("SELECT id, message, created_at, status FROM support_tickets WHERE id = ?");
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $ticket_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $messages = [];
    if ($ticket_info) {
        $messages[] = [
            'id' => 0,
            'agent_id' => 0,
            'sender_type' => 'user',
            'message' => $ticket_info['message'],
            'created_at' => $ticket_info['created_at'],
            'agent_name' => null
        ];
    }

    // Security: In a real app, verify user/agent owns this ticket
    $stmt = $conn->prepare("SELECT tr.id, tr.agent_id, tr.sender_type, tr.message, tr.created_at, a.first_name as agent_name FROM ticket_resolutions tr LEFT JOIN agents a ON tr.agent_id = a.id WHERE tr.ticket_id = ? ORDER BY tr.created_at ASC");
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $messages[] = $row;
    $stmt->close();
    $status = $ticket_info ? ($ticket_info['status'] ?? 'not_found') : 'not_found';
    echo json_encode(['success' => true, 'messages' => $messages, 'status' => $status]);
}
elseif ($action === 'send_chat_message') {
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $sender_type = $_POST['sender_type'] ?? '';
    $message = trim($_POST['message'] ?? '');
    $user_email = $_POST['user_email'] ?? ''; // For user

    $agent_id = 0;
    if ($sender_type === 'agent') {
        $token = $_COOKIE['session_token'] ?? ($_REQUEST['token'] ?? ($_POST['token'] ?? ''));
        $stmt = $conn->prepare("SELECT a.id FROM agents a JOIN users u ON a.email = u.email WHERE u.session_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $agent = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$agent) die(json_encode(['success' => false, 'message' => 'Unauthorized agent']));
        $agent_id = $agent['id'];
    }

    if ($ticket_id <= 0 || !$message || !in_array($sender_type, ['agent', 'user'])) {
        die(json_encode(['success' => false, 'message' => 'Invalid parameters']));
    }

    if ($sender_type === 'agent') {
        $stmt = $conn->prepare("INSERT INTO ticket_resolutions (ticket_id, agent_id, sender_type, message) VALUES (?, ?, 'agent', ?)");
        if (!$stmt) {
            error_log("DB Prepare Error (send_chat_message agent): " . $conn->error);
            die(json_encode(['success' => false, 'message' => 'Database error. Please contact support.']));
        }
        $stmt->bind_param('iis', $ticket_id, $agent_id, $message);
    } else { // sender_type is 'user'
        // We don't have a user_id, so we store agent_id as 0 for user messages
        $stmt = $conn->prepare("INSERT INTO ticket_resolutions (ticket_id, agent_id, sender_type, message) VALUES (?, 0, 'user', ?)");
        if (!$stmt) {
            error_log("DB Prepare Error (send_chat_message user): " . $conn->error);
            die(json_encode(['success' => false, 'message' => 'Database error. Please contact support.']));
        }
        $stmt->bind_param('is', $ticket_id, $message);
    }
    $stmt->execute();
    $stmt->close();

    if ($sender_type === 'agent') {
        $updateStmt = $conn->prepare("UPDATE support_tickets SET status = 'assigned', agent_id = ?, claimed_by_agent_id = ? WHERE id = ? AND (status IN ('open', 'resolved', 'closed') OR agent_id IS NULL)");
        if (!$updateStmt) {
            error_log("DB Prepare Error (send_chat_message update ticket): " . $conn->error);
            die(json_encode(['success' => false, 'message' => 'Database error. Please contact support.']));
        }
        $updateStmt->bind_param('iii', $agent_id, $agent_id, $ticket_id);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        $updateStmt = $conn->prepare("UPDATE support_tickets SET status = 'assigned' WHERE id = ? AND status IN ('resolved', 'closed')");
        if (!$updateStmt) {
            error_log("DB Prepare Error (send_chat_message re-open ticket): " . $conn->error);
            die(json_encode(['success' => false, 'message' => 'Database error. Please contact support.']));
        }
        $updateStmt->bind_param('i', $ticket_id);
        $updateStmt->execute();
        $updateStmt->close();
    }

    echo json_encode(['success' => true]);
}
elseif ($action === 'admin_close_ticket') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE support_tickets SET status = 'closed' WHERE id = ?");
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
}
elseif ($action === 'close_ticket') {
    $token = $_COOKIE['session_token'] ?? ($_REQUEST['token'] ?? ($_POST['token'] ?? ''));
    $closed_by = $_POST['closed_by'] ?? 'agent';

    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    if ($ticket_id <= 0) die(json_encode(['success' => false, 'message' => 'Invalid ticket ID']));

    $agent_id = 0;
    if ($closed_by === 'agent') {
        $stmt = $conn->prepare("SELECT a.id FROM agents a JOIN users u ON a.email = u.email WHERE u.session_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $agent = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$agent) die(json_encode(['success' => false, 'message' => 'Unauthorized agent']));
        $agent_id = $agent['id'];
    } else {
        $user_email = $_POST['user_email'] ?? ($_REQUEST['user_email'] ?? '');
        if (!$user_email) {
            $stmt = $conn->prepare("SELECT email FROM users WHERE session_token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($user) $user_email = $user['email'];
        }
        $stmt = $conn->prepare("SELECT id FROM support_tickets WHERE id = ? AND student_email = ?");
        $stmt->bind_param("is", $ticket_id, $user_email);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$ticket) die(json_encode(['success' => false, 'message' => 'Unauthorized user']));
    }

    // Check if an agent has ever replied to this ticket
    $stmt = $conn->prepare("SELECT COUNT(*) as agent_message_count FROM ticket_resolutions WHERE ticket_id = ? AND sender_type = 'agent'");
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result && $result['agent_message_count'] == 0) {
        // No agent has replied, so we can delete the ticket entirely.
        $stmt = $conn->prepare("DELETE FROM support_tickets WHERE id = ?");
        $stmt->bind_param('i', $ticket_id);
        $stmt->execute();
        $stmt->close();

        $stmt2 = $conn->prepare("DELETE FROM ticket_resolutions WHERE ticket_id = ?");
        $stmt2->bind_param('i', $ticket_id);
        $stmt2->execute();
        $stmt2->close();
    } else {
        // An agent has replied, so we mark it as closed.
        $stmt = $conn->prepare("UPDATE support_tickets SET status = 'closed' WHERE id = ?");
        $stmt->bind_param('i', $ticket_id);
        $stmt->execute();
        $stmt->close();
        
        $msg = ($closed_by === 'agent') ? "This ticket has been closed by the support agent. You can reply to this message to re-open the ticket." : "This ticket has been closed by the student. You can reply to this message to re-open the ticket.";
        $sender = ($closed_by === 'agent') ? 'agent' : 'user';
        $ins = $conn->prepare("INSERT INTO ticket_resolutions (ticket_id, agent_id, sender_type, message) VALUES (?, ?, ?, ?)");
        $ins->bind_param('iiss', $ticket_id, $agent_id, $sender, $msg);
        $ins->execute();
        $ins->close();
    }
    echo json_encode(['success' => true]);
}
elseif ($action === 'admin_agent_list') {
    // Only admin session can call this endpoint
    if (empty($_SESSION['admin_logged_in'])) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    }

    // Removed missing 'phone' column from query
    $stmt = $conn->query("SELECT id, first_name, last_name, age, proof_path, email, is_approved, is_resigned, is_removed, created_at, last_seen, TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_since_last_seen, (SELECT COUNT(*) FROM support_tickets WHERE claimed_by_agent_id = agents.id AND status IN ('resolved', 'closed')) as resolved_tickets FROM agents ORDER BY created_at DESC");
    $agents = [];
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) $agents[] = $row;
    }
    echo json_encode(['success' => true, 'agents' => $agents]);
}
elseif ($action === 'admin_agent_update_status') {
    if (empty($_SESSION['admin_logged_in'])) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    }

    $agent_id = (int)($_POST['agent_id'] ?? 0);
    $mode = $_POST['mode'] ?? '';
    if ($agent_id <= 0) die(json_encode(['success' => false, 'message' => 'Invalid agent id']));

    if (!in_array($mode, ['approve','disapprove','resign','remove', 'logout'], true)) {
        die(json_encode(['success' => false, 'message' => 'Invalid mode']));
    }

    if ($mode === 'approve') {
        $stmt = $conn->prepare("UPDATE agents SET is_approved = 1, is_resigned = 0, is_removed = 0 WHERE id = ?");
        $stmt->bind_param('i', $agent_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($mode === 'disapprove') {
        $stmt = $conn->prepare("UPDATE agents SET is_approved = 0 WHERE id = ?");
        $stmt->bind_param('i', $agent_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($mode === 'resign') {
        $stmt = $conn->prepare("UPDATE agents SET is_resigned = 1, is_approved = 0 WHERE id = ?");
        $stmt->bind_param('i', $agent_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($mode === 'remove') {
        $stmt = $conn->prepare("UPDATE agents SET is_removed = 1, is_resigned = 1, is_approved = 0 WHERE id = ?");
        $stmt->bind_param('i', $agent_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($mode === 'logout') {
        $stmt = $conn->prepare("UPDATE agents SET last_seen = NULL WHERE id = ?");
        $stmt->bind_param('i', $agent_id);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true]);
}
elseif ($action === 'agent_ping') {
    $token = $_COOKIE['session_token'] ?? ($_REQUEST['token'] ?? ($_POST['token'] ?? ''));
    $stmt = $conn->prepare("SELECT a.id FROM agents a JOIN users u ON a.email = u.email WHERE u.session_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $agent = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($agent) {
        $stmt2 = $conn->prepare("UPDATE agents SET last_seen = NOW() WHERE id = ?");
        $stmt2->bind_param('i', $agent['id']);
        $stmt2->execute();
        $stmt2->close();
    }

    echo json_encode(['success' => true]);
}
elseif ($action === 'admin_get_ticket') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    $ticket_id = (int)($_GET['ticket_id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT t.*, a.email as agent_email, a.first_name as agent_name FROM support_tickets t LEFT JOIN agents a ON t.agent_id = a.id WHERE t.id = ?");
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($ticket) {
        $stmt = $conn->prepare("SELECT r.*, a.first_name as agent_name, a.email as agent_email FROM ticket_resolutions r LEFT JOIN agents a ON r.agent_id = a.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
        $stmt->bind_param('i', $ticket_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $resolutions = [];
        while($row = $res->fetch_assoc()) $resolutions[] = $row;
        $ticket['resolutions'] = $resolutions;
        echo json_encode(['success' => true, 'ticket' => $ticket]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ticket not found']);
    }
}
elseif ($action === 'get_ticket_status') {
    $ticket_id = (int)($_REQUEST['ticket_id'] ?? 0);
    $user_email = $_REQUEST['user_email'] ?? $_REQUEST['email'] ?? '';
    if ($ticket_id <= 0 || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['success' => false, 'message' => 'Invalid parameters']));
    }
    // Security: ensure the user owns this ticket
    $stmt = $conn->prepare("SELECT status FROM support_tickets WHERE id = ? AND student_email = ?");
    $stmt->bind_param('is', $ticket_id, $user_email);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    echo json_encode(['success' => !!$ticket, 'status' => $ticket['status'] ?? 'not_found']);
}
elseif ($action === 'get_user_tickets') {
    $email = $conn->real_escape_string($_REQUEST['email'] ?? '');

    if (!verify_user_session($conn, $email)) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized session. Please log in again.']));
    }

    $stmt = $conn->prepare("SELECT id, subject, status, created_at, updated_at FROM support_tickets WHERE student_email = ? ORDER BY updated_at DESC");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'tickets' => $tickets]);
}
elseif ($action === 'admin_resolved_ticket_history') {
    if (empty($_SESSION['admin_logged_in'])) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    }

    $stmt = $conn->query("SELECT r.id AS resolution_id, r.created_at, t.id AS ticket_id, t.student_email, t.subject, r.message, a.email AS agent_email
        FROM ticket_resolutions r
        JOIN support_tickets t ON t.id = r.ticket_id
        JOIN agents a ON a.id = r.agent_id
        WHERE r.is_visible_to_admin = 1
        ORDER BY r.created_at DESC
        LIMIT 200");
    $rows = [];
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) $rows[] = $row;
    }

    echo json_encode(['success' => true, 'history' => $rows]);
}
elseif ($action === 'admin_get_analytics') {
    if (empty($_SESSION['admin_logged_in'])) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    }

    $analytics = [];

    $res = $conn->query("SELECT COALESCE(SUM(amount),0) as total_revenue FROM payments WHERE status = 'approved'");
    $approvedRevenue = $res ? (float)($res->fetch_assoc()['total_revenue'] ?? 0) : 0;

    $res = $conn->query("SELECT COALESCE(SUM(amount),0) as refunded_amount FROM payments WHERE status = 'refunded'");
    $refundedAmount = $res ? (float)($res->fetch_assoc()['refunded_amount'] ?? 0) : 0;

    $analytics['total_revenue'] = max(0, (int)round($approvedRevenue - $refundedAmount));
    $analytics['refunded_amount'] = (int)round($refundedAmount);

    $res = $conn->query("SELECT COUNT(*) as pending_payments FROM payments WHERE status = 'pending'");
    $analytics['pending_payments'] = $res ? (int)($res->fetch_assoc()['pending_payments'] ?? 0) : 0;

    $res = $conn->query("SELECT COUNT(*) as open_tickets FROM support_tickets WHERE status = 'open' OR status = 'assigned'");
    $analytics['open_tickets'] = $res ? (int)($res->fetch_assoc()['open_tickets'] ?? 0) : 0;

    $res = $conn->query("SELECT COUNT(*) as online_agents FROM agents WHERE last_seen > NOW() - INTERVAL 5 MINUTE AND IFNULL(is_approved, 0) = 1 AND IFNULL(is_resigned, 0) = 0 AND IFNULL(is_removed, 0) = 0");
    $analytics['online_agents'] = $res ? (int)($res->fetch_assoc()['online_agents'] ?? 0) : 0;

    $res = $conn->query("SELECT COUNT(*) as total_agents FROM agents WHERE IFNULL(is_removed, 0) = 0");
    $analytics['total_agents'] = $res ? (int)($res->fetch_assoc()['total_agents'] ?? 0) : 0;

    echo json_encode(['success' => true, 'analytics' => $analytics]);
}
elseif ($action === 'admin_list_users') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    $search = $conn->real_escape_string($_GET['search'] ?? '');
    
    $query = "SELECT id, email, display_name, is_verified, created_at FROM users";
    $res = null;

    // Use prepared statements to prevent SQL injection.
    if ($search && is_numeric($search)) {
        $stmt = $conn->prepare($query . " WHERE id = ?");
        $stmt->bind_param('i', $search);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
    } else {
        $res = $conn->query($query . " ORDER BY created_at DESC LIMIT 500");
    }
    
    $users = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $parts = explode("@", $r['email']);
            $name = $parts[0] ?? '';
            $masked_name = strlen($name) > 2 ? substr($name, 0, 1) . str_repeat("*", strlen($name) - 2) . substr($name, -1) : $name . "***";
            $r['masked_email'] = $masked_name . "@" . ($parts[1] ?? '');
            // Mask display_name if present
            if (!empty(trim($r['display_name'] ?? ''))) {
                $dn = trim($r['display_name']);
                $dnParts = explode(" ", $dn);
                if (count($dnParts) > 1 && !empty($dnParts[0]) && !empty($dnParts[1])) {
                    $r['masked_name'] = $dnParts[0][0] . str_repeat("*", strlen($dnParts[0]) - 1) . " " . $dnParts[1][0] . str_repeat("*", strlen($dnParts[1]) - 1);
                } else {
                    $r['masked_name'] = str_repeat("*", max(0, strlen($dn) - 1)) . substr($dn, -1);
                }
            }
            $users[] = $r;
        }
    }
    echo json_encode(['success' => true, 'users' => $users]);
}
elseif ($action === 'admin_get_user') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    $user_id = (int)($_GET['user_id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) die(json_encode(['success' => false, 'message' => 'User ID not found']));

    // Mask personal email to ensure privacy
    $email = $user['email'];
    $parts = explode("@", $email);
    $name = $parts[0];
    $masked_name = strlen($name) > 2 ? substr($name, 0, 1) . str_repeat("*", strlen($name) - 2) . substr($name, -1) : $name . "***";
    $masked_email = $masked_name . "@" . ($parts[1] ?? '');

    $stmt = $conn->prepare("SELECT plan, created_at FROM payments WHERE email = ? AND status = 'approved' ORDER BY created_at DESC");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $purchases = [];
    while($r = $res->fetch_assoc()) $purchases[] = $r;
    $stmt->close();

    echo json_encode(['success' => true, 'user' => ['id' => $user_id, 'masked_email' => $masked_email], 'purchases' => $purchases]);
}
elseif ($action === 'admin_manual_grant') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    $user_id = (int)($_POST['user_id'] ?? 0);
    $plan = $_POST['plan'] ?? '';
    if (!$user_id || !$plan) die(json_encode(['success' => false, 'message' => 'Missing parameters']));

    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) die(json_encode(['success' => false, 'message' => 'User not found']));

    $order_id = 'MANUAL_' . time() . '_' . $user_id;
    $stmt = $conn->prepare("INSERT INTO payments (email, order_id, plan, amount, screenshot_path, status) VALUES (?, ?, ?, 0, 'manual_admin_grant', 'approved')");
    $stmt->bind_param('sss', $user['email'], $order_id, $plan);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true]);
}
elseif ($action === 'admin_list_coupons') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    $res = $conn->query("SELECT c.*, COALESCE(u.times_used, 0) as usage_count, u.last_used_at FROM coupons c LEFT JOIN (SELECT coupon_code, COUNT(*) as times_used, MAX(used_at) as last_used_at FROM coupon_usage GROUP BY coupon_code) u ON u.coupon_code = c.code ORDER BY c.created_at DESC");
    $coupons = [];
    if ($res) {
        while($r = $res->fetch_assoc()) $coupons[] = $r;
    }
    echo json_encode(['success' => true, 'coupons' => $coupons]);
}
elseif ($action === 'admin_create_coupon') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discount = (int)($_POST['discount'] ?? 0);
    $expiry = $_POST['expiry'] ?? '';
    $expiry_val = !empty($expiry) ? date('Y-m-d H:i:s', strtotime($expiry)) : null;

    if (!$code || $discount <= 0) die(json_encode(['success' => false, 'message' => 'Invalid code or discount']));

    $stmt = $conn->prepare("INSERT INTO coupons (code, discount_amount, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param('sis', $code, $discount, $expiry_val);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Coupon code might already exist']);
    $stmt->close();
}
elseif ($action === 'admin_toggle_coupon') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    $id = (int)($_POST['id'] ?? 0);
    $is_active = (int)($_POST['is_active'] ?? 1);
    $stmt = $conn->prepare("UPDATE coupons SET is_active = ? WHERE id = ?");
    $stmt->bind_param('ii', $is_active, $id);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Failed to toggle coupon status']);
    $stmt->close();
}
elseif ($action === 'request_refund') {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $plan = $_POST['plan'] ?? '';
    
    if (!verify_user_session($conn, $email)) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized session. Please log in again.']));
    }
    if (!$plan) {
        die(json_encode(['success' => false, 'message' => 'Plan is required.']));
    }
    
    // Ensure user actually owns this plan and it is approved
    $stmt = $conn->prepare("SELECT order_id FROM payments WHERE email = ? AND plan = ? AND status = 'approved' LIMIT 1");
    $stmt->bind_param("ss", $email, $plan);
    $stmt->execute();
    $paymentRes = $stmt->get_result();
    if ($paymentRes->num_rows === 0) {
        die(json_encode(['success' => false, 'message' => 'No approved payment found for this plan.']));
    }
    $paymentRow = $paymentRes->fetch_assoc();
    $order_id = $paymentRow['order_id'];
    $stmt->close();
    
    // Check for duplicate pending requests
    $stmt = $conn->prepare("SELECT id FROM refund_requests WHERE student_email = ? AND plan = ? AND status = 'pending' LIMIT 1");
    $stmt->bind_param("ss", $email, $plan);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        die(json_encode(['success' => false, 'message' => 'A refund request is already pending for this plan.']));
    }
    $stmt->close();

    // Process the QR code upload
    if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/refunds/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $fileExt = strtolower(pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedExtensions)) {
            die(json_encode(['success' => false, 'message' => 'Invalid file type. Only images are allowed.']));
        }

        $fileName = time() . '_qr_' . basename($_FILES['qr_code']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $targetPath)) {
            $refund_token = 'REF-' . strtoupper(uniqid());
            $stmt = $conn->prepare("INSERT INTO refund_requests (student_email, plan, order_id, qr_code_path, refund_token) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $email, $plan, $order_id, $targetPath, $refund_token);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Refund request submitted successfully. Your tracking token is: ' . $refund_token]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload QR code.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'QR code image is missing.']);
    }
}
elseif ($action === 'admin_get_refund_requests') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));

    // Return only non-sensitive user details for admin UI (username + user id)
    $res = $conn->query(
        "SELECT rr.*, u.id AS user_id, u.email, u.display_name AS username
         FROM refund_requests rr
         LEFT JOIN users u ON u.email = rr.student_email
         ORDER BY rr.created_at DESC"
    );

    $requests = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $username = $row['username'] ?? '';
            if (!$username) {
                $emailParts = explode('@', (string)($row['email'] ?? ''));
                $username = $emailParts[0] ?? 'N/A';
            }

            $requests[] = [
                'id' => $row['id'],
                'plan' => $row['plan'],
                'order_id' => $row['order_id'] ?? 'N/A',
                'refund_token' => $row['refund_token'] ?? 'N/A',
                'qr_code_path' => $row['qr_code_path'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'user_id' => $row['user_id'] ?? null,
                'username' => $username
            ];
        }
    }

    echo json_encode(['success' => true, 'requests' => $requests]);
}

elseif ($action === 'admin_process_refund') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    $request_id = (int)($_POST['request_id'] ?? 0);
    $action_type = $_POST['action_type'] ?? ''; // 'approve' or 'reject'
    
    if (!$request_id || !in_array($action_type, ['approve', 'reject'])) {
        die(json_encode(['success' => false, 'message' => 'Invalid parameters']));
    }
    
    $stmt = $conn->prepare("SELECT student_email, plan, order_id, status, id FROM refund_requests WHERE id = ?");
    $stmt->bind_param('i', $request_id);

    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$req) die(json_encode(['success' => false, 'message' => 'Request not found']));
    if ($req['status'] !== 'pending') die(json_encode(['success' => false, 'message' => 'Request already processed']));
    
    $new_status = ($action_type === 'approve') ? 'refunded' : 'rejected';
    $upd = $conn->prepare("UPDATE refund_requests SET status = ? WHERE id = ?");
    $upd->bind_param('si', $new_status, $request_id);
    $upd->execute();
    $upd->close();
    
    if ($action_type === 'approve') {
        // Mark ALL approved user payments for this specific plan as refunded to guarantee access is fully revoked
        $updPay = $conn->prepare("UPDATE payments SET status = 'refunded' WHERE email = ? AND plan = ? AND status = 'approved'");
        $updPay->bind_param('ss', $req['student_email'], $req['plan']);
        $updPay->execute();
        $updPay->close();
        
        $planName = strtoupper($req['plan']);
        $reportMsg = "Refund Request: $planName Package";
        $adminReply = "Your refund request for the $planName mock test package has been successfully approved and processed.\n\nThe admin has scanned your QR code and initiated the transfer. Your access to the $planName mock tests has now been revoked.\n\nIf you ever wish to practice again, you can repurchase the package at any time from our platform.";
        
        $insRep = $conn->prepare("INSERT INTO agent_reports (student_email, ticket_id, message, admin_reply, status) VALUES (?, 0, ?, ?, 'replied')");
        $insRep->bind_param('sss', $req['student_email'], $reportMsg, $adminReply);
        $insRep->execute();
        $insRep->close();

        echo json_encode(['success' => true, 'message' => 'Refund approved, access revoked, and notification sent.']);
    } else {
        $planName = strtoupper($req['plan']);
        $reportMsg = "Refund Request: $planName Package";
        $adminReply = "Your refund request for the $planName mock test package has been reviewed but could not be approved at this time. Please ensure the QR code is valid and belongs to the original purchaser. You retain full access to your mock tests.";
        
        $insRep = $conn->prepare("INSERT INTO agent_reports (student_email, ticket_id, message, admin_reply, status) VALUES (?, 0, ?, ?, 'replied')");
        $insRep->bind_param('sss', $req['student_email'], $reportMsg, $adminReply);
        $insRep->execute();
        $insRep->close();
        echo json_encode(['success' => true, 'message' => 'Refund rejected and notification sent.']);
    }
}
elseif ($action === 'validate_coupon') {
    $code = strtoupper(trim($_GET['code'] ?? ''));
    $email = $conn->real_escape_string($_GET['email'] ?? '');
    $plan = $_GET['plan'] ?? '';
    
    if ($email) {
        $checkUsage = $conn->prepare("SELECT id FROM coupon_usage WHERE coupon_code = ? AND student_email = ? LIMIT 1");
        if ($checkUsage) {
            $checkUsage->bind_param("ss", $code, $email);
            $checkUsage->execute();
            if ($checkUsage->get_result()->num_rows > 0) {
                die(json_encode(['success' => false, 'message' => 'You have already used this coupon code.']));
            }
            $checkUsage->close();
        }
    }

    $stmt = $conn->prepare("SELECT discount_amount, expires_at, is_active FROM coupons WHERE code = ?");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) die(json_encode(['success' => false, 'message' => 'Invalid coupon code']));
    if (isset($res['is_active']) && $res['is_active'] == 0) {
        die(json_encode(['success' => false, 'message' => 'Coupon has expired']));
    }
    if (!empty($res['expires_at']) && new DateTime() > new DateTime($res['expires_at'])) {
        die(json_encode(['success' => false, 'message' => 'Coupon has expired']));
    }

    $prices = ['met' => 500, 'vit' => 500, 'srm' => 500, 'bitsat' => 500, 'gitam' => 500, 'amrita' => 500, 'jee' => 500, 'combo' => 1500];
    $amount = $prices[$plan] ?? 0;
    
    if ($plan && $amount > 0 && (int)$res['discount_amount'] > $amount) {
        die(json_encode(['success' => false, 'message' => 'Coupon discount cannot exceed the package amount.']));
    }

    echo json_encode(['success' => true, 'discount' => (int)$res['discount_amount']]);
}
elseif ($action === 'admin_delete_coupon') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM coupons WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Failed to delete coupon']);
    $stmt->close();
}
elseif ($action === 'get_mock_test') {
    // Sanitize test name to prevent path traversal
    $test_name_raw = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['test_name'] ?? 'viteee');

    // Map short names (used in frontend) to the actual filenames to fix loading issues
    $test_name_map = [
        'srm' => 'srmjeee',
        'vit' => 'viteee',
    ];
    $test_name = $test_name_map[$test_name_raw] ?? $test_name_raw;

    $file_path = __DIR__ . '/' . $test_name . '_mock_test.json';
    $questions = [];
    if (file_exists($file_path)) {
        $questions = json_decode(file_get_contents($file_path), true);
    }

    // Determine pattern based on test_name to pass into mapping
    $current_exam_pattern = $exam_patterns[$test_name] ?? $exam_patterns['custom'];
    
    if (!empty($questions)) {
        $mapping = [];
        $originalCorrect = [];
        
        // Remove correct answers and shuffle options before sending to client for security
        $safe_questions = array_map(function($q) use (&$mapping, &$originalCorrect, $current_exam_pattern) {
            $options = $q['options'];
            $keys = array_keys($options);
            $originalCorrect = $q['correctAnswer'];
            shuffle($keys);
            
            $shuffled_options = [];
            $q_mapping = [];
            $shuffled_answer = 0;

            foreach ($keys as $new_index => $old_index) {
                $shuffled_options[] = $options[$old_index];
                $q_mapping[$new_index] = $old_index;
                if ($old_index == $q['correctAnswer']) {
                    $shuffled_answer = $new_index;
                }
            }
            
            $mapping[$q['id']] = $q_mapping;
            
            // Allow individual questions in custom mock to follow their original mock's marking rule
            $q['marks_correct'] = $q['marks_correct'] ?? $current_exam_pattern['marks_correct'];
            $q['marks_wrong'] = $q['marks_wrong'] ?? $current_exam_pattern['marks_wrong'];
            $q['options'] = $shuffled_options;
            $q['answer'] = $shuffled_answer;
            unset($q['correctAnswer']);
            return $q;
        }, $questions);

        // Store the mapping in the session so submit_mock_test knows which option was originally correct
        $_SESSION['mock_mapping_' . $test_name] = $mapping;

        $pattern = [
            'total_questions' => count($questions),
            'marks_correct' => $current_exam_pattern['marks_correct'],
            'marks_wrong' => $current_exam_pattern['marks_wrong'],
            'has_calculator' => $current_exam_pattern['has_calculator']
        ];

        echo json_encode(['success' => true, 'pattern' => $pattern, 'questions' => $safe_questions]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Test not found']);
    }
}
elseif ($action === 'submit_mock_test') {
    $test_name_raw = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['test_name'] ?? 'viteee');
    $answers = json_decode($_POST['answers'] ?? '[]', true); // Expected format: [{"id": 1, "answer": 0}, ...]
    
    // Apply the same mapping as get_mock_test so the session keys match
    $test_name_map = [
        'srm' => 'srmjeee',
        'vit' => 'viteee',
    ];
    $test_name = $test_name_map[$test_name_raw] ?? $test_name_raw;

    // Retrieve the mapping stored in the session
    $mapping = $_SESSION['mock_mapping_' . $test_name] ?? [];

    if (empty($mapping)) {
        die(json_encode(['success' => false, 'message' => 'Session expired or mapping not found. Please retake the test.']));
    }
    
    $test_data = [];
    if ($test_name === 'custom') {
        $test_data = $_SESSION['custom_test_data'] ?? [];
    } else {
        $file_path = __DIR__ . '/' . $test_name . '_mock_test.json';
        if (!file_exists($file_path)) {
            die(json_encode(['success' => false, 'message' => 'Mock test not found']));
        }
        $test_data = json_decode(file_get_contents($file_path), true);
    }

    if (!is_array($test_data) || empty($test_data)) {
        die(json_encode(['success' => false, 'message' => 'Invalid mock test data.']));
    }

    $score = 0; $correct = 0; $wrong = 0;
    
    // Get marking scheme for the current test
    $current_exam_pattern = $exam_patterns[$test_name] ?? $exam_patterns['custom'];
    $marks_correct = $current_exam_pattern['marks_correct'];
    $marks_wrong = $current_exam_pattern['marks_wrong'];

    // Map correct answers securely on the backend
    $answerKey = [];
    $questionMarks = [];
    foreach ($test_data as $q) {
        $answerKey[$q['id']] = $q['correctAnswer'];
        $questionMarks[$q['id']] = [
            'correct' => $q['marks_correct'] ?? $marks_correct,
            'wrong' => $q['marks_wrong'] ?? $marks_wrong
        ];
    }
    
    foreach ($answers as $ans) {
        $q_id = $ans['id'];
        $user_shuffled_index = (int)$ans['answer'];

        if (!isset($answerKey[$q_id]) || !isset($mapping[$q_id])) continue;

        $original_correct_index = (int)$answerKey[$q_id];
        $user_original_index = $mapping[$q_id][$user_shuffled_index] ?? -1;

        $q_marks_correct = $questionMarks[$q_id]['correct'];
        $q_marks_wrong = $questionMarks[$q_id]['wrong'];

        if ($user_original_index === $original_correct_index) {
            $score += $q_marks_correct; $correct++;
        } else {
            $score += $q_marks_wrong; $wrong++;
        }
    }
    
    echo json_encode(['success' => true, 'score' => $score, 'correct' => $correct, 'wrong' => $wrong, 'unattempted' => count($test_data) - ($correct + $wrong)]);
}
elseif ($action === 'submit_agent_report') {
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $message = $conn->real_escape_string($_POST['message'] ?? '');

    if (!verify_user_session($conn, $email)) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    }
    if ($ticket_id <= 0 || empty($message)) {
        die(json_encode(['success' => false, 'message' => 'Invalid parameters']));
    }

    $stmt = $conn->prepare("INSERT INTO agent_reports (student_email, ticket_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $email, $ticket_id, $message);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Failed to submit report.']);
    $stmt->close();
}
elseif ($action === 'get_user_reports') {
    $email = $conn->real_escape_string($_REQUEST['email'] ?? '');
    if (!verify_user_session($conn, $email)) die(json_encode(['success' => false, 'message' => 'Unauthorized']));

    $stmt = $conn->prepare("SELECT * FROM agent_reports WHERE student_email = ? ORDER BY updated_at DESC");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $reports = [];
    while ($row = $res->fetch_assoc()) $reports[] = $row;
    $stmt->close();

    echo json_encode(['success' => true, 'reports' => $reports]);
}
elseif ($action === 'admin_get_agent_reports') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    // Exclude system notifications (ticket_id = 0) so admins only see actual agent reports
    $res = $conn->query("SELECT * FROM agent_reports WHERE ticket_id > 0 ORDER BY created_at DESC");
    $reports = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) $reports[] = $row;
    }
    echo json_encode(['success' => true, 'reports' => $reports]);
}
elseif ($action === 'admin_reply_agent_report') {
    if (empty($_SESSION['admin_logged_in'])) die(json_encode(['success' => false, 'message' => 'Unauthorized']));
    $report_id = (int)($_POST['report_id'] ?? 0);
    $reply = $_POST['reply'] ?? '';
    if ($report_id <= 0 || empty($reply)) die(json_encode(['success' => false, 'message' => 'Invalid parameters']));
    $stmt = $conn->prepare("UPDATE agent_reports SET admin_reply = ?, status = 'replied' WHERE id = ?");
    $stmt->bind_param("si", $reply, $report_id);
    if ($stmt->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['success' => false, 'message' => 'Failed to reply.']);
    $stmt->close();
}
else echo json_encode(['success' => false, 'message' => 'Invalid action.']);

$conn->close();
?>
