-- Schema for Sistem Informasi Inventaris Aset Streaming BMN
-- Diskominfo Kabupaten Tangerang - Smart Building
-- Engine: MariaDB 10.11 / MySQL 8

CREATE DATABASE IF NOT EXISTS bmn_streaming CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bmn_streaming;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS repairs;
DROP TABLE IF EXISTS loan_items;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS package_items;
DROP TABLE IF EXISTS packages;
DROP TABLE IF EXISTS assets;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','pemohon','supervisor','admin_gudang') NOT NULL,
    phone VARCHAR(30) NULL,
    unit_kerja VARCHAR(150) NULL,
    photo VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    restored_by BIGINT UNSIGNED NULL,
    restored_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_restored_by FOREIGN KEY (restored_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    restored_by BIGINT UNSIGNED NULL,
    restored_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_categories_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_categories_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_categories_restored_by FOREIGN KEY (restored_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE assets (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    asset_code VARCHAR(50) NOT NULL UNIQUE,
    bmn_number VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category_id INT UNSIGNED NULL,
    brand VARCHAR(80) NULL,
    model VARCHAR(80) NULL,
    serial_number VARCHAR(120) NULL,
    barcode VARCHAR(120) NOT NULL UNIQUE,
    condition_note VARCHAR(255) NULL,
    photo VARCHAR(255) NULL,
    purchase_price DECIMAL(15,2) NULL COMMENT 'Harga perolehan (harga dulu / beli)',
    purchase_date DATE NULL COMMENT 'Tanggal perolehan/pembelian',
    current_value DECIMAL(15,2) NULL COMMENT 'Nilai sekarang / nilai buku saat ini',
    status ENUM('Available','Booked','CheckedOut','Damaged','Retired','Lost') NOT NULL DEFAULT 'Available',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    restored_by BIGINT UNSIGNED NULL,
    restored_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_asset_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_assets_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_assets_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_assets_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_assets_restored_by FOREIGN KEY (restored_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_asset_status (status),
    INDEX idx_asset_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE packages (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    restored_by BIGINT UNSIGNED NULL,
    restored_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_packages_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_packages_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_packages_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_packages_restored_by FOREIGN KEY (restored_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE package_items (
    package_id INT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (package_id, asset_id),
    CONSTRAINT fk_pi_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
    CONSTRAINT fk_pi_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE loans (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    loan_code VARCHAR(30) NOT NULL UNIQUE,
    requester_id BIGINT UNSIGNED NOT NULL,
    event_name VARCHAR(200) NOT NULL,
    event_location VARCHAR(200) NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    purpose TEXT NULL,
    status ENUM('Pending','Approved','Rejected','CheckedOut','Returned','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    supervisor_id BIGINT UNSIGNED NULL,
    approval_note TEXT NULL,
    approved_at DATETIME NULL,
    checkout_at DATETIME NULL,
    checkin_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    restored_by BIGINT UNSIGNED NULL,
    restored_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_loan_requester FOREIGN KEY (requester_id) REFERENCES users(id),
    CONSTRAINT fk_loan_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id),
    CONSTRAINT fk_loans_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_loans_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_loans_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_loans_restored_by FOREIGN KEY (restored_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_loan_status (status),
    INDEX idx_loan_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE loan_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    loan_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED NOT NULL,
    package_id INT UNSIGNED NULL,
    checkout_by BIGINT UNSIGNED NULL,
    checkout_at DATETIME NULL,
    checkin_by BIGINT UNSIGNED NULL,
    checkin_at DATETIME NULL,
    return_condition ENUM('Good','Damaged','Lost') NULL,
    damage_note TEXT NULL,
    item_status ENUM('Reserved','CheckedOut','ReturnedGood','ReturnedDamaged','ReturnedLost','InRepair','Restored') NOT NULL DEFAULT 'Reserved',
    CONSTRAINT fk_li_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    CONSTRAINT fk_li_asset FOREIGN KEY (asset_id) REFERENCES assets(id),
    CONSTRAINT fk_li_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL,
    CONSTRAINT fk_li_checkout FOREIGN KEY (checkout_by) REFERENCES users(id),
    CONSTRAINT fk_li_checkin FOREIGN KEY (checkin_by) REFERENCES users(id),
    INDEX idx_li_status (item_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE repairs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    repair_code VARCHAR(30) NOT NULL UNIQUE,
    asset_id BIGINT UNSIGNED NOT NULL,
    loan_item_id BIGINT UNSIGNED NULL,
    complaint TEXT NOT NULL,
    form_printed_at DATETIME NULL,
    technician_name VARCHAR(120) NULL,
    action_taken TEXT NULL,
    completed_by BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    status ENUM('Open','FormPrinted','InRepair','Completed') NOT NULL DEFAULT 'Open',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    restored_by BIGINT UNSIGNED NULL,
    restored_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rep_asset FOREIGN KEY (asset_id) REFERENCES assets(id),
    CONSTRAINT fk_rep_loanitem FOREIGN KEY (loan_item_id) REFERENCES loan_items(id) ON DELETE SET NULL,
    CONSTRAINT fk_rep_completed FOREIGN KEY (completed_by) REFERENCES users(id),
    CONSTRAINT fk_repairs_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_repairs_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_repairs_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_repairs_restored_by FOREIGN KEY (restored_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_repair_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id VARCHAR(60) NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
