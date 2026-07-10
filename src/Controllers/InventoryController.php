<?php
declare(strict_types=1);
// Inventory (Assets) CRUD

function inventory_index(): void {
    Auth::requireLogin();
    $pdo = db();
    $q = trim($_GET['q'] ?? '');
    $status = $_GET['status'] ?? '';
    $categoryId = (int) ($_GET['category_id'] ?? 0);

    $where = ["a.status != 'Retired' OR a.status = 'Retired'"]; // include all
    $params = [];
    if ($q) { $where[] = "(a.name LIKE ? OR a.bmn_number LIKE ? OR a.asset_code LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
    if ($status) { $where[] = "a.status = ?"; $params[] = $status; }
    if ($categoryId) { $where[] = "a.category_id = ?"; $params[] = $categoryId; }
    $sql = "SELECT a.*, c.name AS category_name FROM assets a LEFT JOIN categories c ON c.id = a.category_id WHERE " . implode(' AND ', $where) . " ORDER BY a.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assets = $stmt->fetchAll();
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

    layout('main', 'inventory/index', [
        'title' => 'Manajemen Alat',
        'assets' => $assets,
        'categories' => $categories,
        'q' => $q,
        'currentStatus' => $status,
        'currentCategoryId' => $categoryId,
        'currentPath' => '/inventory',
    ]);
}

function inventory_create_get(): void {
    Auth::requireRole('admin_gudang', 'admin');
    $pdo = db();
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
    layout('main', 'inventory/form', [
        'title' => 'Tambah Alat',
        'asset' => null,
        'categories' => $categories,
        'currentPath' => '/inventory',
    ]);
}

function inventory_create_post(): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $data = _inventory_capture();
    if (!$data['asset_code'] || !$data['bmn_number'] || !$data['name']) {
        flash('error', 'Kode Aset, No. BMN, dan Nama wajib diisi.');
        redirect('/inventory/create');
    }
    $upload = handle_photo_upload('photo');
    if ($upload['error']) {
        flash('error', $upload['error']);
        redirect('/inventory/create');
    }
    try {
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO assets (asset_code, bmn_number, name, category_id, brand, model, serial_number, barcode, condition_note, photo, purchase_price, purchase_date, current_value, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'Available')");
        $stmt->execute([$data['asset_code'], $data['bmn_number'], $data['name'], $data['category_id'], $data['brand'], $data['model'], $data['serial_number'], $data['barcode'] ?: $data['bmn_number'], $data['condition_note'], $upload['filename'], $data['purchase_price'], $data['purchase_date'], $data['current_value']]);
        log_audit('asset.create', 'asset', $pdo->lastInsertId(), $data);
        flash('success', 'Alat berhasil ditambahkan.');
        redirect('/inventory');
    } catch (Throwable $e) {
        delete_photo($upload['filename']); // rollback file kalau insert gagal
        flash('error', 'Gagal: ' . $e->getMessage());
        redirect('/inventory/create');
    }
}

function inventory_edit_get(string $id): void {
    Auth::requireRole('admin_gudang', 'admin');
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->execute([(int)$id]);
    $asset = $stmt->fetch();
    if (!$asset) { http_response_code(404); include APP_ROOT.'/views/errors/404.php'; return; }
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
    layout('main', 'inventory/form', ['title' => 'Ubah Alat', 'asset' => $asset, 'categories' => $categories, 'currentPath' => '/inventory']);
}

function inventory_edit_post(string $id): void {
    Auth::requireRole('admin_gudang', 'admin');
    Auth::verifyCsrf();
    $data = _inventory_capture();

    $pdo = db();
    $stmt = $pdo->prepare("SELECT photo FROM assets WHERE id = ?");
    $stmt->execute([(int)$id]);
    $existing = $stmt->fetch();
    if (!$existing) { flash('error', 'Alat tidak ditemukan.'); redirect('/inventory'); }
    $oldPhoto = $existing['photo'];

    $upload = handle_photo_upload('photo', $oldPhoto);
    if ($upload['error']) {
        flash('error', $upload['error']);
        redirect('/inventory/' . (int)$id . '/edit');
    }

    // Tentukan nilai kolom photo final:
    // - Ada file baru diupload -> pakai file baru
    // - Tidak ada file baru, tapi user centang "hapus foto" -> null
    // - Selain itu -> tetap pakai foto lama
    if ($upload['filename']) {
        $photo = $upload['filename'];
    } elseif (!empty($_POST['remove_photo'])) {
        delete_photo($oldPhoto);
        $photo = null;
    } else {
        $photo = $oldPhoto;
    }

    try {
        $pdo->prepare("UPDATE assets SET asset_code=?, bmn_number=?, name=?, category_id=?, brand=?, model=?, serial_number=?, barcode=?, condition_note=?, photo=?, purchase_price=?, purchase_date=?, current_value=? WHERE id=?")
            ->execute([$data['asset_code'], $data['bmn_number'], $data['name'], $data['category_id'], $data['brand'], $data['model'], $data['serial_number'], $data['barcode'] ?: $data['bmn_number'], $data['condition_note'], $photo, $data['purchase_price'], $data['purchase_date'], $data['current_value'], (int)$id]);
        log_audit('asset.update', 'asset', $id, $data);
        flash('success', 'Perubahan disimpan.');
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('/inventory');
}

function inventory_retire(string $id): void {
    Auth::requireRole('admin', 'admin_gudang');
    Auth::verifyCsrf();
    db()->prepare("UPDATE assets SET status='Retired' WHERE id=? AND status IN ('Available','Damaged')")->execute([(int)$id]);
    log_audit('asset.retire', 'asset', $id);
    flash('success', 'Alat dinonaktifkan (Retired).');
    redirect('/inventory');
}

function inventory_barcode_single(string $id): void {
    Auth::requireRole('admin_gudang', 'admin', 'supervisor');
    $pdo = db();
    $stmt = $pdo->prepare("SELECT a.*, c.name AS category_name FROM assets a LEFT JOIN categories c ON c.id = a.category_id WHERE a.id = ?");
    $stmt->execute([(int)$id]);
    $asset = $stmt->fetch();
    if (!$asset) { http_response_code(404); include APP_ROOT.'/views/errors/404.php'; return; }
    $assets = [$asset];
    include APP_ROOT . '/views/inventory/barcode_print.php';
}

function inventory_barcode_bulk(): void {
    Auth::requireRole('admin_gudang', 'admin', 'supervisor');
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')))));
    if (empty($ids)) {
        flash('error', 'Pilih minimal satu alat untuk dicetak barcode-nya.');
        redirect('/inventory');
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo = db();
    $stmt = $pdo->prepare("SELECT a.*, c.name AS category_name FROM assets a LEFT JOIN categories c ON c.id = a.category_id WHERE a.id IN ($placeholders)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    // Pertahankan urutan sesuai yang dipilih pengguna (agar sesuai urutan centang di tabel)
    $byId = [];
    foreach ($rows as $r) { $byId[(int)$r['id']] = $r; }
    $assets = [];
    foreach ($ids as $i) { if (isset($byId[$i])) $assets[] = $byId[$i]; }
    if (empty($assets)) { flash('error', 'Alat tidak ditemukan.'); redirect('/inventory'); }
    include APP_ROOT . '/views/inventory/barcode_print.php';
}

function _inventory_capture(): array {
    return [
        'asset_code' => trim($_POST['asset_code'] ?? ''),
        'bmn_number' => trim($_POST['bmn_number'] ?? ''),
        'name'       => trim($_POST['name'] ?? ''),
        'category_id'=> (int) ($_POST['category_id'] ?? 0) ?: null,
        'brand'      => trim($_POST['brand'] ?? '') ?: null,
        'model'      => trim($_POST['model'] ?? '') ?: null,
        'serial_number' => trim($_POST['serial_number'] ?? '') ?: null,
        'barcode'    => trim($_POST['barcode'] ?? ''),
        'condition_note' => trim($_POST['condition_note'] ?? '') ?: null,
        'purchase_price' => _inventory_parse_money($_POST['purchase_price'] ?? ''),
        'purchase_date'  => trim($_POST['purchase_date'] ?? '') ?: null,
        'current_value'  => _inventory_parse_money($_POST['current_value'] ?? ''),
    ];
}

function _inventory_parse_money($value): ?float {
    $value = trim((string) $value);
    if ($value === '') return null;
    // Buang "Rp" dan spasi jika ada. Input form pakai <input type="number">
    // sehingga desimal selalu memakai titik (mis. 1500000.50), bukan pemisah ribuan.
    $clean = preg_replace('/[^0-9.\-]/', '', $value);
    if ($clean === '' || !is_numeric($clean)) return null;
    return (float) $clean;
}
