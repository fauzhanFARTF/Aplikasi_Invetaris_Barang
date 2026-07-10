<?php
declare(strict_types=1);
// Alur pengembalian — pindai saat alat dikembalikan, tandai kondisi Baik / Rusak

function checkin_index(): void {
    Auth::requireRole('admin_gudang', 'admin');
    $pdo = db();
    $loans = $pdo->query("SELECT l.*, u.name AS requester_name,
                            (SELECT COUNT(*) FROM loan_items li WHERE li.loan_id = l.id) AS total_items,
                            (SELECT COUNT(*) FROM loan_items li WHERE li.loan_id = l.id AND li.item_status IN ('ReturnedGood','ReturnedDamaged','ReturnedLost','InRepair','Restored')) AS in_items
                            FROM loans l JOIN users u ON u.id = l.requester_id
                            WHERE l.status IN ('CheckedOut','Returned')
                            HAVING in_items < total_items
                            ORDER BY l.checkout_at DESC")->fetchAll();

    layout('main', 'checkin/index', [
        'title' => 'Pengembalian Alat',
        'loans' => $loans,
        'currentPath' => '/checkin',
    ]);
}

function checkin_scan_page(string $id): void {
    Auth::requireRole('admin_gudang', 'admin');
    $pdo = db();
    $id = (int) $id;
    $stmt = $pdo->prepare("SELECT l.*, u.name AS requester_name FROM loans l JOIN users u ON u.id = l.requester_id WHERE l.id = ?");
    $stmt->execute([$id]);
    $loan = $stmt->fetch();
    if (!$loan) { http_response_code(404); include APP_ROOT.'/views/errors/404.php'; return; }
    $items = $pdo->prepare("SELECT li.*, a.name AS asset_name, a.bmn_number, a.barcode, a.purchase_price, a.current_value FROM loan_items li JOIN assets a ON a.id = li.asset_id WHERE li.loan_id = ? ORDER BY li.id");
    $items->execute([$id]);
    $items = $items->fetchAll();

    layout('main', 'checkin/scan', [
        'title' => 'Pengembalian — ' . $loan['loan_code'],
        'loan' => $loan,
        'items' => $items,
        'currentPath' => '/checkin',
    ]);
}

function checkin_scan_submit(): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    header('Content-Type: application/json');
    $loanId    = (int) ($_POST['loan_id'] ?? 0);
    $barcode   = trim($_POST['barcode'] ?? '');
    $condition = $_POST['condition'] ?? 'Good';
    $note      = trim($_POST['damage_note'] ?? '');

    if (!$loanId || !$barcode) json_response(['ok' => false, 'message' => 'Data tidak lengkap.'], 400);
    if (!in_array($condition, ['Good','Damaged','Lost'])) json_response(['ok' => false, 'message' => 'Kondisi tidak valid.'], 400);
    if ($condition === 'Damaged' && !$note) json_response(['ok' => false, 'message' => 'Keluhan wajib diisi untuk kondisi Rusak.'], 400);
    if ($condition === 'Lost' && !$note) json_response(['ok' => false, 'message' => 'Keterangan wajib diisi untuk kondisi Hilang.'], 400);

    $pdo = db();
    $stmt = $pdo->prepare("SELECT li.*, a.name AS asset_name, a.bmn_number, a.purchase_price, a.current_value FROM loan_items li JOIN assets a ON a.id = li.asset_id
                            WHERE li.loan_id = ? AND a.barcode = ?");
    $stmt->execute([$loanId, $barcode]);
    $item = $stmt->fetch();
    if (!$item) json_response(['ok' => false, 'message' => "Barcode $barcode tidak ditemukan di peminjaman ini."], 404);
    if ($item['item_status'] !== 'CheckedOut') json_response(['ok' => false, 'message' => "Alat {$item['asset_name']} tidak berstatus CheckedOut."], 409);

    $pdo->beginTransaction();
    try {
        $itemStatus = $condition === 'Good' ? 'ReturnedGood' : ($condition === 'Damaged' ? 'ReturnedDamaged' : 'ReturnedLost');
        $assetStatus= $condition === 'Good' ? 'Available' : ($condition === 'Damaged' ? 'Damaged' : 'Lost');
        $pdo->prepare("UPDATE loan_items SET item_status=?, checkin_by=?, checkin_at=NOW(), return_condition=?, damage_note=? WHERE id=?")
            ->execute([$itemStatus, Auth::id(), $condition, $note ?: null, $item['id']]);
        $pdo->prepare("UPDATE assets SET status=? WHERE id=?")->execute([$assetStatus, $item['asset_id']]);

        $repairId = null;
        if ($condition === 'Damaged') {
            $code = generate_code('RP', 'repairs', 'repair_code');
            $r = $pdo->prepare("INSERT INTO repairs (repair_code, asset_id, loan_item_id, complaint, status) VALUES (?,?,?,?, 'Open')");
            $r->execute([$code, $item['asset_id'], $item['id'], $note]);
            $repairId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE loan_items SET item_status='InRepair' WHERE id=?")->execute([$item['id']]);
        }

        // Auto-sync loan status the moment items come back, instead of waiting for a
        // separate manual "Selesai Pengembalian" click — keeps the DIPINJAM/SELESAI badge
        // in the loan list truthful to the asset's actual condition at all times.
        $remaining = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ? AND item_status = 'CheckedOut'");
        $remaining->execute([$loanId]);
        if ((int)$remaining->fetchColumn() === 0) {
            $allGood = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ? AND item_status != 'ReturnedGood'");
            $allGood->execute([$loanId]);
            $newStatus = (int)$allGood->fetchColumn() === 0 ? 'Completed' : 'Returned';
            $pdo->prepare("UPDATE loans SET status=?, checkin_at = COALESCE(checkin_at, NOW()) WHERE id = ?")->execute([$newStatus, $loanId]);
        }

        $pdo->commit();
        log_audit('loan.checkin_item', 'loan_item', $item['id'], ['condition' => $condition, 'note' => $note]);

        $message = "Pengembalian $condition: {$item['asset_name']}";
        if ($condition === 'Lost') {
            $lostMsg = "{$item['asset_name']} ({$item['bmn_number']}) dilaporkan hilang saat pengembalian. Nilai perolehan: " . fmt_rupiah($item['purchase_price']) . ", nilai sekarang: " . fmt_rupiah($item['current_value']) . ".";
            Notification::pushToRole('admin', 'Alat Dilaporkan Hilang', $lostMsg, "/inventory");
            Notification::pushToRole('admin_gudang', 'Alat Dilaporkan Hilang', $lostMsg, "/inventory");
            $message = "Hilang: {$item['asset_name']} — nilai perolehan " . fmt_rupiah($item['purchase_price']) . ", nilai sekarang " . fmt_rupiah($item['current_value']);
        }

        json_response([
            'ok' => true,
            'message' => $message,
            'asset_name' => $item['asset_name'],
            'bmn' => $item['bmn_number'],
            'condition' => $condition,
            'repair_id' => $repairId,
            'purchase_price' => $item['purchase_price'],
            'current_value' => $item['current_value'],
            'purchase_price_fmt' => fmt_rupiah($item['purchase_price']),
            'current_value_fmt' => fmt_rupiah($item['current_value']),
        ]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['ok' => false, 'message' => $e->getMessage()], 500);
    }
}

function checkin_finalize(string $id): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $id = (int) $id;
    $pdo = db();
    $notIn = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ? AND item_status = 'CheckedOut'");
    $notIn->execute([$id]);
    if ((int)$notIn->fetchColumn() > 0) {
        flash('error', 'Masih ada alat yang belum di-scan untuk pengembalian.');
        redirect("/checkin/$id");
    }
    $pdo->prepare("UPDATE loans SET status='Returned', checkin_at = COALESCE(checkin_at, NOW()) WHERE id = ?")->execute([$id]);

    // Determine if all items ReturnedGood → Completed, else Returned (with pending repairs)
    $allGood = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ? AND item_status != 'ReturnedGood'");
    $allGood->execute([$id]);
    if ((int)$allGood->fetchColumn() === 0) {
        $pdo->prepare("UPDATE loans SET status='Completed' WHERE id = ?")->execute([$id]);
    }
    log_audit('loan.checkin_finalize', 'loan', $id);
    $r = $pdo->prepare("SELECT requester_id, loan_code FROM loans WHERE id = ?"); $r->execute([$id]); $row = $r->fetch();
    if ($row) Notification::push((int)$row['requester_id'], 'Alat Telah Diterima Kembali', "Peminjaman {$row['loan_code']} telah selesai diproses pengembaliannya.", "/loans/$id");

    flash('success', 'Pengembalian selesai.');
    redirect("/loans/$id");
}
