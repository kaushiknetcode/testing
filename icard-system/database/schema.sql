-- Eastern Railway I-Card System Database Schema
-- Created: 2025-05-28
-- Clean version without foreign keys for Hostinger shared hosting
-- 8 departments and 13 COs

-- Drop existing tables if they exist (for clean setup)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS departments CASCADE;
DROP TABLE IF EXISTS employees CASCADE;
DROP TABLE IF EXISTS system_users CASCADE;
DROP TABLE IF EXISTS applications CASCADE;
DROP TABLE IF EXISTS icards CASCADE;
DROP TABLE IF EXISTS icard_sequence CASCADE;
DROP TABLE IF EXISTS icard_requests CASCADE;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Departments table (8 departments for employee selection)
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(10) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dept_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Employees table (Master Database - 7000+ records)
CREATE TABLE employees (
    hrms_id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    emp_number VARCHAR(50) NOT NULL,
    dob DATE NOT NULL,
    category ENUM('gazetted', 'non_gazetted') NOT NULL,
    department_id INT,
    designation VARCHAR(100),
    mobile_no VARCHAR(15),
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_department (department_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. System Users (Admin, 13 COs, Dealer, AWO)
CREATE TABLE system_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'co', 'dealer', 'awo') NOT NULL,
    department_id INT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    mobile VARCHAR(15),
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Applications table (I-Card applications tracking)
CREATE TABLE applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hrms_id VARCHAR(20) NOT NULL,
    designation VARCHAR(100),
    mobile_no VARCHAR(15),
    height VARCHAR(10),
    blood_group VARCHAR(5),
    residential_address TEXT,
    identification_mark VARCHAR(255),
    photo_path VARCHAR(255),
    emp_signature_path VARCHAR(255),
    auth_signature_path VARCHAR(255),
    current_status ENUM(
        'draft',
        'submitted',
        'co_pending',
        'dealer_pending',
        'awo_pending',
        'approved',
        'rejected'
    ) DEFAULT 'draft',
    submitted_at TIMESTAMP NULL,
    co_reviewed_at TIMESTAMP NULL,
    dealer_reviewed_at TIMESTAMP NULL,
    awo_reviewed_at TIMESTAMP NULL,
    co_remarks TEXT,
    dealer_remarks TEXT,
    awo_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (current_status),
    INDEX idx_hrms (hrms_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. I-Cards table (Generated I-Cards)
CREATE TABLE icards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hrms_id VARCHAR(20) NOT NULL,
    application_id INT NOT NULL,
    icard_number VARCHAR(20) UNIQUE NOT NULL,
    pdf_path VARCHAR(255) NOT NULL,
    status ENUM('active', 'revoked', 'lost', 'replaced') DEFAULT 'active',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL,
    revocation_reason VARCHAR(255),
    is_current BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_hrms (hrms_id),
    INDEX idx_number (icard_number),
    INDEX idx_current (is_current)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. I-Card Sequence table (Unique number generation)
CREATE TABLE icard_sequence (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prefix VARCHAR(10) NOT NULL DEFAULT 'ERKPAW',
    sequence INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_prefix (prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. I-Card Requests table (Update/Lost/Revoke requests)
CREATE TABLE icard_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hrms_id VARCHAR(20) NOT NULL,
    original_icard_id INT NOT NULL,
    request_type ENUM('update', 'lost', 'revoke') NOT NULL,
    reason TEXT NOT NULL,
    new_data JSON,
    new_photo_path VARCHAR(255),
    new_emp_signature_path VARCHAR(255),
    new_auth_signature_path VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    dealer_remarks TEXT,
    awo_remarks TEXT,
    dealer_reviewed_at TIMESTAMP NULL,
    awo_reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_hrms (hrms_id),
    INDEX idx_type (request_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert 8 Departments (for employee selection dropdown)
INSERT INTO departments (name, code, status) VALUES 
('Personnel', 'PERS', 'active'),
('Medical', 'MED', 'active'),
('Mechanical', 'MECH', 'active'),
('Electrical', 'ELEC', 'active'),
('Accounts', 'ACC', 'active'),
('Engineering', 'ENG', 'active'),
('Stores', 'STR', 'active'),
('IT', 'IT', 'active');

-- Insert System Users (1 Admin + 13 COs + placeholders for Dealer & AWO)
INSERT INTO system_users (username, password_hash, role, full_name, email, is_active) VALUES 
-- Admin User (password: admin123)
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 'admin@railway.gov.in', 1),

-- 13 Controlling Officers (password: admin123 - admin can change these)
('dycpo_kpa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'Dy.CPO/KPA', 'dycpo.kpa@railway.gov.in', 1),
('dycme_c_kpa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'Dy.CME/C/KPA', 'dycme.c.kpa@railway.gov.in', 1),
('dycme_g_kpa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'Dy.CME/G/KPA', 'dycme.g.kpa@railway.gov.in', 1),
('dycee_l_kpa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'Dy.CEE/L/KPA', 'dycee.l.kpa@railway.gov.in', 1),
('dycee_g_kpa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'Dy.CEE/G/KPA', 'dycee.g.kpa@railway.gov.in', 1),
('dycee_w_kpa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'Dy.CEE/W/KPA', 'dycee.w.kpa@railway.gov.in', 1),
('dycee_pd_kpa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'Dy.CEE/P&D/KPA', 'dycee.pd.kpa@railway.gov.in', 1),
('dycmm_kpa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'Dy.CMM/KPA', 'dycmm.kpa@railway.gov.in', 1),
('dycmm_hlr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'Dy.CMM/HLR', 'dycmm.hlr@railway.gov.in', 1),
('dycao_kpa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'Dy.CAO/KPA', 'dycao.kpa@railway.gov.in', 1),
('cms_kpa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'CMS/KPA', 'cms.kpa@railway.gov.in', 1),
('edpm_kpa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'EDPM/KPA', 'edpm.kpa@railway.gov.in', 1),
('ate_kpa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'co', 'ATE/KPA', 'ate.kpa@railway.gov.in', 1),

-- Placeholder users (admin will create specific dealer/AWO accounts)
('dealer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dealer', 'Dealer User', 'dealer@railway.gov.in', 1),
('awo', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'awo', 'AWO User', 'awo@railway.gov.in', 1);

-- Initialize I-Card sequence
INSERT INTO icard_sequence (prefix, sequence) VALUES ('ERKPAW', 1);

-- Stored procedure for thread-safe I-Card number generation
DELIMITER //
CREATE PROCEDURE get_next_icard_number(OUT next_number VARCHAR(20))
BEGIN
    DECLARE seq INT;
    DECLARE prefix_val VARCHAR(10);
    
    START TRANSACTION;
    
    SELECT sequence, prefix INTO seq, prefix_val 
    FROM icard_sequence 
    WHERE prefix = 'ERKPAW' 
    FOR UPDATE;
    
    SET next_number = CONCAT(prefix_val, '/', LPAD(seq, 5, '0'));
    
    UPDATE icard_sequence 
    SET sequence = sequence + 1, updated_at = NOW() 
    WHERE prefix = 'ERKPAW';
    
    COMMIT;
END //
DELIMITER ;