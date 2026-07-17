<?php
declare(strict_types=1);
// Geser timestamp lama ke zona waktu aplikasi (APP_TZ, default Asia/Jakarta / WIB).
//
// Latar: kolom waktu otomatis (created_at, updated_at, deleted_at, dst) ditulis oleh
// MySQL lewat NOW()/CURRENT_TIMESTAMP, yang mengikuti zona OS server. Di server
// produksi OS-nya UTC, sehingga waktu tercatat 7 jam lebih awal dari WIB.
//
// Sejak config/database.php menjalankan SET time_zone, data BARU sudah benar.
// Skrip ini khusus merapikan data LAMA yang terlanjur tercatat dengan zona lama.
//
// Pemakaian (dari root aplikasi):
//   php scripts/fix_timezone_data.php            -> dry run, tidak mengubah apa pun
//   php scripts/fix_timezone_data.php --apply    -> jalankan perubahan
//
// Aman diulang: setelah berhasil, penandanya dicatat di audit_logs dan skrip
// menolak jalan lagi supaya waktu tidak tergeser dua kali.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

const MARKER_ACTION = 'fix.timezone_shift';

/** Kolom waktu yang ditulis mesin — SEMUA ditulis MySQL, jadi ikut tergeser. */
const TIME_COLUMNS = [
    'users'         => ['created_at', 'updated_at', 'deleted_at', 'restored_at'],
    'categories'    => ['created_at', 'updated_at', 'deleted_at', 'restored_at'],
    'assets'        => ['created_at', 'updated_at', 'deleted_at', 'restored_at'],
    'packages'      => ['created_at', 'updated_at', 'deleted_at', 'restored_at'],
    'loans'         => ['created_at', 'updated_at', 'deleted_at', 'restored_at', 'approved_at', 'checkout_at', 'checkin_at'],
    'loan_items'    => ['checkout_at', 'checkin_at'],
    'repairs'       => ['created_at', 'updated_at', 'deleted_at', 'restored_at', 'completed_at', 'form_printed_at'],
    'audit_logs'    => ['created_at'],
    'notifications' => ['created_at'],
];
// SENGAJA TIDAK disentuh — diisi manusia, bukan jam server:
//   assets.purchase_date, loans.start_date, loans.end_date, loans.start_time, loans.end_time

$apply = in_array('--apply', $argv, true);
$pdo = db();

// --- 1. Deteksi zona lama --------------------------------------------------
// db() sudah menyetel session time_zone ke zona aplikasi. Untuk tahu zona yang
// dipakai saat data lama ditulis, kembalikan session ke zona global server dulu.
$pdo->exec("SET SESSION time_zone = @@global.time_zone");
$probe     = $pdo->query("SELECT NOW() n, UTC_TIMESTAMP() u, @@global.time_zone g")->fetch();
$oldOffset = (int) round((strtotime($probe['n']) - strtotime($probe['u'])) / 3600);
$appOffset = (int) round((new DateTime('now'))->getOffset() / 3600);
$shift     = $appOffset - $oldOffset;

$appTz = date_default_timezone_get();
echo "Zona aplikasi (PHP)      : $appTz (UTC" . sprintf('%+d', $appOffset) . ")\n";
echo "Zona MySQL server        : {$probe['g']} (UTC" . sprintf('%+d', $oldOffset) . ")\n";
echo "Waktu MySQL sekarang     : {$probe['n']}\n";
echo "Pergeseran yang dibutuhkan: " . sprintf('%+d', $shift) . " jam\n\n";

if ($shift === 0) {
    echo "MySQL sudah memakai zona yang sama dengan aplikasi — tidak ada yang perlu digeser.\n";
    exit(0);
}

// --- 2. Tolak bila sudah pernah dijalankan ---------------------------------
$done = $pdo->prepare("SELECT created_at, details FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT 1");
$done->execute([MARKER_ACTION]);
if ($row = $done->fetch()) {
    fwrite(STDERR, "DITOLAK: perbaikan ini sudah pernah dijalankan pada {$row['created_at']} ({$row['details']}).\n"
                 . "Menjalankan ulang akan menggeser waktu dua kali. Hapus penanda di audit_logs bila memang perlu diulang.\n");
    exit(1);
}

// Kembalikan ke zona aplikasi: pergeseran di bawah murni aritmetika pada nilai
// DATETIME (tidak terpengaruh session tz), tapi penanda audit_logs harus WIB.
$pdo->exec("SET SESSION time_zone = '" . (new DateTime('now'))->format('P') . "'");

// --- 3. Ringkasan / dry run ------------------------------------------------
$plan = [];
foreach (TIME_COLUMNS as $table => $columns) {
    try {
        $existing = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        echo "  ! tabel $table tidak ada — dilewati\n";
        continue;
    }
    $cols = array_values(array_intersect($columns, $existing));
    if (!$cols) continue;
    $counts = [];
    foreach ($cols as $c) {
        $counts[$c] = (int) $pdo->query("SELECT COUNT(*) FROM `$table` WHERE `$c` IS NOT NULL")->fetchColumn();
    }
    $plan[$table] = $cols;
    printf("  %-14s %s\n", $table, implode(', ', array_map(fn ($c) => "$c({$counts[$c]})", $cols)));
}

// Contoh nyata supaya perubahannya bisa dilihat sebelum dijalankan.
echo "\nContoh perubahan (5 peminjaman terbaru):\n";
$sample = $pdo->query("SELECT loan_code, created_at FROM loans ORDER BY id DESC LIMIT 5")->fetchAll();
foreach ($sample as $s) {
    $after = (new DateTime($s['created_at']))->modify(sprintf('%+d hours', $shift))->format('Y-m-d H:i:s');
    printf("  %-14s %s  ->  %s\n", $s['loan_code'], $s['created_at'], $after);
}
if (!$sample) echo "  (belum ada data peminjaman)\n";

if (!$apply) {
    echo "\nDRY RUN — belum ada yang diubah. Jalankan ulang dengan --apply untuk menerapkan.\n";
    exit(0);
}

// --- 4. Terapkan -----------------------------------------------------------
// Semua kolom waktu satu tabel digeser dalam SATU statement, termasuk updated_at
// secara eksplisit. Kalau updated_at dibiarkan, ON UPDATE CURRENT_TIMESTAMP akan
// menimpanya dengan jam sekarang dan riwayat perubahan jadi hilang.
echo "\nMenerapkan pergeseran " . sprintf('%+d', $shift) . " jam...\n";
$pdo->beginTransaction();
try {
    $total = 0;
    foreach ($plan as $table => $cols) {
        $sets = implode(', ', array_map(
            fn ($c) => "`$c` = DATE_ADD(`$c`, INTERVAL $shift HOUR)", // NULL tetap NULL
            $cols
        ));
        $n = $pdo->exec("UPDATE `$table` SET $sets");
        $total += (int) $n;
        printf("  %-14s %d baris\n", $table, (int) $n);
    }
    $note = sprintf('Geser %+d jam (UTC%+d -> %s / UTC%+d), %d baris.', $shift, $oldOffset, $appTz, $appOffset, $total);
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (NULL, ?, NULL, NULL, ?)")
        ->execute([MARKER_ACTION, $note]);
    $pdo->commit();
    echo "\nSelesai. $note\n";
    echo "Penanda dicatat di audit_logs sehingga skrip ini tidak akan jalan dua kali.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "GAGAL, semua perubahan dibatalkan: " . $e->getMessage() . "\n");
    exit(1);
}
