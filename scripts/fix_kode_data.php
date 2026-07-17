<?php
declare(strict_types=1);
// Sesuaikan data lama dengan format kode yang baru. Dua pekerjaan terpisah:
//
//   1. Kode peminjaman  : LN-2026-000123  ->  APTIKA-YYYYMMDD-NNN
//                         NNN diurutkan per hari berdasarkan created_at, mulai 001.
//   2. Nomor BMN -> BMD : BMN-2024-KMR-001 -> BMD-2024-KMR-001
//                         Kolom assets.bmn_number DAN assets.barcode ikut diganti.
//                         Stiker QR lama tetap bisa dipindai karena penyerahan/
//                         pengembalian menerima awalan BMN- maupun BMD-.
//
// Pemakaian (dari root aplikasi):
//   php scripts/fix_kode_data.php            -> dry run, tidak mengubah apa pun
//   php scripts/fix_kode_data.php --apply    -> jalankan perubahan
//
// PERHATIAN: kode lama ditimpa dan TIDAK bisa dikembalikan. Berita Acara yang
// terlanjur dicetak memakai kode lama tidak akan cocok lagi dengan sistem.
// Backup dulu sebelum --apply.
//
// Aman diulang: tiap bagian mencatat penanda di audit_logs dan menolak jalan lagi.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

const MARK_LOAN = 'fix.loan_code_aptika';
const MARK_BMD  = 'fix.bmn_to_bmd';

$apply = in_array('--apply', $argv, true);
$pdo   = db();

/** Sudah pernah dijalankan? Kembalikan barisnya bila ya. */
function already(PDO $pdo, string $action) {
    $s = $pdo->prepare("SELECT created_at, details FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT 1");
    $s->execute([$action]);
    return $s->fetch() ?: null;
}
function mark(PDO $pdo, string $action, string $note): void {
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (NULL, ?, NULL, NULL, ?)")
        ->execute([$action, $note]);
}

// ─── BAGIAN 1: kode peminjaman ────────────────────────────────────────────────
echo "=== 1. Kode peminjaman -> APTIKA-YYYYMMDD-NNN ===\n";
$loanPlan = [];
$loanSkip = already($pdo, MARK_LOAN);
if ($loanSkip) {
    echo "  Dilewati: sudah dijalankan pada {$loanSkip['created_at']} ({$loanSkip['details']}).\n\n";
} else {
    // Urutkan per hari created_at; id sebagai penentu bila jam-nya sama persis.
    $rows = $pdo->query("SELECT id, loan_code, created_at FROM loans ORDER BY created_at ASC, id ASC")->fetchAll();
    $seq  = [];
    foreach ($rows as $r) {
        $day = date('Ymd', strtotime((string) $r['created_at']));
        $seq[$day] = ($seq[$day] ?? 0) + 1;
        $new = "APTIKA-$day-" . str_pad((string) $seq[$day], 3, '0', STR_PAD_LEFT);
        if ($new !== $r['loan_code']) {
            $loanPlan[] = ['id' => (int) $r['id'], 'old' => $r['loan_code'], 'new' => $new];
        }
    }
    if (!$loanPlan) {
        echo "  Tidak ada kode yang perlu diubah (" . count($rows) . " peminjaman).\n\n";
    } else {
        echo "  " . count($loanPlan) . " dari " . count($rows) . " peminjaman akan diubah. Contoh:\n";
        foreach (array_slice($loanPlan, 0, 8) as $p) printf("    %-18s ->  %s\n", $p['old'], $p['new']);
        if (count($loanPlan) > 8) echo "    ... dan " . (count($loanPlan) - 8) . " lainnya\n";
        echo "\n";
    }
}

// ─── BAGIAN 2: BMN -> BMD ─────────────────────────────────────────────────────
echo "=== 2. Nomor BMN -> BMD (bmn_number & barcode) ===\n";
$bmdSkip = already($pdo, MARK_BMD);
$nBmn = (int) $pdo->query("SELECT COUNT(*) FROM assets WHERE bmn_number LIKE 'BMN-%'")->fetchColumn();
$nBar = (int) $pdo->query("SELECT COUNT(*) FROM assets WHERE barcode LIKE 'BMN-%'")->fetchColumn();
if ($bmdSkip) {
    echo "  Dilewati: sudah dijalankan pada {$bmdSkip['created_at']} ({$bmdSkip['details']}).\n\n";
} elseif ($nBmn === 0 && $nBar === 0) {
    echo "  Tidak ada nilai berawalan BMN-.\n\n";
} else {
    echo "  bmn_number berawalan BMN- : $nBmn baris\n";
    echo "  barcode    berawalan BMN- : $nBar baris\n";
    foreach ($pdo->query("SELECT bmn_number, barcode FROM assets WHERE bmn_number LIKE 'BMN-%' LIMIT 5") as $r) {
        printf("    %-22s ->  %s\n", $r['bmn_number'], 'BMD-' . substr((string) $r['bmn_number'], 4));
    }
    echo "\n";
}

if (!$apply) {
    echo "DRY RUN — belum ada yang diubah. Jalankan ulang dengan --apply untuk menerapkan.\n";
    exit(0);
}

// ─── Terapkan ─────────────────────────────────────────────────────────────────
$pdo->beginTransaction();
try {
    if (!$loanSkip && $loanPlan) {
        // Dua tahap: kode lama & baru bisa saling tabrakan di tengah jalan, sedangkan
        // loan_code UNIQUE. Tahap 1 memarkir semua ke nilai sementara yang pasti bebas.
        $tmp = $pdo->prepare("UPDATE loans SET loan_code = ? WHERE id = ?");
        foreach ($loanPlan as $p) $tmp->execute(['TMP-' . $p['id'], $p['id']]);
        $fin = $pdo->prepare("UPDATE loans SET loan_code = ? WHERE id = ?");
        foreach ($loanPlan as $p) $fin->execute([$p['new'], $p['id']]);
        $note = count($loanPlan) . ' kode peminjaman diubah ke format APTIKA-YYYYMMDD-NNN.';
        mark($pdo, MARK_LOAN, $note);
        echo "  1. $note\n";
    }
    if (!$bmdSkip && ($nBmn > 0 || $nBar > 0)) {
        $a = $pdo->exec("UPDATE assets SET bmn_number = CONCAT('BMD-', SUBSTRING(bmn_number, 5)) WHERE bmn_number LIKE 'BMN-%'");
        $b = $pdo->exec("UPDATE assets SET barcode    = CONCAT('BMD-', SUBSTRING(barcode, 5))    WHERE barcode    LIKE 'BMN-%'");
        $note = "bmn_number: $a baris, barcode: $b baris diganti BMN- -> BMD-.";
        mark($pdo, MARK_BMD, $note);
        echo "  2. $note\n";
    }
    $pdo->commit();
    echo "\nSelesai. Penanda dicatat di audit_logs sehingga skrip ini tidak jalan dua kali.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "GAGAL, semua perubahan dibatalkan: " . $e->getMessage() . "\n");
    exit(1);
}
