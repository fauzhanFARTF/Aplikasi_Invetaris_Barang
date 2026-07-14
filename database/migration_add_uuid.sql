-- Migration: tambah kolom uuid (CHAR(36) UNIQUE) ke 6 entitas utama dan
-- backfill UUID untuk baris yang sudah ada. UUID dipakai di URL agar id
-- integer internal tidak terekspos. Aman diulang.

USE bmn_streaming;

ALTER TABLE users      ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL UNIQUE;
ALTER TABLE categories ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL UNIQUE;
ALTER TABLE assets     ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL UNIQUE;
ALTER TABLE packages   ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL UNIQUE;
ALTER TABLE loans      ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL UNIQUE;
ALTER TABLE repairs    ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL UNIQUE;

UPDATE users      SET uuid = UUID() WHERE uuid IS NULL;
UPDATE categories SET uuid = UUID() WHERE uuid IS NULL;
UPDATE assets     SET uuid = UUID() WHERE uuid IS NULL;
UPDATE packages   SET uuid = UUID() WHERE uuid IS NULL;
UPDATE loans      SET uuid = UUID() WHERE uuid IS NULL;
UPDATE repairs    SET uuid = UUID() WHERE uuid IS NULL;
