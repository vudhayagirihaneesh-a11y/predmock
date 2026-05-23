CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(100) NULL,
    otp VARCHAR(10),
    otp_expires_at DATETIME NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    order_id VARCHAR(50) NOT NULL UNIQUE,
    plan VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    coupon_code VARCHAR(50) NULL DEFAULT NULL,
    screenshot_path VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Agent portal (cc012) + Student support tickets
CREATE TABLE agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_approved BOOLEAN DEFAULT FALSE,
    is_resigned BOOLEAN DEFAULT FALSE,
    is_removed BOOLEAN DEFAULT FALSE
);

CREATE TABLE support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_email VARCHAR(255) NOT NULL,
    agent_id INT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open','assigned','resolved','closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_student_subject (student_email, subject)
);

CREATE TABLE ticket_resolutions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    agent_id INT NOT NULL,
    sender_type ENUM('agent', 'user') NOT NULL DEFAULT 'agent',
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_visible_to_admin BOOLEAN DEFAULT TRUE
);

CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_amount INT NOT NULL,
    expires_at DATETIME NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE coupon_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_code VARCHAR(50) NOT NULL,
    order_id VARCHAR(50) NOT NULL,
    student_email VARCHAR(255) NOT NULL,
    discount_amount INT NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_code) REFERENCES coupons(code)
);

CREATE TABLE refund_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_email VARCHAR(255) NOT NULL,
    plan VARCHAR(50) NOT NULL,
    qr_code_path VARCHAR(255) NOT NULL,
    refund_token VARCHAR(50) NULL,
    status ENUM('pending', 'refunded', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
