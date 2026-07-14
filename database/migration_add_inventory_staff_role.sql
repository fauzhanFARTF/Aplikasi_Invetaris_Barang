-- Migration: tambah role 'inventory_staff' pada enum users.role.
-- Inventory Staff = mengelola alat: menambah alat, serta mengedit/menghapus
-- HANYA alat yang dia tambahkan sendiri (berdasarkan assets.created_by).
-- Aman diulang (MODIFY idempotent).

USE bmn_streaming;

ALTER TABLE users
    MODIFY COLUMN role ENUM('superadmin','admin','pemohon','supervisor','admin_gudang','inventory_staff') NOT NULL;
