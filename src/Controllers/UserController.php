<?php
declare(strict_types=1);
// User management (admin only)

function user_index(): void {
    Auth::requireRole('admin');
    $users = db()->query("SELECT * FROM users ORDER BY role, name")->fetchAll();
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
    try {
        db()->prepare("INSERT INTO users (name,email,password_hash,role,phone,unit_kerja,is_active) VALUES (?,?,?,?,?,?,1)")
            ->execute([$d['name'], $d['email'], password_hash($_POST['password'], PASSWORD_BCRYPT), $d['role'], $d['phone'], $d['unit_kerja']]);
        log_audit('user.create', 'user', db()->lastInsertId(), ['email' => $d['email']]);
        flash('success', 'User dibuat.');
        redirect('/users');
    } catch (Throwable $e) { flash('error', $e->getMessage()); redirect('/users/create'); }
}

function user_edit_get(string $id): void {
    Auth::requireRole('admin');
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([(int)$id]);
    $user = $stmt->fetch();
    if (!$user) { http_response_code(404); include APP_ROOT.'/views/errors/404.php'; return; }
    layout('main', 'users/form', ['title' => 'Ubah User', 'user' => $user, 'currentPath' => '/users']);
}

function user_edit_post(string $id): void {
    Auth::requireRole('admin');
    Auth::verifyCsrf();
    $d = _user_capture();
    $sql = "UPDATE users SET name=?, email=?, role=?, phone=?, unit_kerja=?";
    $params = [$d['name'], $d['email'], $d['role'], $d['phone'], $d['unit_kerja']];
    if (!empty($_POST['password'])) { $sql .= ", password_hash=?"; $params[] = password_hash($_POST['password'], PASSWORD_BCRYPT); }
    $sql .= " WHERE id = ?"; $params[] = (int)$id;
    try {
        db()->prepare($sql)->execute($params);
        log_audit('user.update', 'user', $id);
        flash('success', 'User diperbarui.');
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('/users');
}

function user_toggle(string $id): void {
    Auth::requireRole('admin');
    Auth::verifyCsrf();
    db()->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?")->execute([(int)$id]);
    log_audit('user.toggle', 'user', $id);
    flash('success', 'Status user diubah.');
    redirect('/users');
}

function _user_capture(): array {
    return [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'role' => $_POST['role'] ?? '',
        'phone' => trim($_POST['phone'] ?? '') ?: null,
        'unit_kerja' => trim($_POST['unit_kerja'] ?? '') ?: null,
    ];
}

// ================ Category ================

function category_index(): void {
    Auth::requireRole('admin', 'admin_gudang');
    $cats = db()->query("SELECT c.*, COUNT(a.id) AS asset_count FROM categories c LEFT JOIN assets a ON a.category_id = c.id GROUP BY c.id ORDER BY c.name")->fetchAll();
    layout('main', 'inventory/categories', ['title' => 'Kategori Alat', 'cats' => $cats, 'currentPath' => '/categories']);
}
function category_create(): void {
    Auth::requireRole('admin', 'admin_gudang');
    Auth::verifyCsrf();
    $name = trim($_POST['name'] ?? ''); $desc = trim($_POST['description'] ?? '');
    if (!$name) { flash('error', 'Nama kategori wajib.'); redirect('/categories'); }
    try { db()->prepare("INSERT INTO categories (name, description) VALUES (?,?)")->execute([$name, $desc]); flash('success', 'Kategori ditambahkan.'); }
    catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('/categories');
}
function category_delete(string $id): void {
    Auth::requireRole('admin');
    Auth::verifyCsrf();
    try { db()->prepare("DELETE FROM categories WHERE id = ?")->execute([(int)$id]); flash('success','Kategori dihapus.'); }
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
    $where = ["a.status != 'Retired'"];
    $params = [];
    if ($q) { $where[] = "(a.name LIKE ? OR a.bmn_number LIKE ? OR a.asset_code LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
    if ($cat) { $where[] = "a.category_id = ?"; $params[] = $cat; }
    $sql = "SELECT a.id,a.name,a.asset_code,a.bmn_number,a.status,c.name category_name FROM assets a LEFT JOIN categories c ON c.id = a.category_id WHERE " . implode(' AND ', $where) . " ORDER BY a.name LIMIT 100";
    $stmt = db()->prepare($sql); $stmt->execute($params);
    json_response(['assets' => $stmt->fetchAll()]);
}
function api_loan_detail(string $id): void {
    Auth::requireLogin();
    $stmt = db()->prepare("SELECT * FROM loans WHERE id = ?"); $stmt->execute([(int)$id]);
    json_response(['loan' => $stmt->fetch() ?: null]);
}
function api_unread_notif(): void {
    Auth::requireLogin();
    json_response(['count' => Notification::unreadCount(Auth::id())]);
}
