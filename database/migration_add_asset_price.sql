-- Migration: tambah kolom harga perolehan (dulu) & nilai sekarang pada tabel assets
-- Jalankan file ini jika database sudah ada dan tidak ingin di-reset ulang (schema.sql akan DROP semua tabel).
-- Aman dijalankan berkali-kali (idempotent) di MariaDB 10.11 / MySQL 8 yang mendukung
-- `ADD COLUMN IF NOT EXISTS`. Jika versi server tidak mendukungnya, hapus klausa
-- "IF NOT EXISTS" dan jalankan manual setelah memeriksa kolom belum ada.

USE bmn_streaming;

ALTER TABLE assets
    ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(15,2) NULL COMMENT 'Harga perolehan (harga dulu / beli)' AFTER photo,
    ADD COLUMN IF NOT EXISTS purchase_date DATE NULL COMMENT 'Tanggal perolehan/pembelian' AFTER purchase_price,
    ADD COLUMN IF NOT EXISTS current_value DECIMAL(15,2) NULL COMMENT 'Nilai sekarang / nilai buku saat ini' AFTER purchase_date;

-- Tambah status 'Lost' (Hilang) agar saat alat dilaporkan hilang saat check-in,
-- sistem dapat menandai aset & item peminjaman dengan status yang sesuai dan
-- menampilkan harga dulu (purchase_price) & nilai sekarang (current_value)
-- sebagai acuan nilai kerugian/ganti rugi.
ALTER TABLE assets
    MODIFY COLUMN status ENUM('Available','Booked','CheckedOut','Damaged','Retired','Lost') NOT NULL DEFAULT 'Available';

ALTER TABLE loan_items
    MODIFY COLUMN return_condition ENUM('Good','Damaged','Lost') NULL,
    MODIFY COLUMN item_status ENUM('Reserved','CheckedOut','ReturnedGood','ReturnedDamaged','ReturnedLost','InRepair','Restored') NOT NULL DEFAULT 'Reserved';
