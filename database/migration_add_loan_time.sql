-- Migration: tambah jam acara (start_time, end_time) pada tabel loans.
-- Aman diulang.

USE bmn_streaming;

ALTER TABLE loans ADD COLUMN IF NOT EXISTS start_time TIME NULL;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS end_time TIME NULL;
