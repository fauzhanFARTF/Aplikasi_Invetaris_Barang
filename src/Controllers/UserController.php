<?php
declare(strict_types=1);
// User management (admin only)

function user_index(): void {
    Auth::requireRole('admin');
    $users = db()->query("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY updated_at DESC, id DESC")->fetchAll();
    layout('main', 'users/index', ['title' => 'Manajemen User', 'users' => $users, 'currentPath' => '/users']);
}

function user_create_get(): void {
    Auth::requireRole('admin');
    layout('main', 'users/form', ['title' => 'Tambah User', 'user' => null, 'currentPath' => '/users']);
}

function user_create_post(): void {
    Auth::requireRole('admin');
    Auth::verifyCsrf();
    $d = _user_capture();
    if (!$d['name'] || !$d['email'] || !$d['role'] || empty($_POST['password'])) {
        flash('error', 'Nama, email, role, dan password wajib.'); redirect('/users/create');
    }
    if (!_user_role_assignable($d['role'])) {
        flash('error', 'Role tidak valid / tidak berwenang memberikan role tersebut.'); redirect('/users/create');
    }
    $upload = handle_photo_upload('photo', null, 'users', 'user');
    if ($upload['error']) {
        flash('error', $upload['error']);
        redirect('/users/create');
    }
    try {
        db()->prepare("INSERT INTO users (uuid,name,email,password_hash,role,phone,unit_kerja,photo,is_active,created_by) VALUES (?,?,?,?,?,?,?,?,1,?)")
            ->execute([generate_uuid(), $d['name'], $d['email'], password_hash($_POST['password'], PASSWORD_BCRYPT), $d['role'], $d['phone'], $d['unit_kerja'], $upload['filename'], Auth::id()]);
        log_audit('user.create', 'user', db()->lastInsertId(), ['email' => $d['email']]);
        flash('success', 'User dibuat.');
        redirect('/users');
    } catch (Throwable $e) {
        delete_photo($upload['filename'], 'users'); // rollback file kalau insert gagal
        flash('error', $e->getMessage());
        redirect('/users/create');
    }
}

function user_edit_get(string $uuid): void {
    Auth::requireRole('admin');
    $id = uuid_to_id_or_404('users', $uuid);
    _user_guard_target((int)$id);
    $stmt = db()->prepare("SELECT u.*, cu.name AS created_by_name, uu.name AS updated_by_name, ru.name AS restored_by_name
                           FROM users u
                           LEFT JOIN users cu ON cu.id = u.created_by
                           LEFT JOIN users uu ON uu.id = u.updated_by
                           LEFT JOIN users ru ON ru.id = u.restored_by
                           WHERE u.id = ? AND u.deleted_at IS NULL");
    $stmt->execute([(int)$id]);
    $user = $stmt->fetch();
    if (!$user) { http_response_code(404); include APP_ROOT.'/views/errors/404.php'; return; }
    layout('main', 'users/form', ['title' => 'Ubah User', 'user' => $user, 'currentPath' => '/users']);
}

function user_edit_post(string $uuid): void {
    Auth::requireRole('admin');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('users', $uuid);
    _user_guard_target((int)$id);
    $d = _user_capture();
    if (!_user_role_assignable($d['role'])) {
        flash('error', 'Role tidak valid / tidak berwenang memberikan role tersebut.');
        redirect('/users/' . $uuid . '/edit');
    }

    $stmt = db()->prepare("SELECT photo FROM users WHERE id = ?");
    $stmt->execute([(int)$id]);
    $existing = $stmt->fetch();
    if (!$existing) { flash('error', 'User tidak ditemukan.'); redirect('/users'); }
    $oldPhoto = $existing['photo'];

    $upload = handle_photo_upload('photo', $oldPhoto, 'users', 'user');
    if ($upload['error']) {
        flash('error', $upload['error']);
        redirect('/users/' . $uuid . '/edit');
    }

    if ($upload['filename']) {
        $photo = $upload['filename'];
    } elseif (!empty($_POST['remove_photo'])) {
        delete_photo($oldPhoto, 'users');
        $photo = null;
    } else {
        $photo = $oldPhoto;
    }

    $sql = "UPDATE users SET name=?, email=?, role=?, phone=?, unit_kerja=?, photo=?, updated_by=?";
    $params = [$d['name'], $d['email'], $d['role'], $d['phone'], $d['unit_kerja'], $photo, Auth::id()];
    if (!empty($_POST['password'])) { $sql .= ", password_hash=?"; $params[] = password_hash($_POST['password'], PASSWORD_BCRYPT); }
    $sql .= " WHERE id = ?"; $params[] = (int)$id;
    try {
        db()->prepare($sql)->execute($params);
        log_audit('user.update', 'user', $id);
        flash('success', 'User diperbarui.');
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('/users');
}

function user_toggle(string $uuid): void {
    Auth::requireRole('admin');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('users', $uuid);
    _user_guard_target((int)$id);
    db()->prepare("UPDATE users SET is_active = 1 - is_active, updated_by = ? WHERE id = ?")->execute([Auth::id(), (int)$id]);
    log_audit('user.toggle', 'user', $id);
    flash('success', 'Status user diubah.');
    redirect('/users');
}

function user_delete(string $uuid): void {
    Auth::requireRole('admin');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('users', $uuid);
    if ($id === Auth::id()) {
        flash('error', 'Tidak bisa menghapus akun Anda sendiri.');
        redirect('/users');
    }
    _user_guard_target($id);
    soft_delete('users', $id);
    log_audit('user.delete', 'user', $id);
    flash('success', 'User dihapus (bisa dipulihkan lewat Riwayat Terhapus).');
    redirect('/users');
}

/** Nilai unit kerja: pakai isian bebas jika memilih "Lainnya", jika tidak pakai pilihan. */
function _capture_unit_kerja(): ?string {
    $sel = trim($_POST['unit_kerja'] ?? '');
    if ($sel === '__other__') {
        return trim($_POST['unit_kerja_other'] ?? '') ?: null;
    }
    return $sel ?: null;
}

function _user_capture(): array {
    return [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'role' => $_POST['role'] ?? '',
        'phone' => trim($_POST['phone'] ?? '') ?: null,
        'unit_kerja' => _capture_unit_kerja(),
    ];
}

/**
 * Validasi role yang boleh di-assign: whitelist + role 'superadmin' hanya boleh
 * diberikan oleh superadmin (admin biasa tidak bisa membuat/menaikkan superadmin).
 */
function _user_role_assignable(string $role): bool {
    $valid = ['superadmin', 'admin', 'pemohon', 'supervisor', 'admin_gudang', 'inventory_staff'];
    if (!in_array($role, $valid, true)) return false;
    if ($role === 'superadmin' && Auth::role() !== 'superadmin') return false;
    return true;
}

/**
 * Guard target: akun superadmin hanya boleh diubah/dihapus/di-toggle oleh
 * sesama superadmin. Mengembalikan role target, atau redirect kalau dilanggar.
 */
function _user_guard_target(int $id): ?string {
    $stmt = db()->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $targetRole = $stmt->fetchColumn() ?: null;
    if ($targetRole === 'superadmin' && Auth::role() !== 'superadmin') {
        flash('error', 'Akun Super Admin hanya dapat dikelola oleh Super Admin.');
        redirect('/users');
    }
    return $targetRole;
}

// ================ Category ================

function category_index(): void {
    Auth::requireRole('admin', 'admin_gudang');
    $cats = db()->query("SELECT c.*, COUNT(a.id) AS asset_count, cu.name AS created_by_name, uu.name AS updated_by_name, ru.name AS restored_by_name
                         FROM categories c
                         LEFT JOIN assets a ON a.category_id = c.id
                         LEFT JOIN users cu ON cu.id = c.created_by
                         LEFT JOIN users uu ON uu.id = c.updated_by
                         LEFT JOIN users ru ON ru.id = c.restored_by
                         WHERE c.deleted_at IS NULL GROUP BY c.id ORDER BY c.updated_at DESC, c.id DESC")->fetchAll();
    layout('main', 'inventory/categories', ['title' => 'Kategori Alat', 'cats' => $cats, 'currentPath' => '/categories']);
}

function category_create_get(): void {
    Auth::requireRole('admin', 'admin_gudang');
    layout('main', 'inventory/category_form', ['title' => 'Tambah Kategori', 'category' => null, 'currentPath' => '/categories']);
}

function category_create_post(): void {
    Auth::requireRole('admin', 'admin_gudang');
    Auth::verifyCsrf();
    $name = trim($_POST['name'] ?? ''); $desc = trim($_POST['description'] ?? '') ?: null;
    if (!$name) { flash('error', 'Nama kategori wajib diisi.'); redirect('/categories/create'); }
    try {
        db()->prepare("INSERT INTO categories (uuid, name, description, created_by) VALUES (?,?,?,?)")->execute([generate_uuid(), $name, $desc, Auth::id()]);
        flash('success', 'Kategori ditambahkan.');
        redirect('/categories');
    } catch (Throwable $e) {
        flash('error', 'Gagal menambah kategori (nama mungkin sudah dipakai): ' . $e->getMessage());
        redirect('/categories/create');
    }
}

function category_edit_get(string $uuid): void {
    Auth::requireRole('admin', 'admin_gudang');
    $id = uuid_to_id_or_404('categories', $uuid);
    $stmt = db()->prepare("SELECT c.*, cu.name AS created_by_name, uu.name AS updated_by_name, ru.name AS restored_by_name
                           FROM categories c
                           LEFT JOIN users cu ON cu.id = c.created_by
                           LEFT JOIN users uu ON uu.id = c.updated_by
                           LEFT JOIN users ru ON ru.id = c.restored_by
                           WHERE c.id = ? AND c.deleted_at IS NULL");
    $stmt->execute([(int)$id]);
    $category = $stmt->fetch();
    if (!$category) { http_response_code(404); include APP_ROOT.'/views/errors/404.php'; return; }
    layout('main', 'inventory/category_form', ['title' => 'Ubah Kategori', 'category' => $category, 'currentPath' => '/categories']);
}

function category_edit_post(string $uuid): void {
    Auth::requireRole('admin', 'admin_gudang');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('categories', $uuid);
    $name = trim($_POST['name'] ?? ''); $desc = trim($_POST['description'] ?? '') ?: null;
    if (!$name) { flash('error', 'Nama kategori wajib diisi.'); redirect('/categories/' . $uuid . '/edit'); }
    try {
        db()->prepare("UPDATE categories SET name=?, description=?, updated_by=? WHERE id=?")->execute([$name, $desc, Auth::id(), (int)$id]);
        flash('success', 'Kategori diperbarui.');
        redirect('/categories');
    } catch (Throwable $e) {
        flash('error', 'Gagal memperbarui kategori (nama mungkin sudah dipakai): ' . $e->getMessage());
        redirect('/categories/' . $uuid . '/edit');
    }
}

function category_delete(string $uuid): void {
    Auth::requireRole('admin', 'admin_gudang');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('categories', $uuid);
    try { soft_delete('categories', (int)$id); flash('success','Kategori dihapus (bisa dipulihkan lewat Riwayat Terhapus).'); }
    catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('/categories');
}

// ================ Notifications ================

function notification_index(): void {
    Auth::requireLogin();
    $stmt = db()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
    $stmt->execute([Auth::id()]);
    $notifs = $stmt->fetchAll();
    layout('main', 'notifications/index', ['title' => 'Notifikasi', 'notifs' => $notifs, 'currentPath' => '/notifications']);
}
function notification_mark_read(string $id): void {
    Auth::requireLogin();
    Auth::verifyCsrf();
    db()->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$id, Auth::id()]);
    if (($_POST['ajax'] ?? '') === '1') { json_response(['ok' => true]); }
    redirect('/notifications');
}
function notification_mark_all_read(): void {
    Auth::requireLogin();
    Auth::verifyCsrf();
    db()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([Auth::id()]);
    flash('success', 'Semua notifikasi ditandai dibaca.');
    redirect('/notifications');
}
function notification_delete(string $id): void {
    Auth::requireLogin();
    Auth::verifyCsrf();
    // Scoped to user_id so a user can only ever delete their own notifications.
    db()->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([(int)$id, Auth::id()]);
    flash('success', 'Notifikasi dihapus.');
    redirect('/notifications');
}
function notification_delete_all(): void {
    Auth::requireLogin();
    Auth::verifyCsrf();
    $pdo = db();
    $count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=?");
    $count->execute([Auth::id()]);
    $total = (int) $count->fetchColumn();
    if ($total === 0) {
        flash('error', 'Tidak ada riwayat notifikasi yang bisa dihapus.');
        redirect('/notifications');
    }
    $pdo->prepare("DELETE FROM notifications WHERE user_id=?")->execute([Auth::id()]);
    flash('success', "$total riwayat notifikasi berhasil dihapus.");
    redirect('/notifications');
}

// ================ Profile ================
function profile_get(): void {
    Auth::requireLogin();
    layout('main', 'users/profile', ['title' => 'Profil Saya', 'user' => Auth::user(), 'currentPath' => '/profile']);
}
function profile_photo_post(): void {
    Auth::requireLogin();
    Auth::verifyCsrf();
    $id = Auth::id();

    $stmt = db()->prepare("SELECT photo FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $oldPhoto = $stmt->fetchColumn() ?: null;

    // Hapus foto saat ini.
    if (!empty($_POST['remove_photo'])) {
        delete_photo($oldPhoto, 'users');
        db()->prepare("UPDATE users SET photo=NULL, updated_by=? WHERE id=?")->execute([$id, $id]);
        log_audit('user.photo_remove', 'user', $id);
        flash('success', 'Foto profil dihapus.');
        redirect('/profile');
    }

    $upload = handle_photo_upload('photo', $oldPhoto, 'users', 'user');
    if ($upload['error']) { flash('error', $upload['error']); redirect('/profile'); }
    if (!$upload['filename']) { flash('error', 'Tidak ada foto yang dipilih.'); redirect('/profile'); }

    db()->prepare("UPDATE users SET photo=?, updated_by=? WHERE id=?")->execute([$upload['filename'], $id, $id]);
    log_audit('user.photo_update', 'user', $id);
    flash('success', 'Foto profil diperbarui.');
    redirect('/profile');
}

function profile_post(): void {
    Auth::requireLogin();
    Auth::verifyCsrf();
    $old = $_POST['old_password'] ?? ''; $new = $_POST['new_password'] ?? '';
    if (!$old || !$new) { flash('error', 'Password lama dan baru wajib.'); redirect('/profile'); }
    $stmt = db()->prepare("SELECT password_hash FROM users WHERE id = ?"); $stmt->execute([Auth::id()]);
    if (!password_verify($old, (string)$stmt->fetchColumn())) { flash('error', 'Password lama salah.'); redirect('/profile'); }
    db()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($new, PASSWORD_BCRYPT), Auth::id()]);
    log_audit('user.change_password', 'user', Auth::id());
    flash('success', 'Password berhasil diubah.');
    redirect('/profile');
}

// ================ API ================
function api_health(): void { json_response(['status' => 'ok', 'time' => date('c'), 'app' => APP_NAME]); }

function api_availability(): void {
    Auth::requireLogin();
    $start = $_GET['start'] ?? date('Y-m-d');
    $end   = $_GET['end']   ?? date('Y-m-d');
    $stmt = db()->prepare("SELECT DISTINCT li.asset_id FROM loan_items li JOIN loans l ON l.id = li.loan_id
                           WHERE l.status IN ('Pending','Approved','CheckedOut') AND l.start_date <= ? AND l.end_date >= ?");
    $stmt->execute([$end, $start]);
    $busy = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    json_response(['busy_asset_ids' => $busy]);
}

function api_asset_search(): void {
    Auth::requireLogin();
    $q = trim($_GET['q'] ?? '');
    $cat = (int) ($_GET['category_id'] ?? 0);
    $where = ["a.status != 'Retired'", "a.deleted_at IS NULL"];
    $params = [];
    if ($q) { $where[] = "(a.name LIKE ? OR a.bmn_number LIKE ? OR a.asset_code LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
    if ($cat) { $where[] = "a.category_id = ?"; $params[] = $cat; }
    $sql = "SELECT a.id,a.name,a.asset_code,a.bmn_number,a.status,c.name category_name FROM assets a LEFT JOIN categories c ON c.id = a.category_id WHERE " . implode(' AND ', $where) . " ORDER BY a.name LIMIT 100";
    $stmt = db()->prepare($sql); $stmt->execute($params);
    json_response(['assets' => $stmt->fetchAll()]);
}
function api_loan_detail(string $id): void {
    Auth::requireLogin();
    $stmt = db()->prepare("SELECT * FROM loans WHERE id = ? AND deleted_at IS NULL"); $stmt->execute([(int)$id]);
    json_response(['loan' => $stmt->fetch() ?: null]);
}
function api_unread_notif(): void {
    Auth::requireLogin();
    json_response(['count' => Notification::unreadCount(Auth::id())]);
}
