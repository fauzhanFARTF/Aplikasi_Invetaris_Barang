-- Migration: tambah kolom foto pada tabel users
-- Jalankan file ini jika database sudah ada dan tidak ingin di-reset ulang (schema.sql akan DROP semua tabel).
-- Aman dijalankan berkali-kali (idempotent) di MariaDB 10.11 / MySQL 8 yang mendukung
-- `ADD COLUMN IF NOT EXISTS`. Jika versi server tidak mendukungnya, hapus klausa
-- "IF NOT EXISTS" dan jalankan manual setelah memeriksa kolom belum ada.

USE bmn_streaming;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS photo VARCHAR(255) NULL AFTER unit_kerja;
