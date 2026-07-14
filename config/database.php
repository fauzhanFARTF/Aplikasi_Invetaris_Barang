<?php
declare(strict_types=1);
// PDO singleton
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // Prefer unix socket if available; fallback to TCP
        if (defined('DB_SOCKET') && file_exists(DB_SOCKET)) {
            $dsn = 'mysql:unix_socket=' . DB_SOCKET . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        } else {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        }
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        run_pending_migrations($pdo);
    }
    return $pdo;
}

// Self-healing migration runner — keeps databases created before the "Lost/Hilang"
// feature was added in sync automatically, so no manual SQL step is ever required.
// Cheap SHOW COLUMNS check on every request; ALTER only runs once, the first time
// it detects the old schema, then never triggers again.
function run_pending_migrations(PDO $pdo): void {
    static $checked = false;
    if ($checked) return; // avoid re-checking more than once per request
    $checked = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM loan_items LIKE 'item_status'")->fetch();
        if ($col && strpos($col['Type'], 'ReturnedLost') === false) {
            $pdo->exec("ALTER TABLE assets MODIFY COLUMN status ENUM('Available','Booked','CheckedOut','Damaged','Retired','Lost') NOT NULL DEFAULT 'Available'");
            $pdo->exec("ALTER TABLE loan_items
                        MODIFY COLUMN return_condition ENUM('Good','Damaged','Lost') NULL,
                        MODIFY COLUMN item_status ENUM('Reserved','CheckedOut','ReturnedGood','ReturnedDamaged','ReturnedLost','InRepair','Restored') NOT NULL DEFAULT 'Reserved'");
        }

        $priceCol = $pdo->query("SHOW COLUMNS FROM assets LIKE 'purchase_price'")->fetch();
        if (!$priceCol) {
            $pdo->exec("ALTER TABLE assets
                        ADD COLUMN purchase_price DECIMAL(15,2) NULL COMMENT 'Harga perolehan (harga dulu / beli)' AFTER photo,
                        ADD COLUMN purchase_date DATE NULL COMMENT 'Tanggal perolehan/pembelian' AFTER purchase_price,
                        ADD COLUMN current_value DECIMAL(15,2) NULL COMMENT 'Nilai sekarang / nilai buku saat ini' AFTER purchase_date");
        }

        $userPhotoCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'photo'")->fetch();
        if (!$userPhotoCol) {
            $pdo->exec("ALTER TABLE users ADD COLUMN photo VARCHAR(255) NULL AFTER unit_kerja");
        }

        // Soft delete + audit trail (created_by/updated_by/deleted_by/deleted_at)
        // di 6 tabel entitas utama. Cek satu kolom penanda (deleted_at) per tabel;
        // kalau belum ada, tambahkan semua kolom terkait sekaligus untuk tabel itu.
        $softDeleteTables = ['users', 'categories', 'assets', 'packages', 'loans', 'repairs'];
        foreach ($softDeleteTables as $table) {
            $col = $pdo->query("SHOW COLUMNS FROM $table LIKE 'deleted_at'")->fetch();
            if ($col) continue;
            $pdo->exec("ALTER TABLE $table
                        ADD COLUMN created_by BIGINT UNSIGNED NULL,
                        ADD COLUMN updated_by BIGINT UNSIGNED NULL,
                        ADD COLUMN deleted_by BIGINT UNSIGNED NULL,
                        ADD COLUMN deleted_at DATETIME NULL");
        }
        // categories tidak punya created_at/updated_at sejak awal; packages & repairs
        // sudah punya created_at tapi belum updated_at — lengkapi kalau belum ada.
        $catCreatedAt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'created_at'")->fetch();
        if (!$catCreatedAt) {
            $pdo->exec("ALTER TABLE categories
                        ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
        foreach (['packages', 'repairs'] as $table) {
            $updatedAt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'updated_at'")->fetch();
            if (!$updatedAt) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            }
        }

        // Jejak pemulihan (siapa & kapan memulihkan data yang di-soft-delete),
        // melengkapi created_by/updated_by/deleted_by di atas.
        foreach ($softDeleteTables as $table) {
            $col = $pdo->query("SHOW COLUMNS FROM $table LIKE 'restored_at'")->fetch();
            if ($col) continue;
            $pdo->exec("ALTER TABLE $table
                        ADD COLUMN restored_by BIGINT UNSIGNED NULL,
                        ADD COLUMN restored_at DATETIME NULL");
        }

        // Role 'superadmin' (akses penuh + reset data per-manajemen).
        $roleCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
        if ($roleCol && strpos($roleCol['Type'], 'superadmin') === false) {
            $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin','admin','pemohon','supervisor','admin_gudang') NOT NULL");
        }
    } catch (Throwable $e) {
        // Never let a migration hiccup break the app (e.g. limited DB privileges) —
        // just log it so an admin can still apply database/migration_add_asset_price.sql /
        // database/migration_add_user_photo.sql / database/migration_add_soft_delete.sql /
        // database/migration_add_restore_trail.sql manually if needed.
        error_log('[simassta-bmn] auto-migration check failed: ' . $e->getMessage());
    }
}
