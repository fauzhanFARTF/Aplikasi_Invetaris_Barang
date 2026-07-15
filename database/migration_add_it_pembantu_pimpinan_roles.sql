-- Migration: tambah role 'it_staff_pembantu' (pengelola alat) dan 'pimpinan'
-- (hanya melihat / view-only) pada enum users.role. Aman diulang.

USE bmn_streaming;

ALTER TABLE users
    MODIFY COLUMN role ENUM('superadmin','admin','pemohon','supervisor','admin_gudang','inventory_staff','it_staff_pembantu','pimpinan') NOT NULL;
