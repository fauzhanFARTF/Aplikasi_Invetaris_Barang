<?php
declare(strict_types=1);
// Alur penyerahan — Admin Gudang memindai barcode untuk menyerahkan aset

function checkout_index(): void {
    Auth::requireRole('admin_gudang', 'admin');
    $pdo = db();
    $today = date('Y-m-d');
    $loans = $pdo->prepare("SELECT l.*, u.name AS requester_name,
                            (SELECT COUNT(*) FROM loan_items li WHERE li.loan_id = l.id) AS total_items,
                            (SELECT COUNT(*) FROM loan_items li WHERE li.loan_id = l.id AND li.item_status IN ('CheckedOut','AtOpd')) AS out_items
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
    // Terima stiker lama (BMN-) maupun baru (BMD-) — lihat barcode_candidates().
    $cand = barcode_candidates($barcode);
    $in   = implode(',', array_fill(0, count($cand), '?'));
    $stmt = $pdo->prepare("SELECT li.*, a.name AS asset_name, a.bmn_number, a.status AS asset_status
                            FROM loan_items li JOIN assets a ON a.id = li.asset_id
                            WHERE li.loan_id = ? AND a.barcode IN ($in)");
    $stmt->execute(array_merge([$loanId], $cand));
    $item = $stmt->fetch();
    if (!$item) json_response(['ok' => false, 'message' => "Barcode $barcode tidak ada di peminjaman ini."], 404);
    if (in_array($item['item_status'], ['CheckedOut', 'AtOpd'], true)) json_response(['ok' => false, 'message' => "Alat {$item['asset_name']} sudah diserahkan."], 409);

    // Keputusan kembali PER BARANG: barang OPD yang tidak dikembalikan (will_return=0)
    // diserahkan permanen -> item & alat berstatus 'Di OPD' (AtOpd). Barang yang
    // dikembalikan tetap 'CheckedOut' (masuk Pengembalian seperti biasa).
    $lr = $pdo->prepare("SELECT loan_type FROM loans WHERE id = ?");
    $lr->execute([$loanId]);
    $loanRow = $lr->fetch();
    $isOpd = $loanRow && ($loanRow['loan_type'] ?? 'event') === 'opd';
    $atOpd = $isOpd && (int)($item['will_return'] ?? 1) === 0;
    $itemOut  = $atOpd ? 'AtOpd' : 'CheckedOut';
    $assetOut = $atOpd ? 'AtOpd' : 'CheckedOut';

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE loan_items SET item_status=?, checkout_by=?, checkout_at=NOW() WHERE id = ?")->execute([$itemOut, Auth::id(), $item['id']]);
        $pdo->prepare("UPDATE assets SET status=? WHERE id = ?")->execute([$assetOut, $item['asset_id']]);
        // Peminjaman naik status kalau SEMUA item sudah keluar gudang (tak ada lagi
        // yang 'Reserved'). Bila ada barang yang dikembalikan (masih 'CheckedOut'),
        // status jadi 'Dipinjam'; bila SEMUA barang tetap di OPD ('AtOpd'), langsung
        // Selesai (tidak ada yang ditunggu kembali).
        $remaining = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ? AND item_status = 'Reserved'");
        $remaining->execute([$loanId]);
        if ((int) $remaining->fetchColumn() === 0) {
            $anyOut = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ? AND item_status = 'CheckedOut'");
            $anyOut->execute([$loanId]);
            if ((int) $anyOut->fetchColumn() > 0) {
                $pdo->prepare("UPDATE loans SET status='CheckedOut', checkout_at=NOW(), updated_by=? WHERE id = ? AND status='Approved'")->execute([Auth::id(), $loanId]);
            } else {
                $pdo->prepare("UPDATE loans SET status='Completed', checkout_at=COALESCE(checkout_at,NOW()), updated_by=? WHERE id = ? AND status IN ('Approved','CheckedOut')")->execute([Auth::id(), $loanId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['ok' => false, 'message' => $e->getMessage()], 500);
    }

    log_audit('loan.checkout_item', 'loan_item', $item['id'], ['bmn' => $item['bmn_number'], 'at_opd' => $atOpd]);
    $msg = $atOpd ? "Diserahkan ke OPD (permanen): {$item['asset_name']}" : "Berhasil diserahkan: {$item['asset_name']}";
    json_response(['ok' => true, 'message' => $msg, 'asset_name' => $item['asset_name'], 'bmn' => $item['bmn_number'], 'at_opd' => $atOpd]);
}

function checkout_finalize(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('loans', $uuid);
    $pdo = db();
    $notOut = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ? AND item_status = 'Reserved'");
    $notOut->execute([$id]);
    if ((int)$notOut->fetchColumn() > 0) {
        flash('error', 'Masih ada alat yang belum di-scan untuk penyerahan.');
        redirect("/checkout/$uuid");
    }
    // Bila ada barang yang dikembalikan (masih 'CheckedOut') -> 'Dipinjam'.
    // Bila SEMUA barang tetap di OPD ('AtOpd') -> langsung Selesai.
    $anyOut = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ? AND item_status = 'CheckedOut'");
    $anyOut->execute([$id]);
    $finalStatus = (int)$anyOut->fetchColumn() > 0 ? 'CheckedOut' : 'Completed';
    $pdo->prepare("UPDATE loans SET status=?, checkout_at = COALESCE(checkout_at, NOW()), updated_by=? WHERE id = ?")->execute([$finalStatus, Auth::id(), $id]);
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
