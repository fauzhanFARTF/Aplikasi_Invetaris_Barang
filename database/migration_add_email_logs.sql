-- Migration: pindahkan penyimpanan log email dari file (storage/emails/) ke database.
-- Aman dijalankan berulang kali (idempotent) berkat IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS email_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    to_email VARCHAR(150) NOT NULL,
    to_name VARCHAR(150) NULL,
    from_email VARCHAR(150) NOT NULL,
    from_name VARCHAR(150) NULL,
    subject VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_logs_to (to_email),
    INDEX idx_email_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
