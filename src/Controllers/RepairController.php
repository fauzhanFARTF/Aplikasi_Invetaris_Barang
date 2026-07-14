<?php
declare(strict_types=1);
// Repair (SPK Fisik) flow

function repair_index(): void {
    Auth::requireRole('admin_gudang', 'admin');
    $pdo = db();
    $active = $pdo->query("SELECT r.*, a.name AS asset_name, a.bmn_number, a.asset_code
                           FROM repairs r JOIN assets a ON a.id = r.asset_id
                           WHERE r.status != 'Completed' AND r.deleted_at IS NULL ORDER BY r.created_at DESC")->fetchAll();
    $done   = $pdo->query("SELECT r.*, a.name AS asset_name, a.bmn_number, u.name AS completed_by_name
                           FROM repairs r JOIN assets a ON a.id = r.asset_id
                           LEFT JOIN users u ON u.id = r.completed_by
                           WHERE r.status = 'Completed' AND r.deleted_at IS NULL ORDER BY r.completed_at DESC LIMIT 20")->fetchAll();
    layout('main', 'repairs/index', [
        'title' => 'Perbaikan Alat',
        'active' => $active,
        'done' => $done,
        'currentPath' => '/repairs',
    ]);
}

function repair_show(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin');
    $id = uuid_to_id_or_404('repairs', $uuid);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT r.*, a.name AS asset_name, a.bmn_number, a.asset_code, a.brand, a.model, a.serial_number,
                            u.name AS completed_by_name, l.loan_code, req.name AS requester_name,
                            cu.name AS created_by_name, uu.name AS updated_by_name, ru.name AS restored_by_name
                            FROM repairs r JOIN assets a ON a.id = r.asset_id
                            LEFT JOIN loan_items li ON li.id = r.loan_item_id
                            LEFT JOIN loans l ON l.id = li.loan_id
                            LEFT JOIN users req ON req.id = l.requester_id
                            LEFT JOIN users u ON u.id = r.completed_by
                            LEFT JOIN users cu ON cu.id = r.created_by
                            LEFT JOIN users uu ON uu.id = r.updated_by
                            LEFT JOIN users ru ON ru.id = r.restored_by
                            WHERE r.id = ? AND r.deleted_at IS NULL");
    $stmt->execute([$id]);
    $repair = $stmt->fetch();
    if (!$repair) { http_response_code(404); include APP_ROOT.'/views/errors/404.php'; return; }
    layout('main', 'repairs/show', [
        'title' => 'Detail Perbaikan ' . $repair['repair_code'],
        'repair' => $repair,
        'currentPath' => '/repairs',
    ]);
}

function repair_print(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin');
    $id = uuid_to_id_or_404('repairs', $uuid);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT r.*, a.name AS asset_name, a.bmn_number, a.asset_code, a.brand, a.model, a.serial_number,
                            l.loan_code, req.name AS requester_name, req.unit_kerja AS requester_unit
                            FROM repairs r JOIN assets a ON a.id = r.asset_id
                            LEFT JOIN loan_items li ON li.id = r.loan_item_id
                            LEFT JOIN loans l ON l.id = li.loan_id
                            LEFT JOIN users req ON req.id = l.requester_id
                            WHERE r.id = ?");
    $stmt->execute([$id]);
    $repair = $stmt->fetch();
    if (!$repair) { http_response_code(404); include APP_ROOT.'/views/errors/404.php'; return; }

    // Mark as FormPrinted (idempotent)
    if ($repair['status'] === 'Open') {
        $pdo->prepare("UPDATE repairs SET status='FormPrinted', form_printed_at=NOW(), updated_by=? WHERE id=?")->execute([Auth::id(), $id]);
        log_audit('repair.print', 'repair', $id);
        $repair['status'] = 'FormPrinted';
        $repair['form_printed_at'] = date('Y-m-d H:i:s');
    }

    // Standalone print view (no layout)
    include APP_ROOT . '/views/repairs/form_print.php';
}

function repair_delete(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('repairs', $uuid);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM repairs WHERE id = ?");
    $stmt->execute([$id]);
    $repair = $stmt->fetch();
    if (!$repair) { flash('error', 'Data perbaikan tidak ditemukan.'); redirect('/repairs'); }

    // Only allow deleting finished repair history — active/in-progress repairs must
    // stay so the asset's repair trail isn't silently lost mid-process.
    if ($repair['status'] !== 'Completed') {
        flash('error', 'Hanya riwayat perbaikan yang sudah Selesai yang dapat dihapus.');
        redirect("/repairs/$uuid");
    }

    try {
        soft_delete('repairs', $id);
        log_audit('repair.delete', 'repair', $id, ['code' => $repair['repair_code']]);
        flash('success', "Riwayat perbaikan {$repair['repair_code']} berhasil dihapus.");
    } catch (Throwable $e) {
        flash('error', 'Gagal menghapus riwayat: ' . $e->getMessage());
    }
    redirect('/repairs');
}

function repair_delete_all(): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $pdo = db();
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM repairs WHERE status = 'Completed' AND deleted_at IS NULL")->fetchColumn();
        $total = (int) $count;
        if ($total === 0) {
            flash('error', 'Tidak ada riwayat perbaikan yang bisa dihapus.');
            redirect('/repairs');
        }
        $pdo->prepare("UPDATE repairs SET deleted_at = NOW(), deleted_by = ? WHERE status = 'Completed' AND deleted_at IS NULL")->execute([Auth::id()]);
        log_audit('repair.delete_all', 'repair', null, ['count' => $total]);
        flash('success', "$total riwayat perbaikan berhasil dihapus.");
    } catch (Throwable $e) {
        flash('error', 'Gagal menghapus riwayat: ' . $e->getMessage());
    }
    redirect('/repairs');
}
function repair_complete(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('repairs', $uuid);
    $techName = trim($_POST['technician_name'] ?? '');
    $action = trim($_POST['action_taken'] ?? '');
    if (!$techName || !$action) {
        flash('error', 'Nama teknisi dan tindakan perbaikan wajib diisi.');
        redirect("/repairs/$uuid");
    }
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM repairs WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $repair = $stmt->fetch();
    if (!$repair) { flash('error', 'Perbaikan tidak ditemukan.'); redirect('/repairs'); }
    if ($repair['status'] === 'Completed') { flash('error', 'Perbaikan sudah selesai.'); redirect("/repairs/$uuid"); }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE repairs SET status='Completed', technician_name=?, action_taken=?, completed_by=?, completed_at=NOW(), updated_by=? WHERE id=?")
            ->execute([$techName, $action, Auth::id(), Auth::id(), $id]);
        $pdo->prepare("UPDATE assets SET status='Available' WHERE id=?")->execute([$repair['asset_id']]);
        if ($repair['loan_item_id']) {
            $pdo->prepare("UPDATE loan_items SET item_status='Restored' WHERE id=?")->execute([$repair['loan_item_id']]);
            // If parent loan has no more open items, mark Completed
            $li = $pdo->prepare("SELECT loan_id FROM loan_items WHERE id = ?");
            $li->execute([$repair['loan_item_id']]);
            $loanId = (int) $li->fetchColumn();
            if ($loanId) {
                $rem = $pdo->prepare("SELECT COUNT(*) FROM loan_items WHERE loan_id = ? AND item_status IN ('Reserved','CheckedOut','ReturnedDamaged','InRepair')");
                $rem->execute([$loanId]);
                if ((int)$rem->fetchColumn() === 0) {
                    $pdo->prepare("UPDATE loans SET status='Completed', updated_by=? WHERE id = ? AND status IN ('Returned','CheckedOut')")->execute([Auth::id(), $loanId]);
                }
            }
        }
        $pdo->commit();
        log_audit('repair.complete', 'repair', $id, ['technician' => $techName]);
        flash('success', 'Perbaikan ditutup. Alat kembali TERSEDIA.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Gagal: ' . $e->getMessage());
    }
    redirect("/repairs/$uuid");
}
