-- Migration: tambah role 'superadmin' pada enum users.role.
-- Superadmin = akses penuh seluruh aplikasi + kemampuan RESET data per-manajemen
-- (peminjaman, user, alat, kategori, paket, perbaikan) yang TIDAK dimiliki admin biasa.
-- Jalankan file ini jika database sudah ada; aman diulang (MODIFY idempotent).

USE bmn_streaming;

ALTER TABLE users
    MODIFY COLUMN role ENUM('superadmin','admin','pemohon','supervisor','admin_gudang') NOT NULL;
