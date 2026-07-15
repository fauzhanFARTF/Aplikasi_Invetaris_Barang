<?php
declare(strict_types=1);
// Inventory (Assets) CRUD

function inventory_index(): void {
    Auth::requireLogin();
    $pdo = db();
    $q = trim($_GET['q'] ?? '');
    $status = $_GET['status'] ?? '';
    $categoryId = (int) ($_GET['category_id'] ?? 0);

    $where = ["a.deleted_at IS NULL"];
    $params = [];
    if ($q) { $where[] = "(a.name LIKE ? OR a.bmn_number LIKE ? OR a.asset_code LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
    if ($status) { $where[] = "a.status = ?"; $params[] = $status; }
    if ($categoryId) { $where[] = "a.category_id = ?"; $params[] = $categoryId; }
    $sql = "SELECT a.*, c.name AS category_name,
                   EXISTS(SELECT 1 FROM loan_items li WHERE li.asset_id = a.id) AS has_loan
            FROM assets a LEFT JOIN categories c ON c.id = a.category_id
            WHERE " . implode(' AND ', $where) . " ORDER BY a.updated_at DESC, a.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assets = $stmt->fetchAll();
    $categories = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

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

/**
 * Pastikan user login berwenang mengelola alat (lihat inventory_can_manage);
 * jika tidak → 403 dan berhenti. Mengembalikan baris alat untuk dipakai handler.
 */
function _inventory_require_manage(int $id): array {
    $stmt = db()->prepare("SELECT id, photo, created_by, status FROM assets WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $asset = $stmt->fetch();
    if (!$asset) { flash('error', 'Alat tidak ditemukan.'); redirect('/inventory'); }
    if (!inventory_can_manage($asset['created_by'])) {
        http_response_code(403);
        include APP_ROOT . '/views/errors/403.php';
        exit;
    }
    return $asset;
}

function inventory_create_get(): void {
    Auth::requireRole('admin_gudang', 'admin', 'it_staff_pembantu');
    $pdo = db();
    $categories = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
    layout('main', 'inventory/form', [
        'title' => 'Tambah Alat',
        'asset' => null,
        'categories' => $categories,
        'currentPath' => '/inventory',
    ]);
}

function inventory_create_post(): void {
    Auth::requireRole('admin_gudang', 'admin', 'it_staff_pembantu');
    Auth::verifyCsrf();
    $data = _inventory_capture();
    if (!$data['name']) {
        flash('error', 'Nama alat wajib diisi.');
        redirect('/inventory/create');
    }
    // Kode Aset & No. BMN dibuat otomatis dari kode singkatan kategori.
    if (!$data['category_id']) {
        flash('error', 'Kategori wajib dipilih (Kode Aset & No. BMN dibuat dari kode singkatan kategori).');
        redirect('/inventory/create');
    }
    if (!next_asset_code((int)$data['category_id'])) {
        flash('error', 'Kategori terpilih belum punya kode singkatan. Lengkapi dulu di menu Kategori.');
        redirect('/inventory/create');
    }
    $upload = handle_photo_upload('photo');
    if ($upload['error']) {
        flash('error', $upload['error']);
        redirect('/inventory/create');
    }
    // Foto opsional. Jika tidak ada file diunggah, coba ambil dari link (mis. Google Drive).
    if (!$upload['filename'] && trim($_POST['photo_url'] ?? '') !== '') {
        $upload = handle_photo_from_url($_POST['photo_url']);
        if ($upload['error']) { flash('error', $upload['error']); redirect('/inventory/create'); }
    }
    $pdo = db();
    // Coba beberapa kali untuk mengatasi tabrakan nomor urut bila ada input bersamaan.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $gen = next_asset_code((int)$data['category_id']);
        try {
            $stmt = $pdo->prepare("INSERT INTO assets (uuid, asset_code, bmn_number, name, category_id, brand, model, serial_number, barcode, condition_note, photo, purchase_price, purchase_date, current_value, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'Available', ?)");
            $stmt->execute([generate_uuid(), $gen['asset_code'], $gen['bmn_number'], $data['name'], $data['category_id'], $data['brand'], $data['model'], $data['serial_number'], $gen['bmn_number'], $data['condition_note'], $upload['filename'], $data['purchase_price'], $data['purchase_date'], $data['current_value'], Auth::id()]);
            log_audit('asset.create', 'asset', $pdo->lastInsertId(), ['asset_code' => $gen['asset_code']] + $data);
            flash('success', "Alat berhasil ditambahkan dengan Kode {$gen['asset_code']} (BMN {$gen['bmn_number']}).");
            redirect('/inventory');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && $attempt < 4) continue; // tabrakan unik -> hitung ulang nomor
            delete_photo($upload['filename']); // rollback file kalau insert gagal
            flash('error', 'Gagal: ' . $e->getMessage());
            redirect('/inventory/create');
        }
    }
}

function inventory_edit_get(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin', 'it_staff_pembantu');
    $id = uuid_to_id_or_404("assets", $uuid);
    _inventory_require_manage((int)$id);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT a.*, cu.name AS created_by_name, uu.name AS updated_by_name, ru.name AS restored_by_name
                           FROM assets a
                           LEFT JOIN users cu ON cu.id = a.created_by
                           LEFT JOIN users uu ON uu.id = a.updated_by
                           LEFT JOIN users ru ON ru.id = a.restored_by
                           WHERE a.id = ? AND a.deleted_at IS NULL");
    $stmt->execute([(int)$id]);
    $asset = $stmt->fetch();
    if (!$asset) { http_response_code(404); include APP_ROOT.'/views/errors/404.php'; return; }
    $categories = $pdo->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
    layout('main', 'inventory/form', ['title' => 'Ubah Alat', 'asset' => $asset, 'categories' => $categories, 'currentPath' => '/inventory']);
}

function inventory_edit_post(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin', 'it_staff_pembantu');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('assets', $uuid);
    $data = _inventory_capture();

    $pdo = db();
    $existing = _inventory_require_manage((int)$id);
    $oldPhoto = $existing['photo'];

    $upload = handle_photo_upload('photo', $oldPhoto);
    if ($upload['error']) {
        flash('error', $upload['error']);
        redirect('/inventory/' . $uuid . '/edit');
    }
    // Jika tidak ada file diunggah, coba ambil dari link (mis. Google Drive).
    if (!$upload['filename'] && trim($_POST['photo_url'] ?? '') !== '') {
        $upload = handle_photo_from_url($_POST['photo_url'], $oldPhoto);
        if ($upload['error']) { flash('error', $upload['error']); redirect('/inventory/' . $uuid . '/edit'); }
    }

    // Tentukan nilai kolom photo final:
    // - Ada foto baru (upload/link) -> pakai yang baru
    // - Tidak ada foto baru, tapi user centang "hapus foto" -> null
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
        $pdo->prepare("UPDATE assets SET asset_code=?, bmn_number=?, name=?, category_id=?, brand=?, model=?, serial_number=?, barcode=?, condition_note=?, photo=?, purchase_price=?, purchase_date=?, current_value=?, updated_by=? WHERE id=?")
            ->execute([$data['asset_code'], $data['bmn_number'], $data['name'], $data['category_id'], $data['brand'], $data['model'], $data['serial_number'], $data['barcode'] ?: $data['bmn_number'], $data['condition_note'], $photo, $data['purchase_price'], $data['purchase_date'], $data['current_value'], Auth::id(), (int)$id]);
        log_audit('asset.update', 'asset', $id, $data);
        flash('success', 'Perubahan disimpan.');
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('/inventory');
}

function inventory_retire(string $uuid): void {
    Auth::requireRole('admin', 'admin_gudang', 'it_staff_pembantu');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('assets', $uuid);
    db()->prepare("UPDATE assets SET status='Retired', updated_by=? WHERE id=? AND status IN ('Available','Damaged')")->execute([Auth::id(), (int)$id]);
    log_audit('asset.retire', 'asset', $id);
    flash('success', 'Alat dinonaktifkan (Retired).');
    redirect('/inventory');
}

function inventory_unretire(string $uuid): void {
    Auth::requireRole('admin', 'admin_gudang', 'it_staff_pembantu');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('assets', $uuid);
    db()->prepare("UPDATE assets SET status='Available', updated_by=? WHERE id=? AND status='Retired'")->execute([Auth::id(), (int)$id]);
    log_audit('asset.unretire', 'asset', $id);
    flash('success', 'Alat diaktifkan kembali (Tersedia).');
    redirect('/inventory');
}

function inventory_delete(string $uuid): void {
    Auth::requireRole('admin', 'admin_gudang', 'it_staff_pembantu');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('assets', $uuid);
    _inventory_require_manage($id); // pastikan berwenang mengelola alat

    // Alat yang pernah dipinjam hanya boleh dihapus oleh superadmin — menjaga
    // integritas riwayat peminjaman.
    if (Auth::role() !== 'superadmin' && asset_has_loan_history($id)) {
        flash('error', 'Alat ini pernah dipinjam sehingga tidak dapat dihapus. Hubungi Super Admin bila benar-benar perlu dihapus.');
        redirect('/inventory');
    }

    soft_delete('assets', $id);
    log_audit('asset.delete', 'asset', $id);
    flash('success', 'Alat dihapus (bisa dipulihkan lewat Riwayat Terhapus).');
    redirect('/inventory');
}

function inventory_barcode_single(string $uuid): void {
    Auth::requireRole('admin_gudang', 'admin', 'supervisor', 'it_staff_pembantu');
    $id = uuid_to_id_or_404('assets', $uuid);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT a.*, c.name AS category_name FROM assets a LEFT JOIN categories c ON c.id = a.category_id WHERE a.id = ?");
    $stmt->execute([(int)$id]);
    $asset = $stmt->fetch();
    if (!$asset) { http_response_code(404); include APP_ROOT.'/views/errors/404.php'; return; }
    $assets = [$asset];
    include APP_ROOT . '/views/inventory/barcode_print.php';
}

function inventory_barcode_bulk(): void {
    Auth::requireRole('admin_gudang', 'admin', 'supervisor', 'it_staff_pembantu');
    // Terima daftar UUID (bukan id) dari query string agar id tidak terekspos.
    $uuids = array_values(array_unique(array_filter(array_map('trim', explode(',', $_GET['ids'] ?? '')))));
    if (empty($uuids)) {
        flash('error', 'Pilih minimal satu alat untuk dicetak barcode-nya.');
        redirect('/inventory');
    }
    $placeholders = implode(',', array_fill(0, count($uuids), '?'));
    $pdo = db();
    $stmt = $pdo->prepare("SELECT a.*, c.name AS category_name FROM assets a LEFT JOIN categories c ON c.id = a.category_id WHERE a.uuid IN ($placeholders)");
    $stmt->execute($uuids);
    $rows = $stmt->fetchAll();
    // Pertahankan urutan sesuai yang dipilih pengguna (agar sesuai urutan centang di tabel)
    $byUuid = [];
    foreach ($rows as $r) { $byUuid[$r['uuid']] = $r; }
    $assets = [];
    foreach ($uuids as $u) { if (isset($byUuid[$u])) $assets[] = $byUuid[$u]; }
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
