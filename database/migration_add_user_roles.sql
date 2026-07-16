-- Migration: tabel user_roles untuk peran tambahan (multi-role per user).
-- users.role tetap menjadi peran utama; user_roles menampung peran tambahan.
-- Aman diulang.

USE bmn_streaming;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('superadmin','admin','pemohon','supervisor','admin_gudang','inventory_staff','it_staff_pembantu','pimpinan') NOT NULL,
    PRIMARY KEY (user_id, role),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
