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
        // Samakan zona waktu MySQL dengan zona aplikasi (APP_TZ, default Asia/Jakarta).
        // Tanpa ini NOW()/CURRENT_TIMESTAMP mengikuti zona OS server — di server
        // produksi itu UTC, sehingga created_at dkk tercatat 7 jam lebih awal.
        // Dipakai offset numerik (+07:00), bukan nama zona, karena tabel zona waktu
        // MySQL sering tidak terpasang. Indonesia tidak punya DST jadi offset tetap aman.
        $pdo->exec("SET time_zone = '" . (new DateTime('now'))->format('P') . "'");
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

        // Perluas enum role bila ada nilai baru yang belum terdaftar
        // (superadmin, inventory_staff, it_staff_pembantu, pimpinan).
        $roleCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
        if ($roleCol) {
            foreach (['superadmin', 'inventory_staff', 'administrator_pembantu_manajemen_user', 'administrator_pembantu_manajemen_alat', 'administrator_pembantu_manajemen_kategori', 'pimpinan'] as $needle) {
                if (strpos($roleCol['Type'], $needle) === false) {
                    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin','admin','pemohon','supervisor','admin_gudang','inventory_staff','it_staff_pembantu','administrator_pembantu_manajemen_user','administrator_pembantu_manajemen_alat','administrator_pembantu_manajemen_kategori','pimpinan') NOT NULL");
                    break;
                }
            }
            // Selaraskan enum user_roles bila tabelnya sudah ada dengan enum lama.
            try {
                $urCol = $pdo->query("SHOW COLUMNS FROM user_roles LIKE 'role'")->fetch();
                if ($urCol && strpos($urCol['Type'], 'administrator_pembantu_manajemen_alat') === false) {
                    $pdo->exec("ALTER TABLE user_roles MODIFY COLUMN role ENUM('superadmin','admin','pemohon','supervisor','admin_gudang','inventory_staff','it_staff_pembantu','administrator_pembantu_manajemen_user','administrator_pembantu_manajemen_alat','administrator_pembantu_manajemen_kategori','pimpinan') NOT NULL");
                }
            } catch (Throwable $e) {}
            // Role 'it_staff_pembantu' dihentikan — alihkan ke pengelola alat baru.
            $pdo->exec("UPDATE users SET role='administrator_pembantu_manajemen_alat' WHERE role='it_staff_pembantu'");
            try { $pdo->exec("UPDATE user_roles SET role='administrator_pembantu_manajemen_alat' WHERE role='it_staff_pembantu'"); } catch (Throwable $e) {}
        }

        // Login dengan Google + pendaftaran mandiri yang menunggu verifikasi admin.
        if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'google_id'")->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(64) NULL UNIQUE AFTER email");
            // Akun Google tidak punya password, jadi kolomnya harus boleh NULL.
            // Auth::attempt() menolak baris ber-password NULL supaya akun Google
            // tidak bisa ditembus lewat form email+password.
            $pdo->exec("ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE users ADD COLUMN reg_status ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved' AFTER is_active");
            $pdo->exec("ALTER TABLE users ADD COLUMN verified_by BIGINT UNSIGNED NULL");
            $pdo->exec("ALTER TABLE users ADD COLUMN verified_at DATETIME NULL");
            // Seluruh akun yang sudah ada dianggap sudah terverifikasi — jangan
            // sampai admin ikut terkunci oleh fitur baru ini.
            $pdo->exec("UPDATE users SET reg_status = 'approved' WHERE reg_status IS NULL OR reg_status = ''");
        }

        // Jenis peminjaman: 'event' (acara, ada rentang tanggal) atau 'opd'
        // (barang keluar ke OPD tanpa batas waktu, kembali hanya bila rusak).
        if (!$pdo->query("SHOW COLUMNS FROM loans LIKE 'loan_type'")->fetch()) {
            $pdo->exec("ALTER TABLE loans ADD COLUMN loan_type ENUM('event','opd') NOT NULL DEFAULT 'event' AFTER status");
        }

        // Barang habis pakai: ditandai PER BARIS peminjaman OPD (bukan sifat tetap
        // alat), dicentang saat mengajukan peminjaman Untuk OPD. Barang habis pakai
        // dianggap tuntas saat diserahkan — tidak ditunggu kembali.
        if (!$pdo->query("SHOW COLUMNS FROM loan_items LIKE 'is_consumable'")->fetch()) {
            $pdo->exec("ALTER TABLE loan_items ADD COLUMN is_consumable TINYINT(1) NOT NULL DEFAULT 0");
        }
        // Kolom assets.is_consumable (pendekatan lama) dipertahankan bila sudah ada —
        // tidak dipakai lagi, dan menghapus kolom berisiko; dibiarkan sebagai default
        // opsional. Tidak ditambahkan pada instalasi baru.

        // Kolom uuid untuk 6 entitas (dipakai di URL, id integer tetap internal).
        // Tambah kolom bila belum ada, lalu backfill baris yang uuid-nya NULL —
        // sekaligus jadi jaring pengaman kalau ada insert yang terlewat mengisi uuid.
        foreach (['users', 'categories', 'assets', 'packages', 'loans', 'repairs'] as $table) {
            $has = $pdo->query("SHOW COLUMNS FROM $table LIKE 'uuid'")->fetch();
            if (!$has) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN uuid CHAR(36) NULL UNIQUE");
            }
            $pdo->exec("UPDATE $table SET uuid = UUID() WHERE uuid IS NULL");
        }

        // Kolom code_prefix (kode singkatan kategori) untuk kode aset otomatis.
        if (!$pdo->query("SHOW COLUMNS FROM categories LIKE 'code_prefix'")->fetch()) {
            $pdo->exec("ALTER TABLE categories ADD COLUMN code_prefix VARCHAR(20) NULL");
        }

        // Jam acara (start_time, end_time) pada loans.
        foreach (['start_time', 'end_time'] as $col) {
            if (!$pdo->query("SHOW COLUMNS FROM loans LIKE '$col'")->fetch()) {
                $pdo->exec("ALTER TABLE loans ADD COLUMN $col TIME NULL");
            }
        }

        // Tabel peran tambahan (multi-role per user).
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (
            user_id BIGINT UNSIGNED NOT NULL,
            role ENUM('superadmin','admin','pemohon','supervisor','admin_gudang','inventory_staff','it_staff_pembantu','administrator_pembantu_manajemen_user','administrator_pembantu_manajemen_alat','administrator_pembantu_manajemen_kategori','pimpinan') NOT NULL,
            PRIMARY KEY (user_id, role),
            CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Tabel personel (IT Staff) yang dilibatkan pada acara peminjaman.
        $pdo->exec("CREATE TABLE IF NOT EXISTS loan_participants (
            loan_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (loan_id, user_id),
            CONSTRAINT fk_lp_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
            CONSTRAINT fk_lp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        // Never let a migration hiccup break the app (e.g. limited DB privileges) —
        // just log it so an admin can still apply database/migration_add_asset_price.sql /
        // database/migration_add_user_photo.sql / database/migration_add_soft_delete.sql /
        // database/migration_add_restore_trail.sql manually if needed.
        error_log('[simassta-bmn] auto-migration check failed: ' . $e->getMessage());
    }
}
