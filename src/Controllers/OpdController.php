<?php
declare(strict_types=1);
// Barang di OPD — daftar barang yang sedang berada di OPD (keluar lewat peminjaman
// "Kebutuhan Jaringan"), beserta aksi menariknya kembali bila rusak. Barang yang
// "Tetap di OPD" (AtOpd) statusnya masih menunggu: tidak masuk Pengembalian biasa,
// dikembalikan hanya bila rusak lewat halaman ini.

function opd_items_index(): void {
    Auth::requireLogin();
    // Personel Luar (peminjam pribadi) tidak mengelola aset OPD.
    if (is_personal_borrower()) { http_response_code(403); include APP_ROOT . '/views/errors/403.php'; return; }
    $pdo = db();

    // Barang OPD yang sedang keluar: baik yang dikembalikan berjadwal (CheckedOut)
    // maupun yang tetap di OPD/menunggu (AtOpd). Kolom sesuai permintaan: OPD, nama,
    // kode, model/SN, tanggal pemasangan (checkout_at), penanggung jawab, personel.
    $rows = $pdo->query("SELECT l.uuid AS loan_uuid, l.loan_code, l.event_name AS opd_name, l.checkout_at,
                                u.name AS requester_name,
                                li.id AS item_id, li.item_status, li.will_return, li.expected_return_date,
                                a.name AS asset_name, a.asset_code, a.bmn_number, a.brand, a.model, a.serial_number,
                                (SELECT GROUP_CONCAT(pu.name ORDER BY pu.name SEPARATOR ', ')
                                   FROM loan_participants lp JOIN users pu ON pu.id = lp.user_id
                                  WHERE lp.loan_id = l.id) AS personnel
                         FROM loan_items li
                         JOIN loans l ON l.id = li.loan_id AND l.deleted_at IS NULL
                         JOIN assets a ON a.id = li.asset_id
                         JOIN users u ON u.id = l.requester_id
                         WHERE l.loan_type = 'opd' AND li.item_status IN ('CheckedOut','AtOpd')
                         ORDER BY (li.item_status = 'AtOpd') DESC, l.checkout_at DESC, a.name")->fetchAll();

    layout('main', 'opd/items', [
        'title' => 'Barang di OPD',
        'rows' => $rows,
        'currentPath' => '/opd-items',
    ]);
}

/** Tarik / kembalikan sebuah barang yang Tetap di OPD (mis. karena rusak). */
function opd_item_return(string $itemId): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $itemId = (int) $itemId;
    $pdo = db();

    $stmt = $pdo->prepare("SELECT li.*, a.name AS asset_name, a.bmn_number, a.purchase_price, a.current_value,
                                  l.id AS loan_id, l.uuid AS loan_uuid, l.loan_code
                           FROM loan_items li
                           JOIN assets a ON a.id = li.asset_id
                           JOIN loans l ON l.id = li.loan_id AND l.deleted_at IS NULL
                           WHERE li.id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) { flash('error', 'Barang tidak ditemukan.'); redirect('/opd-items'); }
    // Hanya barang yang benar-benar Tetap di OPD (AtOpd) yang ditarik lewat sini.
    if ($item['item_status'] !== 'AtOpd') {
        flash('error', 'Barang ini bukan berstatus Di OPD, tidak dapat ditarik dari halaman ini.');
        redirect('/opd-items');
    }

    $condition = $_POST['condition'] ?? 'Damaged';
    $note = trim($_POST['note'] ?? '');
    if (!in_array($condition, ['Good','Damaged','Lost'], true)) { flash('error', 'Kondisi tidak valid.'); redirect('/opd-items'); }
    if ($condition === 'Damaged' && !$note) { flash('error', 'Keterangan kerusakan wajib diisi.'); redirect('/opd-items'); }
    if ($condition === 'Lost' && !$note)    { flash('error', 'Keterangan wajib diisi untuk kondisi Hilang.'); redirect('/opd-items'); }

    $loanId = (int) $item['loan_id'];
    $pdo->beginTransaction();
    try {
        $itemStatus  = $condition === 'Good' ? 'ReturnedGood' : ($condition === 'Damaged' ? 'ReturnedDamaged' : 'ReturnedLost');
        $assetStatus = $condition === 'Good' ? 'Available'    : ($condition === 'Damaged' ? 'Damaged'         : 'Lost');
        $pdo->prepare("UPDATE loan_items SET item_status=?, checkin_by=?, checkin_at=NOW(), return_condition=?, damage_note=? WHERE id=?")
            ->execute([$itemStatus, Auth::id(), $condition, $note ?: null, $itemId]);
        $pdo->prepare("UPDATE assets SET status=? WHERE id=?")->execute([$assetStatus, $item['asset_id']]);

        if ($condition === 'Damaged') {
            $code = generate_code('RP', 'repairs', 'repair_code');
            $pdo->prepare("INSERT INTO repairs (uuid, repair_code, asset_id, loan_item_id, complaint, status, created_by) VALUES (?,?,?,?,?, 'Open', ?)")
                ->execute([generate_uuid(), $code, $item['asset_id'], $itemId, $note, Auth::id()]);
            $pdo->prepare("UPDATE loan_items SET item_status='InRepair' WHERE id=?")->execute([$itemId]);
        }

        // Loan Selesai bila tak ada lagi barang yang menggantung (Reserved/CheckedOut/AtOpd).
        $open = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ? AND item_status IN ('Reserved','CheckedOut','AtOpd')");
        $open->execute([$loanId]);
        if ((int)$open->fetchColumn() === 0) {
            $pdo->prepare("UPDATE loans SET status='Completed', checkin_at=COALESCE(checkin_at,NOW()), updated_by=? WHERE id=?")->execute([Auth::id(), $loanId]);
        }
        $pdo->commit();
        log_audit('opd.item_return', 'loan_item', $itemId, ['condition' => $condition, 'note' => $note]);

        if ($condition === 'Lost') {
            $msg = "{$item['asset_name']} ({$item['bmn_number']}) dari OPD dilaporkan hilang.";
            Notification::pushToRole('admin', 'Barang OPD Hilang', $msg, '/inventory');
            Notification::pushToRole('admin_gudang', 'Barang OPD Hilang', $msg, '/inventory');
        }
        $label = $condition === 'Good' ? 'ditarik (kondisi baik)' : ($condition === 'Damaged' ? 'ditarik karena rusak dan masuk perbaikan' : 'dilaporkan hilang');
        flash('success', "{$item['asset_name']} $label.");
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Gagal memproses: ' . $e->getMessage());
    }
    redirect('/opd-items');
}
