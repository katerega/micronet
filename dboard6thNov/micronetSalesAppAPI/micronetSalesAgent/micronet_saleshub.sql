
CREATE DATABASE IF NOT EXISTS micronet_saleshub
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE micronet_saleshub;

-- =====================================================
-- 1. AGENTS
-- =====================================================

CREATE TABLE agents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) UNIQUE,
    phone VARCHAR(30) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','agent') NOT NULL DEFAULT 'agent',
    status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    profile_photo VARCHAR(500),
    address TEXT,
    created_by BIGINT UNSIGNED NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_agent_role (role),
    INDEX idx_agent_status (status),
    CONSTRAINT fk_agent_creator
        FOREIGN KEY (created_by) REFERENCES agents(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 2. SESSIONS
-- =====================================================

CREATE TABLE sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agent_id BIGINT UNSIGNED NOT NULL,
    access_token_hash CHAR(64) NOT NULL UNIQUE,
    refresh_token_hash CHAR(64) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at DATETIME NOT NULL,
    refresh_expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_agent (agent_id),
    INDEX idx_session_expiry (expires_at),
    FOREIGN KEY (agent_id) REFERENCES agents(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 3. PASSWORD RESETS
-- =====================================================

CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agent_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reset_agent (agent_id),
    FOREIGN KEY (agent_id) REFERENCES agents(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 4. CUSTOMERS
-- =====================================================

CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(50) NOT NULL UNIQUE,
    customer_name VARCHAR(190) NOT NULL,
    contact_person VARCHAR(150),
    email VARCHAR(190),
    phone VARCHAR(30),
    alternative_phone VARCHAR(30),
    address TEXT,
    district VARCHAR(100),
    region VARCHAR(100),
    customer_type ENUM(
        'individual',
        'business',
        'institution',
        'government',
        'other'
    ) DEFAULT 'individual',
    status ENUM('active','inactive','prospect') DEFAULT 'active',
    assigned_agent_id BIGINT UNSIGNED,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_name (customer_name),
    INDEX idx_customer_phone (phone),
    INDEX idx_customer_agent (assigned_agent_id),
    FOREIGN KEY (assigned_agent_id) REFERENCES agents(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 5. VISITS
-- =====================================================

CREATE TABLE visits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agent_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    visit_type ENUM(
        'sales',
        'followup',
        'collection',
        'service',
        'other'
    ) DEFAULT 'sales',
    purpose VARCHAR(255),
    visit_notes TEXT,
    check_in_time DATETIME NOT NULL,
    check_out_time DATETIME NULL,
    check_in_latitude DECIMAL(10,7),
    check_in_longitude DECIMAL(10,7),
    check_out_latitude DECIMAL(10,7),
    check_out_longitude DECIMAL(10,7),
    status ENUM(
        'scheduled',
        'in_progress',
        'completed',
        'cancelled'
    ) DEFAULT 'in_progress',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_visit_agent (agent_id),
    INDEX idx_visit_customer (customer_id),
    INDEX idx_visit_date (check_in_time),
    FOREIGN KEY (agent_id) REFERENCES agents(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB;

-- =====================================================
-- 6. PRODUCTS
-- =====================================================

CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(100) NOT NULL UNIQUE,
    product_name VARCHAR(190) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    unit VARCHAR(50) DEFAULT 'piece',
    cost_price DECIMAL(15,2) DEFAULT 0.00,
    selling_price DECIMAL(15,2) NOT NULL,
    stock_quantity DECIMAL(15,3) DEFAULT 0,
    minimum_stock DECIMAL(15,3) DEFAULT 0,
    status ENUM('active','inactive','discontinued') DEFAULT 'active',
    image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_product_name (product_name),
    INDEX idx_product_category (category),
    INDEX idx_product_status (status)
) ENGINE=InnoDB;

-- =====================================================
-- 7. SALES
-- =====================================================

CREATE TABLE sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_number VARCHAR(50) NOT NULL UNIQUE,
    agent_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    visit_id BIGINT UNSIGNED NULL,
    sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount DECIMAL(15,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
    balance DECIMAL(15,2) NOT NULL DEFAULT 0,
    payment_status ENUM(
        'unpaid',
        'partial',
        'paid',
        'refunded'
    ) DEFAULT 'unpaid',
    sale_status ENUM(
        'draft',
        'confirmed',
        'cancelled',
        'completed'
    ) DEFAULT 'confirmed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sale_agent (agent_id),
    INDEX idx_sale_customer (customer_id),
    INDEX idx_sale_date (sale_date),
    FOREIGN KEY (agent_id) REFERENCES agents(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (visit_id) REFERENCES visits(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 8. SALE ITEMS
-- =====================================================

CREATE TABLE sale_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    product_name VARCHAR(190) NOT NULL,
    quantity DECIMAL(15,3) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    discount DECIMAL(15,2) DEFAULT 0,
    line_total DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sale_item_sale (sale_id),
    INDEX idx_sale_item_product (product_id),
    FOREIGN KEY (sale_id) REFERENCES sales(id)
        ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- =====================================================
-- 9. INVOICES
-- =====================================================

CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    sale_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    agent_id BIGINT UNSIGNED NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
    balance DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM(
        'draft',
        'sent',
        'partially_paid',
        'paid',
        'overdue',
        'cancelled'
    ) DEFAULT 'draft',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoice_customer (customer_id),
    INDEX idx_invoice_agent (agent_id),
    INDEX idx_invoice_due (due_date),
    FOREIGN KEY (sale_id) REFERENCES sales(id)
        ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (agent_id) REFERENCES agents(id)
) ENGINE=InnoDB;

-- =====================================================
-- 10. INVOICE ITEMS
-- =====================================================

CREATE TABLE invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(15,3) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    discount DECIMAL(15,2) DEFAULT 0,
    line_total DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice_item_invoice (invoice_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
        ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 11. PAYMENTS
-- =====================================================

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_reference VARCHAR(100) NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    agent_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NULL,
    invoice_id BIGINT UNSIGNED NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'UGX',
    payment_method ENUM(
        'cash',
        'mobile_money',
        'bank_transfer',
        'card',
        'cheque',
        'other'
    ) DEFAULT 'cash',
    transaction_reference VARCHAR(150),
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM(
        'pending',
        'completed',
        'failed',
        'reversed'
    ) DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_customer (customer_id),
    INDEX idx_payment_agent (agent_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_payment_invoice (invoice_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (agent_id) REFERENCES agents(id),
    FOREIGN KEY (sale_id) REFERENCES sales(id)
        ON DELETE SET NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 12. FOLLOWUPS
-- =====================================================

CREATE TABLE followups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agent_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NULL,
    invoice_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT,
    followup_date DATETIME NOT NULL,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM(
        'pending',
        'completed',
        'cancelled',
        'overdue'
    ) DEFAULT 'pending',
    completed_at DATETIME NULL,
    outcome TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_followup_agent (agent_id),
    INDEX idx_followup_customer (customer_id),
    INDEX idx_followup_date (followup_date),
    INDEX idx_followup_status (status),
    FOREIGN KEY (agent_id) REFERENCES agents(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (sale_id) REFERENCES sales(id)
        ON DELETE SET NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- 13. COMMISSIONS
-- =====================================================

CREATE TABLE commissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agent_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    commission_rate DECIMAL(7,4) NOT NULL DEFAULT 0,
    sale_amount DECIMAL(15,2) NOT NULL,
    commission_amount DECIMAL(15,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'UGX',
    status ENUM(
        'pending',
        'approved',
        'paid',
        'cancelled'
    ) DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_commission_agent (agent_id),
    INDEX idx_commission_sale (sale_id),
    INDEX idx_commission_status (status),
    FOREIGN KEY (agent_id) REFERENCES agents(id),
    FOREIGN KEY (sale_id) REFERENCES sales(id),
    FOREIGN KEY (approved_by) REFERENCES agents(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- END DATABASE INSTALLATION
-- =====================================================

