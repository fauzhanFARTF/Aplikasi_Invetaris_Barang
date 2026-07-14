<?php
declare(strict_types=1);
// Packages CRUD

function package_index(): void {
    Auth::requireLogin();
    $pdo = db();
    $packages = $pdo->query("SELECT p.*, COUNT(pi.asset_id) AS item_count FROM packages p LEFT JOIN package_items pi ON pi.package_id = p.id WHERE p.deleted_at IS NULL GROUP BY p.id ORDER BY p.updated_at DESC, p.id DESC")->fetchAll();
    layout('main', 'packages/index', ['title' => 'Paket Alat', 'packages' => $packages, 'currentPath' => '/packages']);
}

function package_create_get(): void {
    Auth::requireRole('admin_gudang', 'admin');
    $pdo = db();
    $assets = $pdo->query("SELECT a.*, c.name AS category_name FROM assets a LEFT JOIN categories c ON c.id = a.category_id WHERE a.status != 'Retired' AND a.deleted_at IS NULL ORDER BY a.name")->fetchAll();
    $categories = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
    layout('main', 'packages/form', ['title' => 'Tambah Paket', 'package' => null, 'assets' => $assets, 'categories' => $categories, 'selectedIds' => [], 'currentPath' => '/packages']);
}

function package_create_post(): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $ids  = array_map('intval', $_POST['asset_ids'] ?? []);
    if (!$name) { flash('error', 'Nama paket wajib diisi.'); redirect('/packages/create'); }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO packages (uuid, name, description, created_by) VALUES (?,?,?,?)")->execute([generate_uuid(), $name, $desc, Auth::id()]);
        $pid = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT IGNORE INTO package_items (package_id, asset_id) VALUES (?,?)");
        foreach ($ids as $aid) { $ins->execute([$pid, $aid]); }
        $pdo->commit();
        log_audit('package.create', 'package', $pid, ['name' => $name, 'items' => count($ids)]);
        flash('success', 'Paket dibuat.');
    } catch (Throwable $e) { $pdo->rollBack(); flash('error', $e->getMessage()); }
    redirect('/packages');
}

function package_edit_get(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin');
    $id = uuid_to_id_or_404('packages', $uuid);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT p.*, cu.name AS created_by_name, uu.name AS updated_by_name, ru.name AS restored_by_name
                           FROM packages p
                           LEFT JOIN users cu ON cu.id = p.created_by
                           LEFT JOIN users uu ON uu.id = p.updated_by
                           LEFT JOIN users ru ON ru.id = p.restored_by
                           WHERE p.id = ? AND p.deleted_at IS NULL");
    $stmt->execute([$id]); $package = $stmt->fetch();
    if (!$package) { http_response_code(404); include APP_ROOT.'/views/errors/404.php'; return; }
    $selectedIds = array_map('intval', $pdo->prepare("SELECT asset_id FROM package_items WHERE package_id = ?")->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $s2 = $pdo->prepare("SELECT asset_id FROM package_items WHERE package_id = ?"); $s2->execute([$id]);
    $selectedIds = array_map('intval', $s2->fetchAll(PDO::FETCH_COLUMN));
    $assets = $pdo->query("SELECT a.*, c.name AS category_name FROM assets a LEFT JOIN categories c ON c.id = a.category_id WHERE a.status != 'Retired' AND a.deleted_at IS NULL ORDER BY a.name")->fetchAll();
    $categories = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
    layout('main', 'packages/form', ['title' => 'Ubah Paket', 'package' => $package, 'assets' => $assets, 'categories' => $categories, 'selectedIds' => $selectedIds, 'currentPath' => '/packages']);
}

function package_edit_post(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('packages', $uuid);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $ids  = array_map('intval', $_POST['asset_ids'] ?? []);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE packages SET name=?, description=?, updated_by=? WHERE id=?")->execute([$name, $desc, Auth::id(), $id]);
        $pdo->prepare("DELETE FROM package_items WHERE package_id = ?")->execute([$id]);
        $ins = $pdo->prepare("INSERT IGNORE INTO package_items (package_id, asset_id) VALUES (?,?)");
        foreach ($ids as $aid) { $ins->execute([$id, $aid]); }
        $pdo->commit();
        log_audit('package.update', 'package', $id);
        flash('success', 'Paket diperbarui.');
    } catch (Throwable $e) { $pdo->rollBack(); flash('error', $e->getMessage()); }
    redirect('/packages');
}

function package_delete(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('packages', $uuid);
    soft_delete('packages', (int)$id);
    log_audit('package.delete', 'package', $id);
    flash('success', 'Paket dihapus (bisa dipulihkan lewat Riwayat Terhapus).');
    redirect('/packages');
}
