<?php
declare(strict_types=1);
// Loan / Booking controllers

function loan_index(): void {
    Auth::requireLogin();
    $role = Auth::role();
    $uid = Auth::id();
    $pdo = db();

    // Status difilter langsung di browser (live filter), jadi di sini kita ambil semua
    // status milik role terkait sekaligus. Parameter ?status= tetap didukung untuk
    // menentukan pill mana yang aktif saat halaman pertama dibuka.
    $status = $_GET['status'] ?? '';
    $params = [];
    $where = ['l.deleted_at IS NULL'];
    if (in_array($role, ['pemohon', 'inventory_staff'], true)) {
        $where[] = 'l.requester_id = ?';
        $params[] = $uid;
    }
    // asset_names: gabungan nama alat per peminjaman, agar bisa dicari by alat di daftar.
    $sql = "SELECT l.*, u.name AS requester_name,
                   (SELECT GROUP_CONCAT(a2.name SEPARATOR ' ') FROM loan_items li2 JOIN assets a2 ON a2.id = li2.asset_id WHERE li2.loan_id = l.id) AS asset_names
            FROM loans l JOIN users u ON u.id = l.requester_id
            WHERE " . implode(' AND ', $where) . " ORDER BY l.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $loans = $stmt->fetchAll();

    layout('main', 'loans/index', [
        'title' => 'Peminjaman',
        'loans' => $loans,
        'currentStatus' => $status,
        'currentPath' => '/loans',
    ]);
}

function loan_create_get(): void {
    Auth::requireRole('pemohon', 'inventory_staff', 'it_staff_pembantu', 'admin');
    $pdo = db();
    $categories = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
    $assets = $pdo->query("SELECT a.*, c.name AS category_name FROM assets a LEFT JOIN categories c ON c.id = a.category_id WHERE a.status != 'Retired' AND a.deleted_at IS NULL ORDER BY a.name")->fetchAll();
    $packages = $pdo->query("SELECT p.*, GROUP_CONCAT(a.name SEPARATOR ', ') AS items FROM packages p LEFT JOIN package_items pi ON pi.package_id = p.id LEFT JOIN assets a ON a.id = pi.asset_id WHERE p.is_active = 1 AND p.deleted_at IS NULL GROUP BY p.id ORDER BY p.name")->fetchAll();
    // Personel yang dapat dilibatkan hanya user ber-role IT Staff.
    $itStaff = $pdo->query("SELECT id, name, unit_kerja FROM users WHERE role = 'inventory_staff' AND is_active = 1 AND deleted_at IS NULL ORDER BY name")->fetchAll();

    layout('main', 'loans/create', [
        'title' => 'Ajukan Peminjaman',
        'categories' => $categories,
        'assets' => $assets,
        'packages' => $packages,
        'itStaff' => $itStaff,
        'currentPath' => '/loans',
    ]);
}

function loan_create_post(): void {
    Auth::requireRole('pemohon', 'inventory_staff', 'it_staff_pembantu', 'admin');
    Auth::verifyCsrf();

    $eventName = trim($_POST['event_name'] ?? '');
    $location  = trim($_POST['event_location'] ?? '');
    $start     = $_POST['start_date'] ?? '';
    $end       = $_POST['end_date'] ?? '';
    $purpose   = trim($_POST['purpose'] ?? '');
    $assetIds  = array_map('intval', $_POST['asset_ids'] ?? []);
    $packageIds= array_map('intval', $_POST['package_ids'] ?? []);
    $participantIds = array_values(array_unique(array_filter(array_map('intval', $_POST['participant_ids'] ?? []))));

    if (!$eventName || !$start || !$end || (!$assetIds && !$packageIds)) {
        flash('error', 'Lengkapi nama acara, tanggal, dan pilih minimal 1 alat / paket.');
        redirect('/loans/create');
    }
    if ($start > $end) { flash('error', 'Tanggal selesai harus setelah tanggal mulai.'); redirect('/loans/create'); }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Resolve packages to asset ids
        $allAssetIds = $assetIds;
        $packageMap = []; // asset_id => package_id
        if ($packageIds) {
            $in = implode(',', array_fill(0, count($packageIds), '?'));
            $stmt = $pdo->prepare("SELECT package_id, asset_id FROM package_items WHERE package_id IN ($in)");
            $stmt->execute($packageIds);
            foreach ($stmt->fetchAll() as $row) {
                $allAssetIds[] = (int)$row['asset_id'];
                $packageMap[(int)$row['asset_id']] = (int)$row['package_id'];
            }
        }
        $allAssetIds = array_values(array_unique($allAssetIds));
        if (!$allAssetIds) throw new RuntimeException('Tidak ada alat yang dipilih.');

        // Conflict check: any asset already Booked or CheckedOut in overlapping loan?
        $in = implode(',', array_fill(0, count($allAssetIds), '?'));
        $params = array_merge($allAssetIds, [$end, $start]);
        $sqlConf = "SELECT DISTINCT li.asset_id, a.name FROM loan_items li
                    JOIN loans l ON l.id = li.loan_id
                    JOIN assets a ON a.id = li.asset_id
                    WHERE li.asset_id IN ($in)
                      AND l.status IN ('Pending','Approved','CheckedOut')
                      AND l.start_date <= ? AND l.end_date >= ?";
        $st = $pdo->prepare($sqlConf);
        $st->execute($params);
        $conflicts = $st->fetchAll();
        if ($conflicts) {
            $names = array_column($conflicts, 'name');
            throw new RuntimeException('Alat sudah dipesan pada rentang tanggal tersebut: ' . implode(', ', $names));
        }

        // Also block Damaged assets
        $stD = $pdo->prepare("SELECT id, name FROM assets WHERE id IN ($in) AND status IN ('Damaged','Retired')");
        $stD->execute($allAssetIds);
        $bad = $stD->fetchAll();
        if ($bad) throw new RuntimeException('Alat tidak dapat dipinjam (rusak / dihapus): ' . implode(', ', array_column($bad,'name')));

        $code = generate_code('LN', 'loans', 'loan_code');
        $loanUuid = generate_uuid();
        $ins = $pdo->prepare("INSERT INTO loans (uuid, loan_code, requester_id, event_name, event_location, start_date, end_date, purpose, status, created_by) VALUES (?,?,?,?,?,?,?,?,'Pending',?)");
        $ins->execute([$loanUuid, $code, Auth::id(), $eventName, $location, $start, $end, $purpose, Auth::id()]);
        $loanId = (int) $pdo->lastInsertId();

        $itemIns = $pdo->prepare("INSERT INTO loan_items (loan_id, asset_id, package_id, item_status) VALUES (?,?,?, 'Reserved')");
        $upA = $pdo->prepare("UPDATE assets SET status = 'Booked' WHERE id = ? AND status = 'Available'");
        foreach ($allAssetIds as $aid) {
            $itemIns->execute([$loanId, $aid, $packageMap[$aid] ?? null]);
            $upA->execute([$aid]);
        }

        // Personel yang dilibatkan — hanya user ber-role IT Staff yang valid.
        if ($participantIds) {
            $in = implode(',', array_fill(0, count($participantIds), '?'));
            $vst = $pdo->prepare("SELECT id FROM users WHERE id IN ($in) AND role = 'inventory_staff' AND is_active = 1 AND deleted_at IS NULL");
            $vst->execute($participantIds);
            $validIds = array_map('intval', $vst->fetchAll(PDO::FETCH_COLUMN));
            $pIns = $pdo->prepare("INSERT INTO loan_participants (loan_id, user_id) VALUES (?, ?)");
            foreach ($validIds as $pid) { $pIns->execute([$loanId, $pid]); }
        }
        $pdo->commit();

        log_audit('loan.create', 'loan', $loanId, ['code' => $code, 'items' => count($allAssetIds)]);

        // Notify supervisors
        Notification::pushToRole('supervisor', 'Pengajuan Peminjaman Baru',
            "Pengajuan $code untuk acara \"$eventName\" ($start s/d $end) menunggu persetujuan Anda.",
            "/loans/$loanUuid");

        flash('success', "Peminjaman $code berhasil diajukan. Menunggu persetujuan atasan.");
        redirect("/loans/$loanUuid");
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', $e->getMessage());
        redirect('/loans/create');
    }
}

function loan_show(string $uuid): void {
    Auth::requireLogin();
    $pdo = db();
    $id = uuid_to_id_or_404('loans', $uuid);
    $stmt = $pdo->prepare("SELECT l.*, u.name AS requester_name, u.unit_kerja AS requester_unit, s.name AS supervisor_name,
                           cu.name AS created_by_name, uu.name AS updated_by_name, ru.name AS restored_by_name
                           FROM loans l JOIN users u ON u.id = l.requester_id
                           LEFT JOIN users s ON s.id = l.supervisor_id
                           LEFT JOIN users cu ON cu.id = l.created_by
                           LEFT JOIN users uu ON uu.id = l.updated_by
                           LEFT JOIN users ru ON ru.id = l.restored_by
                           WHERE l.id = ? AND l.deleted_at IS NULL");
    $stmt->execute([$id]);
    $loan = $stmt->fetch();
    if (!$loan) { http_response_code(404); include APP_ROOT . '/views/errors/404.php'; return; }

    // Role authorization: pemohon & IT Staff hanya bisa melihat peminjaman miliknya
    if (role_is_requester() && (int)$loan['requester_id'] !== Auth::id()) {
        http_response_code(403); include APP_ROOT . '/views/errors/403.php'; return;
    }

    $items = $pdo->prepare("SELECT li.*, a.name AS asset_name, a.bmn_number, a.asset_code, a.barcode, a.photo, a.purchase_price, a.current_value, p.name AS package_name
                            FROM loan_items li JOIN assets a ON a.id = li.asset_id
                            LEFT JOIN packages p ON p.id = li.package_id
                            WHERE li.loan_id = ? ORDER BY li.id");
    $items->execute([$id]);
    $items = $items->fetchAll();

    $partStmt = $pdo->prepare("SELECT u.name, u.unit_kerja FROM loan_participants lp JOIN users u ON u.id = lp.user_id WHERE lp.loan_id = ? ORDER BY u.name");
    $partStmt->execute([$id]);
    $participants = $partStmt->fetchAll();

    layout('main', 'loans/show', [
        'title' => 'Detail Peminjaman ' . $loan['loan_code'],
        'loan' => $loan,
        'items' => $items,
        'participants' => $participants,
        'currentPath' => '/loans',
    ]);
}

function loan_cancel(string $uuid): void {
    Auth::requireLogin();
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('loans', $uuid);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ?");
    $stmt->execute([$id]);
    $loan = $stmt->fetch();
    if (!$loan) { flash('error', 'Peminjaman tidak ditemukan.'); redirect('/loans'); }
    if (Auth::role() !== 'admin' && (int)$loan['requester_id'] !== Auth::id()) {
        flash('error', 'Tidak berwenang.'); redirect('/loans');
    }
    if (!in_array($loan['status'], ['Pending','Approved'])) {
        flash('error', 'Hanya peminjaman berstatus Pending/Approved yang dapat dibatalkan.');
        redirect("/loans/$uuid");
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE loans SET status='Cancelled', updated_by=? WHERE id = ?")->execute([Auth::id(), $id]);
        // Release assets that are still Booked (not yet CheckedOut)
        $pdo->prepare("UPDATE assets SET status = 'Available' WHERE id IN (SELECT asset_id FROM loan_items WHERE loan_id = ? AND item_status IN ('Reserved'))")->execute([$id]);
        $pdo->commit();
        log_audit('loan.cancel', 'loan', $id);
        Notification::pushToRole('supervisor', 'Peminjaman Dibatalkan', "Peminjaman {$loan['loan_code']} dibatalkan oleh pemohon.", "/loans/$uuid");
        flash('success', 'Peminjaman berhasil dibatalkan.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Gagal membatalkan: ' . $e->getMessage());
    }
    redirect("/loans/$uuid");
}

function loan_delete(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('loans', $uuid);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ?");
    $stmt->execute([$id]);
    $loan = $stmt->fetch();
    if (!$loan) { flash('error', 'Peminjaman tidak ditemukan.'); redirect('/loans'); }

    // Only allow deleting finished/historical records — never delete a loan that's
    // still in play (Pending/Approved/CheckedOut), since that would silently strand
    // its assets/booking state.
    $finalStatuses = ['Completed', 'Rejected', 'Cancelled', 'Returned'];
    if (!in_array($loan['status'], $finalStatuses, true)) {
        flash('error', 'Hanya riwayat peminjaman yang sudah selesai/ditolak/dibatalkan yang dapat dihapus.');
        redirect("/loans/$uuid");
    }

    try {
        // Soft delete — baris loan_items tetap ada (diakses lewat loan_id yang sekarang
        // "tersembunyi"), riwayatnya masih bisa dicek & dipulihkan lewat Riwayat Terhapus.
        soft_delete('loans', $id);
        log_audit('loan.delete', 'loan', $id, ['code' => $loan['loan_code'], 'status' => $loan['status']]);
        flash('success', "Riwayat peminjaman {$loan['loan_code']} berhasil dihapus.");
    } catch (Throwable $e) {
        flash('error', 'Gagal menghapus riwayat: ' . $e->getMessage());
    }
    redirect('/loans');
}

function loan_delete_all(): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $pdo = db();
    $finalStatuses = ['Completed', 'Rejected', 'Cancelled', 'Returned'];
    $in = implode(',', array_fill(0, count($finalStatuses), '?'));

    try {
        $count = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE status IN ($in) AND deleted_at IS NULL");
        $count->execute($finalStatuses);
        $total = (int) $count->fetchColumn();

        if ($total === 0) {
            flash('error', 'Tidak ada riwayat peminjaman yang bisa dihapus.');
            redirect('/loans');
        }

        // Soft delete massal — tetap bisa dipulihkan lewat Riwayat Terhapus.
        $pdo->prepare("UPDATE loans SET deleted_at = NOW(), deleted_by = ? WHERE status IN ($in) AND deleted_at IS NULL")
            ->execute(array_merge([Auth::id()], $finalStatuses));
        log_audit('loan.delete_all', 'loan', null, ['count' => $total, 'statuses' => $finalStatuses]);
        flash('success', "$total riwayat peminjaman berhasil dihapus.");
    } catch (Throwable $e) {
        flash('error', 'Gagal menghapus riwayat: ' . $e->getMessage());
    }
    redirect('/loans');
}

// ============ APPROVALS =============

function approval_index(): void {
    Auth::requireRole('supervisor', 'admin');
    $pdo = db();
    $pending = $pdo->query("SELECT l.*, u.name AS requester_name, u.unit_kerja AS requester_unit
                            FROM loans l JOIN users u ON u.id = l.requester_id
                            WHERE l.status = 'Pending' AND l.deleted_at IS NULL ORDER BY l.created_at ASC")->fetchAll();
    $decided = $pdo->query("SELECT l.*, u.name AS requester_name, s.name AS supervisor_name
                            FROM loans l JOIN users u ON u.id = l.requester_id
                            LEFT JOIN users s ON s.id = l.supervisor_id
                            WHERE l.status IN ('Approved','Rejected','CheckedOut','Returned','Completed') AND l.deleted_at IS NULL
                            ORDER BY l.approved_at DESC LIMIT 20")->fetchAll();
    layout('main', 'loans/approvals', [
        'title' => 'Approval Peminjaman',
        'pending' => $pending,
        'decided' => $decided,
        'currentPath' => '/approvals',
    ]);
}

function loan_approve(string $uuid): void {
    Auth::requireRole('supervisor', 'admin');
    Auth::verifyCsrf();
    _loan_decide(uuid_to_id_or_404('loans', $uuid), 'Approved');
}
function loan_reject(string $uuid): void {
    Auth::requireRole('supervisor', 'admin');
    Auth::verifyCsrf();
    _loan_decide(uuid_to_id_or_404('loans', $uuid), 'Rejected');
}
function _loan_decide(int $id, string $decision): void {
    $pdo = db();
    $note = trim($_POST['note'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ?");
    $stmt->execute([$id]);
    $loan = $stmt->fetch();
    if (!$loan) { flash('error', 'Peminjaman tidak ditemukan.'); redirect('/approvals'); }
    if ($loan['status'] !== 'Pending') { flash('error', 'Peminjaman sudah tidak dalam status Pending.'); redirect("/loans/{$loan['uuid']}"); }

    $pdo->beginTransaction();
    try {
        $u = $pdo->prepare("UPDATE loans SET status = ?, supervisor_id = ?, approval_note = ?, approved_at = NOW(), updated_by = ? WHERE id = ?");
        $u->execute([$decision, Auth::id(), $note, Auth::id(), $id]);

        if ($decision === 'Rejected') {
            // Release assets
            $pdo->prepare("UPDATE assets SET status='Available' WHERE id IN (SELECT asset_id FROM loan_items WHERE loan_id = ?)")->execute([$id]);
        }
        $pdo->commit();
        log_audit('loan.decision', 'loan', $id, ['decision' => $decision, 'note' => $note]);

        // Notify requester
        Notification::push((int)$loan['requester_id'],
            $decision === 'Approved' ? 'Peminjaman Anda Disetujui' : 'Peminjaman Anda Ditolak',
            "Peminjaman {$loan['loan_code']} telah $decision oleh Kepala Bagian." . ($note ? "\nCatatan: $note" : ''),
            "/loans/{$loan['uuid']}");
        if ($decision === 'Approved') {
            Notification::pushToRole('admin_gudang', 'Peminjaman Siap Diserahkan',
                "Peminjaman {$loan['loan_code']} ({$loan['event_name']}) sudah disetujui. Siapkan alat untuk diserahkan.",
                "/checkout/{$loan['uuid']}");
        }
        flash('success', "Peminjaman $decision.");
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Gagal: ' . $e->getMessage());
    }
    redirect("/loans/{$loan['uuid']}");
}
