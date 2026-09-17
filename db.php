<?php
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log("DB Connection Failed: " . $conn->connect_error);
    die(json_encode(['success' => false, 'message' => 'Could not connect to the service. Please try again later.']));
}

// Ensure core tables exist first for fresh deployments
$checkUsers = $conn->query("SHOW TABLES LIKE 'users'");
if ($checkUsers && $checkUsers->num_rows === 0) {
    @$conn->query("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        display_name VARCHAR(100) NULL,
        password_hash VARCHAR(255) NULL,
        is_verified BOOLEAN DEFAULT FALSE,
        otp VARCHAR(10) NULL,
        otp_expires_at DATETIME NULL,
        reset_token VARCHAR(64) NULL,
        reset_token_expires_at DATETIME NULL,
        session_token VARCHAR(64) NULL,
        session_expires_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

$checkAgents = $conn->query("SHOW TABLES LIKE 'agents'");
if ($checkAgents && $checkAgents->num_rows === 0) {
    @$conn->query("CREATE TABLE agents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        first_name VARCHAR(255) NULL,
        last_name VARCHAR(255) NULL,
        age INT NULL,
        proof_path VARCHAR(255) NULL,
        is_approved BOOLEAN DEFAULT FALSE,
        is_resigned BOOLEAN DEFAULT FALSE,
        is_removed BOOLEAN DEFAULT FALSE,
        last_seen DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

$checkPayments = $conn->query("SHOW TABLES LIKE 'payments'");
if ($checkPayments && $checkPayments->num_rows === 0) {
    @$conn->query("CREATE TABLE payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        order_id VARCHAR(100) NOT NULL,
        plan VARCHAR(50) NOT NULL,
        amount INT NOT NULL,
        coupon_code VARCHAR(50) NULL,
        screenshot_path VARCHAR(255) NULL,
        status ENUM('pending','approved','rejected','refunded') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Auto-migrate database to prevent query failures if schema wasn't manually updated
$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_expires_at'");
if ($colCheck && $colCheck->num_rows === 0) {
    @$conn->query("ALTER TABLE users ADD COLUMN otp_expires_at DATETIME NULL");
}
$colCheckSession = $conn->query("SHOW COLUMNS FROM users LIKE 'session_token'");
if ($colCheckSession && $colCheckSession->num_rows === 0) {
    @$conn->query("ALTER TABLE users ADD COLUMN session_token VARCHAR(64) NULL, ADD COLUMN session_expires_at DATETIME NULL");
    @$conn->query("CREATE INDEX idx_session_token ON users(session_token)");
}

// Auto-migrate users table to include password reset tokens
$colCheckReset = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
if ($colCheckReset && $colCheckReset->num_rows === 0) {
    @$conn->query("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL, ADD COLUMN reset_token_expires_at DATETIME NULL");
}

// Auto-migrate users table to include password_hash
$colCheckPwd = $conn->query("SHOW COLUMNS FROM users LIKE 'password_hash'");
if ($colCheckPwd && $colCheckPwd->num_rows === 0) {
    @$conn->query("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL");
}

// Auto-migrate users table to include display_name
$colCheckDisplayName = $conn->query("SHOW COLUMNS FROM users LIKE 'display_name'");
if ($colCheckDisplayName && $colCheckDisplayName->num_rows === 0) {
    @$conn->query("ALTER TABLE users ADD COLUMN display_name VARCHAR(100) NULL");
}

// Auto-migrate agents table to support registration data
$colCheckAgentsName = $conn->query("SHOW COLUMNS FROM agents LIKE 'first_name'");
if ($colCheckAgentsName && $colCheckAgentsName->num_rows === 0) {
    @$conn->query("ALTER TABLE agents ADD COLUMN first_name VARCHAR(255) NULL, ADD COLUMN last_name VARCHAR(255) NULL, ADD COLUMN age INT NULL, ADD COLUMN proof_path VARCHAR(255) NULL");
}
$colCheckAgentsResigned = $conn->query("SHOW COLUMNS FROM agents LIKE 'is_resigned'");
if ($colCheckAgentsResigned && $colCheckAgentsResigned->num_rows === 0) {
    @$conn->query("ALTER TABLE agents ADD COLUMN is_resigned BOOLEAN DEFAULT FALSE");
}
$colCheckAgentsRemoved = $conn->query("SHOW COLUMNS FROM agents LIKE 'is_removed'");
if ($colCheckAgentsRemoved && $colCheckAgentsRemoved->num_rows === 0) {
    @$conn->query("ALTER TABLE agents ADD COLUMN is_removed BOOLEAN DEFAULT FALSE");
}
$colCheckAgentsLastSeen = $conn->query("SHOW COLUMNS FROM agents LIKE 'last_seen'");
if ($colCheckAgentsLastSeen && $colCheckAgentsLastSeen->num_rows === 0) {
    @$conn->query("ALTER TABLE agents ADD COLUMN last_seen DATETIME NULL");
}

// Ensure ticket_resolutions has agent_id
$colCheckAgentId = $conn->query("SHOW COLUMNS FROM ticket_resolutions LIKE 'agent_id'");
if ($colCheckAgentId && $colCheckAgentId->num_rows === 0) {
    @$conn->query("ALTER TABLE ticket_resolutions ADD COLUMN agent_id INT NOT NULL DEFAULT 0 AFTER ticket_id");
}

// Ensure ticket_resolutions has sender_type
$colCheckSenderType = $conn->query("SHOW COLUMNS FROM ticket_resolutions LIKE 'sender_type'");
if ($colCheckSenderType && $colCheckSenderType->num_rows === 0) {
    @$conn->query("ALTER TABLE ticket_resolutions ADD COLUMN sender_type ENUM('agent', 'user') NOT NULL DEFAULT 'agent'");
}

// Ensure ticket_resolutions has message (rename from resolution_message if needed)
$colCheckMessage = $conn->query("SHOW COLUMNS FROM ticket_resolutions LIKE 'message'");
if ($colCheckMessage && $colCheckMessage->num_rows === 0) {
    // Check if resolution_message exists to rename it
    $colCheckResMsg = $conn->query("SHOW COLUMNS FROM ticket_resolutions LIKE 'resolution_message'");
    if ($colCheckResMsg && $colCheckResMsg->num_rows > 0) {
        @$conn->query("ALTER TABLE ticket_resolutions CHANGE COLUMN resolution_message message TEXT NOT NULL");
    } else {
        @$conn->query("ALTER TABLE ticket_resolutions ADD COLUMN message TEXT NOT NULL");
    }
}

// Ensure support_tickets has claimed_by_agent_id for collision detection
$colCheckClaimed = $conn->query("SHOW COLUMNS FROM support_tickets LIKE 'claimed_by_agent_id'");
if ($colCheckClaimed && $colCheckClaimed->num_rows === 0) {
    @$conn->query("ALTER TABLE support_tickets ADD COLUMN claimed_by_agent_id INT NULL AFTER agent_id");
}

// Ensure support_tickets and ticket_resolutions tables exist with correct schema
$checkTickets = $conn->query("SHOW TABLES LIKE 'support_tickets'");
if ($checkTickets && $checkTickets->num_rows === 0) {
    @$conn->query("CREATE TABLE support_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_email VARCHAR(255) NOT NULL,
        agent_id INT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('open','assigned','resolved','closed') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_subject_msg (student_email, subject, message(255))
    )");
}

// Auto-migrate ticket status to include 'closed'
@$conn->query("ALTER TABLE support_tickets MODIFY COLUMN status ENUM('open','assigned','resolved','closed') DEFAULT 'open'");

$checkResolutions = $conn->query("SHOW TABLES LIKE 'ticket_resolutions'");
if ($checkResolutions && $checkResolutions->num_rows === 0) {
    @$conn->query("CREATE TABLE ticket_resolutions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        agent_id INT NOT NULL,
        sender_type ENUM('agent', 'user') NOT NULL DEFAULT 'agent',
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_visible_to_admin BOOLEAN DEFAULT TRUE
    )");
}

// Auto-migrate coupons table
$checkCoupons = $conn->query("SHOW TABLES LIKE 'coupons'");
if ($checkCoupons && $checkCoupons->num_rows === 0) {
    @$conn->query("CREATE TABLE coupons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        discount_amount INT NOT NULL,
        expires_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}
$checkCouponUsage = $conn->query("SHOW TABLES LIKE 'coupon_usage'");
if ($checkCouponUsage && $checkCouponUsage->num_rows === 0) {
    @$conn->query("CREATE TABLE coupon_usage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coupon_code VARCHAR(50) NOT NULL,
        order_id VARCHAR(100) NOT NULL,
        student_email VARCHAR(255) NOT NULL,
        discount_amount INT NOT NULL,
        used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Auto-migrate refund_requests table
$checkRefunds = $conn->query("SHOW TABLES LIKE 'refund_requests'");
if ($checkRefunds && $checkRefunds->num_rows === 0) {
    @$conn->query("CREATE TABLE refund_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_email VARCHAR(255) NOT NULL,
        plan VARCHAR(50) NOT NULL,
        qr_code_path VARCHAR(255) NOT NULL,
        refund_token VARCHAR(50) NULL,
        status ENUM('pending', 'refunded', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

// Auto-migrate refund_requests table to include order_id
$colCheckRefundOrder = $conn->query("SHOW COLUMNS FROM refund_requests LIKE 'order_id'");
if ($colCheckRefundOrder && $colCheckRefundOrder->num_rows === 0) {
    @$conn->query("ALTER TABLE refund_requests ADD COLUMN order_id VARCHAR(100) NULL AFTER plan");
}

// Auto-migrate coupons table to include is_active
$colCheckCouponsActive = $conn->query("SHOW COLUMNS FROM coupons LIKE 'is_active'");
if ($colCheckCouponsActive && $colCheckCouponsActive->num_rows === 0) {
    @$conn->query("ALTER TABLE coupons ADD COLUMN is_active BOOLEAN DEFAULT TRUE");
}

// Auto-migrate payments table to include coupon_code
$colCheckPaymentsCoupon = $conn->query("SHOW COLUMNS FROM payments LIKE 'coupon_code'");
if ($colCheckPaymentsCoupon && $colCheckPaymentsCoupon->num_rows === 0) {
    @$conn->query("ALTER TABLE payments ADD COLUMN coupon_code VARCHAR(50) NULL AFTER amount");
}

// Auto-migrate agent_reports table
$checkAgentReports = $conn->query("SHOW TABLES LIKE 'agent_reports'");
if ($checkAgentReports && $checkAgentReports->num_rows === 0) {
    @$conn->query("CREATE TABLE agent_reports (id INT AUTO_INCREMENT PRIMARY KEY, student_email VARCHAR(255), ticket_id INT, message TEXT, admin_reply TEXT, status VARCHAR(50) DEFAULT 'open', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
}
