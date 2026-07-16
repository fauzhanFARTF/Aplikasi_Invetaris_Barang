-- Migration: tambah 3 role admin-pembantu terfokus dan hentikan it_staff_pembantu.
--   administrator_pembantu_manajemen_user     -> hanya manajemen user
--   administrator_pembantu_manajemen_alat      -> hanya manajemen alat
--   administrator_pembantu_manajemen_kategori  -> hanya manajemen kategori
-- User lama ber-role it_staff_pembantu dialihkan ke administrator_pembantu_manajemen_alat.
-- Aman diulang.

USE bmn_streaming;

ALTER TABLE users MODIFY COLUMN role
    ENUM('superadmin','admin','pemohon','supervisor','admin_gudang','inventory_staff','it_staff_pembantu','administrator_pembantu_manajemen_user','administrator_pembantu_manajemen_alat','administrator_pembantu_manajemen_kategori','pimpinan') NOT NULL;

ALTER TABLE user_roles MODIFY COLUMN role
    ENUM('superadmin','admin','pemohon','supervisor','admin_gudang','inventory_staff','it_staff_pembantu','administrator_pembantu_manajemen_user','administrator_pembantu_manajemen_alat','administrator_pembantu_manajemen_kategori','pimpinan') NOT NULL;

UPDATE users      SET role='administrator_pembantu_manajemen_alat' WHERE role='it_staff_pembantu';
UPDATE user_roles SET role='administrator_pembantu_manajemen_alat' WHERE role='it_staff_pembantu';
