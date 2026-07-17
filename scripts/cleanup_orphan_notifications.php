<?php
declare(strict_types=1);
// ==============================================================
//  SIMANTAP — One-time cleanup for orphaned notifications
//
//  Fixes notifications created BEFORE the fix in LoanController.php
//  (loan_delete / loan_delete_all), whose `link` still points to a
//  loan/checkout page that has since been deleted from the
//  database — these are the notifications that lead to a 404 when
//  clicked "Buka" in /notifications.
//
//  Run once from the command line, from the project root:
//      php scripts/cleanup_orphan_notifications.php
//
//  Safe to re-run any time — it only ever deletes notifications
//  whose target no longer exists.
// ==============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pdo = db();

$deleted = 0;

// 1. Notifications linking to /loans/{id} where that loan no longer exists.
$stmt = $pdo->query("
    SELECT n.id FROM notifications n
    WHERE n.link REGEXP '^/loans/[0-9]+$'
      AND NOT EXISTS (
          SELECT 1 FROM loans l WHERE l.id = CAST(SUBSTRING(n.link, 8) AS UNSIGNED)
      )
");
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM notifications WHERE id IN ($ph)")->execute($ids);
    $deleted += count($ids);
    echo count($ids) . " notifikasi basi ke /loans/{id} dihapus.\n";
}

// 2. Notifications linking to /checkout/{id} where that loan no longer exists.
$stmt = $pdo->query("
    SELECT n.id FROM notifications n
    WHERE n.link REGEXP '^/checkout/[0-9]+$'
      AND NOT EXISTS (
          SELECT 1 FROM loans l WHERE l.id = CAST(SUBSTRING(n.link, 11) AS UNSIGNED)
      )
");
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM notifications WHERE id IN ($ph)")->execute($ids);
    $deleted += count($ids);
    echo count($ids) . " notifikasi basi ke /checkout/{id} dihapus.\n";
}

if ($deleted === 0) {
    echo "Tidak ada notifikasi basi yang ditemukan. Semua link masih valid.\n";
} else {
    echo "Selesai. Total $deleted notifikasi basi dihapus.\n";
}
