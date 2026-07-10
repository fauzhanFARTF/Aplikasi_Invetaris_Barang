-- Migration: tambah restored_by/restored_at (siapa & kapan memulihkan data yang
-- di-soft-delete) pada 6 tabel entitas utama: users, categories, assets, packages,
-- loans, repairs. Melengkapi created_by/updated_by/deleted_by yang sudah ada
-- (lihat database/migration_add_soft_delete.sql).
-- Jalankan file ini jika database sudah ada dan tidak ingin di-reset ulang (schema.sql akan DROP semua tabel).
-- Aman dijalankan berkali-kali (idempotent) di MariaDB 10.11 / MySQL 8 yang mendukung
-- `ADD COLUMN IF NOT EXISTS`. Jika versi server tidak mendukungnya, hapus klausa
-- "IF NOT EXISTS" dan jalankan manual setelah memeriksa kolom belum ada.

USE bmn_streaming;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS restored_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS restored_at DATETIME NULL;

ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS restored_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS restored_at DATETIME NULL;

ALTER TABLE assets
    ADD COLUMN IF NOT EXISTS restored_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS restored_at DATETIME NULL;

ALTER TABLE packages
    ADD COLUMN IF NOT EXISTS restored_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS restored_at DATETIME NULL;

ALTER TABLE loans
    ADD COLUMN IF NOT EXISTS restored_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS restored_at DATETIME NULL;

ALTER TABLE repairs
    ADD COLUMN IF NOT EXISTS restored_by BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS restored_at DATETIME NULL;
