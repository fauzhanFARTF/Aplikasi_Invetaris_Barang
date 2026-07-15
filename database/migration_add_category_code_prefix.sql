-- Migration: tambah kolom code_prefix (kode singkatan kategori, mis. CAMVIDEO)
-- ke tabel categories. Dipakai untuk membuat Kode Aset & No. BMN otomatis
-- saat menambah alat (CAMVIDEO-001, BMN-2026-CAMVIDEO-001). Aman diulang.

USE bmn_streaming;

ALTER TABLE categories ADD COLUMN IF NOT EXISTS code_prefix VARCHAR(20) NULL;
