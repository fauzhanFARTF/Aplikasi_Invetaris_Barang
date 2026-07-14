<?php
declare(strict_types=1);
// Alur penyerahan — Admin Gudang memindai barcode untuk menyerahkan aset

function checkout_index(): void {
    Auth::requireRole('admin_gudang', 'admin');
    $pdo = db();
    $today = date('Y-m-d');
    $loans = $pdo->prepare("SELECT l.*, u.name AS requester_name,
                            (SELECT COUNT(*) FROM loan_items li WHERE li.loan_id = l.id) AS total_items,
                            (SELECT COUNT(*) FROM loan_items li WHERE li.loan_id = l.id AND li.item_status = 'CheckedOut') AS out_items
                            FROM loans l JOIN users u ON u.id = l.requester_id
                            WHERE l.status IN ('Approved','CheckedOut') AND l.start_date <= ?
                            HAVING out_items < total_items
                            ORDER BY l.start_date ASC");
    $loans->execute([$today]);
    $loans = $loans->fetchAll();

    layout('main', 'checkout/index', [
        'title' => 'Penyerahan Alat',
        'loans' => $loans,
        'currentPath' => '/checkout',
    ]);
}

function checkout_scan_page(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin');
    $pdo = db();
    $id = uuid_to_id_or_404('loans', $uuid);
    $stmt = $pdo->prepare("SELECT l.*, u.name AS requester_name, u.unit_kerja FROM loans l JOIN users u ON u.id = l.requester_id WHERE l.id = ? AND l.deleted_at IS NULL");
    $stmt->execute([$id]);
    $loan = $stmt->fetch();
    if (!$loan) { http_response_code(404); include APP_ROOT.'/views/errors/404.php'; return; }
    if (!in_array($loan['status'], ['Approved','CheckedOut'])) {
        flash('error', 'Peminjaman tidak dalam status yang bisa diserahkan.');
        redirect('/checkout');
    }
    $items = $pdo->prepare("SELECT li.*, a.name AS asset_name, a.bmn_number, a.asset_code, a.barcode, a.photo FROM loan_items li JOIN assets a ON a.id = li.asset_id WHERE li.loan_id = ? ORDER BY li.id");
    $items->execute([$id]);
    $items = $items->fetchAll();

    layout('main', 'checkout/scan', [
        'title' => 'Scan Penyerahan — ' . $loan['loan_code'],
        'loan' => $loan,
        'items' => $items,
        'currentPath' => '/checkout',
    ]);
}

function checkout_scan_submit(): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    header('Content-Type: application/json');
    $loanId  = (int) ($_POST['loan_id'] ?? 0);
    $barcode = trim($_POST['barcode'] ?? '');
    if (!$loanId || !$barcode) json_response(['ok' => false, 'message' => 'Data tidak lengkap.'], 400);

    $pdo = db();
    $stmt = $pdo->prepare("SELECT li.*, a.name AS asset_name, a.bmn_number, a.status AS asset_status
                            FROM loan_items li JOIN assets a ON a.id = li.asset_id
                            WHERE li.loan_id = ? AND a.barcode = ?");
    $stmt->execute([$loanId, $barcode]);
    $item = $stmt->fetch();
    if (!$item) json_response(['ok' => false, 'message' => "Barcode $barcode tidak ada di peminjaman ini."], 404);
    if ($item['item_status'] === 'CheckedOut') json_response(['ok' => false, 'message' => "Alat {$item['asset_name']} sudah diserahkan."], 409);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE loan_items SET item_status='CheckedOut', checkout_by=?, checkout_at=NOW() WHERE id = ?")->execute([Auth::id(), $item['id']]);
        $pdo->prepare("UPDATE assets SET status='CheckedOut' WHERE id = ?")->execute([$item['asset_id']]);
        // Loan baru jadi 'CheckedOut' (Dipinjam) kalau SEMUA item sudah diserahkan.
        // Kalau masih sebagian (mis. 2/3), status peminjaman tetap 'Approved' (Disetujui).
        $remaining = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ? AND item_status != 'CheckedOut'");
        $remaining->execute([$loanId]);
        if ((int) $remaining->fetchColumn() === 0) {
            $pdo->prepare("UPDATE loans SET status='CheckedOut', checkout_at=NOW(), updated_by=? WHERE id = ? AND status='Approved'")->execute([Auth::id(), $loanId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['ok' => false, 'message' => $e->getMessage()], 500);
    }

    log_audit('loan.checkout_item', 'loan_item', $item['id'], ['bmn' => $item['bmn_number']]);
    json_response(['ok' => true, 'message' => "Berhasil diserahkan: {$item['asset_name']}", 'asset_name' => $item['asset_name'], 'bmn' => $item['bmn_number']]);
}

function checkout_finalize(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('loans', $uuid);
    $pdo = db();
    $notOut = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ? AND item_status != 'CheckedOut'");
    $notOut->execute([$id]);
    if ((int)$notOut->fetchColumn() > 0) {
        flash('error', 'Masih ada alat yang belum di-scan untuk penyerahan.');
        redirect("/checkout/$uuid");
    }
    $pdo->prepare("UPDATE loans SET status='CheckedOut', checkout_at = COALESCE(checkout_at, NOW()), updated_by=? WHERE id = ?")->execute([Auth::id(), $id]);
    log_audit('loan.checkout_finalize', 'loan', $id);
    // Notify requester
    $r = $pdo->prepare("SELECT requester_id, loan_code FROM loans WHERE id = ?");
    $r->execute([$id]);
    $row = $r->fetch();
    if ($row) {
        Notification::push((int)$row['requester_id'], 'Alat Telah Diserahkan', "Semua alat pada peminjaman {$row['loan_code']} telah diserahkan kepada Anda. Mohon dijaga & dikembalikan tepat waktu.", "/loans/$uuid");
    }
    flash('success', 'Penyerahan selesai.');
    redirect("/loans/$uuid");
}
