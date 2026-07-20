<?php
declare(strict_types=1);
// User management (admin only)

function user_index(): void {
    Auth::requireRole('admin', 'administrator_pembantu_manajemen_user');
    $users = db()->query("SELECT u.*, (SELECT GROUP_CONCAT(ur.role SEPARATOR ',') FROM user_roles ur WHERE ur.user_id = u.id) AS extra_roles
                          FROM users u WHERE u.deleted_at IS NULL" . hidden_users_sql('u') . "
                          ORDER BY u.updated_at DESC, u.id DESC")->fetchAll();
    layout('main', 'users/index', ['title' => 'Manajemen User', 'users' => $users, 'currentPath' => '/users']);
}

function user_create_get(): void {
    Auth::requireRole('admin', 'administrator_pembantu_manajemen_user');
    layout('main', 'users/form', ['title' => 'Tambah User', 'user' => null, 'currentPath' => '/users']);
}

function user_create_post(): void {
    Auth::requireRole('admin', 'administrator_pembantu_manajemen_user');
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
        $newId = (int) db()->lastInsertId();
        _sync_extra_roles($newId, $d['role']);
        log_audit('user.create', 'user', $newId, ['email' => $d['email']]);
        flash('success', 'User dibuat.');
        redirect('/users');
    } catch (Throwable $e) {
        delete_photo($upload['filename'], 'users'); // rollback file kalau insert gagal
        flash('error', $e->getMessage());
        redirect('/users/create');
    }
}

function user_edit_get(string $uuid): void {
    Auth::requireRole('admin', 'administrator_pembantu_manajemen_user');
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
    layout('main', 'users/form', ['title' => 'Ubah User', 'user' => $user, 'extraRoles' => _user_extra_roles((int)$id), 'currentPath' => '/users']);
}

function user_edit_post(string $uuid): void {
    Auth::requireRole('admin', 'administrator_pembantu_manajemen_user');
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
        _sync_extra_roles((int)$id, $d['role']);
        log_audit('user.update', 'user', $id);
        flash('success', 'User diperbarui.');
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('/users');
}

function user_toggle(string $uuid): void {
    Auth::requireRole('admin', 'administrator_pembantu_manajemen_user');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('users', $uuid);
    _user_guard_target((int)$id);
    db()->prepare("UPDATE users SET is_active = 1 - is_active, updated_by = ? WHERE id = ?")->execute([Auth::id(), (int)$id]);
    log_audit('user.toggle', 'user', $id);
    flash('success', 'Status user diubah.');
    redirect('/users');
}

function user_delete(string $uuid): void {
    Auth::requireRole('admin', 'administrator_pembantu_manajemen_user');
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

/** Daftar role yang boleh di-assign oleh user login (untuk dropdown & validasi). */
function _assignable_roles(): array {
    $roles = ['pemohon', 'supervisor', 'admin_gudang', 'inventory_staff', 'pimpinan'];
    // Role dengan kuasa administratif hanya boleh diberikan oleh admin/superadmin
    // — mencegah eskalasi oleh Administrator Pembantu Manajemen User.
    if (Auth::hasRole('admin', 'superadmin')) {
        array_unshift($roles, 'admin',
            'administrator_pembantu_manajemen_user',
            'administrator_pembantu_manajemen_alat',
            'administrator_pembantu_manajemen_kategori');
    }
    if (Auth::hasRole('superadmin')) array_unshift($roles, 'superadmin');
    return $roles;
}

function _user_role_assignable(string $role): bool {
    return in_array($role, _assignable_roles(), true);
}

/**
 * Sinkronkan peran tambahan (user_roles) dari input $_POST['extra_roles'].
 * Hanya peran yang boleh di-assign, bukan peran utama, dan bukan duplikat.
 */
function _sync_extra_roles(int $userId, string $primaryRole): void {
    $extra = (array) ($_POST['extra_roles'] ?? []);
    $valid = [];
    foreach ($extra as $r) {
        $r = (string) $r;
        if ($r === $primaryRole) continue;              // sama dengan role utama
        if (!_user_role_assignable($r)) continue;        // tak berwenang / tak valid
        $valid[$r] = true;
    }
    $pdo = db();
    $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);
    if ($valid) {
        $ins = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, ?)");
        foreach (array_keys($valid) as $r) { $ins->execute([$userId, $r]); }
    }
}

/** Ambil daftar peran tambahan (user_roles) untuk sebuah user. */
function _user_extra_roles(int $userId): array {
    $stmt = db()->prepare("SELECT role FROM user_roles WHERE user_id = ?");
    $stmt->execute([$userId]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Guard target: akun superadmin hanya boleh diubah/dihapus/di-toggle oleh
 * sesama superadmin. Mengembalikan role target, atau redirect kalau dilanggar.
 */
function _user_guard_target(int $id): ?string {
    $stmt = db()->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $targetRole = $stmt->fetchColumn() ?: null;
    if ($targetRole === 'superadmin' && !Auth::hasRole('superadmin')) {
        flash('error', 'Akun Super Admin hanya dapat dikelola oleh Super Admin.');
        redirect('/users');
    }
    // Akun ber-kuasa administratif hanya boleh dikelola oleh admin/superadmin —
    // Administrator Pembantu Manajemen User tidak boleh menyentuhnya.
    $privileged = ['admin', 'administrator_pembantu_manajemen_user', 'administrator_pembantu_manajemen_alat', 'administrator_pembantu_manajemen_kategori'];
    if (in_array($targetRole, $privileged, true) && !Auth::hasRole('admin', 'superadmin')) {
        flash('error', 'Akun dengan hak administratif hanya dapat dikelola oleh Administrator Sistem.');
        redirect('/users');
    }
    return $targetRole;
}

// ================ Category ================

function category_index(): void {
    Auth::requireRole('admin', 'admin_gudang', 'administrator_pembantu_manajemen_kategori');
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
    Auth::requireRole('admin', 'admin_gudang', 'administrator_pembantu_manajemen_kategori');
    layout('main', 'inventory/category_form', ['title' => 'Tambah Kategori', 'category' => null, 'currentPath' => '/categories']);
}

function category_create_post(): void {
    Auth::requireRole('admin', 'admin_gudang', 'administrator_pembantu_manajemen_kategori');
    Auth::verifyCsrf();
    $name = trim($_POST['name'] ?? ''); $desc = trim($_POST['description'] ?? '') ?: null;
    $prefix = _capture_code_prefix();
    if (!$name) { flash('error', 'Nama kategori wajib diisi.'); redirect('/categories/create'); }
    if (!$prefix) { flash('error', 'Kode singkatan kategori wajib diisi (mis. CAMVIDEO).'); redirect('/categories/create'); }
    try {
        db()->prepare("INSERT INTO categories (uuid, name, code_prefix, description, created_by) VALUES (?,?,?,?,?)")->execute([generate_uuid(), $name, $prefix, $desc, Auth::id()]);
        flash('success', 'Kategori ditambahkan.');
        redirect('/categories');
    } catch (Throwable $e) {
        flash('error', 'Gagal menambah kategori (nama/kode mungkin sudah dipakai): ' . $e->getMessage());
        redirect('/categories/create');
    }
}

/** Normalisasi kode singkatan kategori: huruf/angka kapital tanpa spasi. */
function _capture_code_prefix(): ?string {
    $p = strtoupper(trim($_POST['code_prefix'] ?? ''));
    $p = preg_replace('/[^A-Z0-9]/', '', $p);
    return $p !== '' ? $p : null;
}

function category_edit_get(string $uuid): void {
    Auth::requireRole('admin', 'admin_gudang', 'administrator_pembantu_manajemen_kategori');
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
    Auth::requireRole('admin', 'admin_gudang', 'administrator_pembantu_manajemen_kategori');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('categories', $uuid);
    $name = trim($_POST['name'] ?? ''); $desc = trim($_POST['description'] ?? '') ?: null;
    $prefix = _capture_code_prefix();
    if (!$name) { flash('error', 'Nama kategori wajib diisi.'); redirect('/categories/' . $uuid . '/edit'); }
    if (!$prefix) { flash('error', 'Kode singkatan kategori wajib diisi (mis. CAMVIDEO).'); redirect('/categories/' . $uuid . '/edit'); }
    try {
        db()->prepare("UPDATE categories SET name=?, code_prefix=?, description=?, updated_by=? WHERE id=?")->execute([$name, $prefix, $desc, Auth::id(), (int)$id]);
        flash('success', 'Kategori diperbarui.');
        redirect('/categories');
    } catch (Throwable $e) {
        flash('error', 'Gagal memperbarui kategori (nama mungkin sudah dipakai): ' . $e->getMessage());
        redirect('/categories/' . $uuid . '/edit');
    }
}

function category_delete(string $uuid): void {
    Auth::requireRole('admin', 'admin_gudang', 'administrator_pembantu_manajemen_kategori');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('categories', $uuid);
    try { soft_delete('categories', (int)$id); flash('success','Kategori dihapus (bisa dipulihkan lewat Riwayat Terhapus).'); }
    catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('/categories');
}

// ================ Notifications ================

/**
 * Kotak masuk notifikasi (belum diarsipkan). Berlaku sama untuk semua role —
 * notifikasi memang milik masing-masing user.
 */
function notification_index(): void {
    Auth::requireLogin();
    _notification_list(false);
}

/** Arsip notifikasi: yang sudah disingkirkan dari kotak masuk, tetap bisa dibuka. */
function notification_archive_index(): void {
    Auth::requireLogin();
    _notification_list(true);
}

function _notification_list(bool $archived): void {
    $pdo = db();
    $uid = Auth::id();
    $cond = $archived ? 'archived_at IS NOT NULL' : 'archived_at IS NULL';
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND $cond ORDER BY created_at DESC LIMIT 100");
    $stmt->execute([$uid]);
    $notifs = $stmt->fetchAll();

    // Jumlah di tab lawan, supaya pengguna tahu ada isinya tanpa harus membuka.
    $other = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND "
        . ($archived ? 'archived_at IS NULL' : 'archived_at IS NOT NULL'));
    $other->execute([$uid]);

    layout('main', 'notifications/index', [
        'title'       => $archived ? 'Arsip Notifikasi' : 'Notifikasi',
        'notifs'      => $notifs,
        'isArchive'   => $archived,
        'otherCount'  => (int) $other->fetchColumn(),
        'currentPath' => '/notifications',
    ]);
}

/** Pindahkan satu notifikasi ke arsip (sekaligus ditandai dibaca). */
function notification_archive(string $id): void {
    Auth::requireLogin();
    Auth::verifyCsrf();
    // Selalu dibatasi user_id: seseorang hanya bisa mengarsip notifikasinya sendiri.
    db()->prepare("UPDATE notifications SET archived_at=NOW(), is_read=1 WHERE id=? AND user_id=? AND archived_at IS NULL")
        ->execute([(int) $id, Auth::id()]);
    flash('success', 'Notifikasi dipindahkan ke arsip.');
    redirect('/notifications');
}

/** Kembalikan satu notifikasi dari arsip ke kotak masuk. */
function notification_unarchive(string $id): void {
    Auth::requireLogin();
    Auth::verifyCsrf();
    db()->prepare("UPDATE notifications SET archived_at=NULL WHERE id=? AND user_id=? AND archived_at IS NOT NULL")
        ->execute([(int) $id, Auth::id()]);
    flash('success', 'Notifikasi dikembalikan ke kotak masuk.');
    redirect('/notifications/arsip');
}

/** Arsipkan seluruh isi kotak masuk sekaligus. */
function notification_archive_all(): void {
    Auth::requireLogin();
    Auth::verifyCsrf();
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE notifications SET archived_at=NOW(), is_read=1 WHERE user_id=? AND archived_at IS NULL");
    $stmt->execute([Auth::id()]);
    $n = $stmt->rowCount();
    if ($n === 0) flash('error', 'Tidak ada notifikasi di kotak masuk untuk diarsipkan.');
    else          flash('success', "$n notifikasi dipindahkan ke arsip.");
    redirect('/notifications');
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
    // Dibatasi ke kotak masuk saja — yang di arsip tidak ikut tersentuh.
    db()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND archived_at IS NULL")->execute([Auth::id()]);
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
    // Auth::user() sengaja hanya memuat kolom inti; Chat ID Telegram diambil terpisah.
    $tg = db()->prepare("SELECT telegram_chat_id FROM users WHERE id=?");
    $tg->execute([Auth::id()]);
    layout('main', 'users/profile', [
        'title'          => 'Profil Saya',
        'user'           => Auth::user(),
        'telegramChatId' => (string) ($tg->fetchColumn() ?: ''),
        'currentPath'    => '/profile',
    ]);
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

/** Simpan / hapus Chat ID Telegram milik user sendiri. */
function profile_telegram_post(): void {
    Auth::requireLogin();
    Auth::verifyCsrf();
    $chatId = trim((string) ($_POST['telegram_chat_id'] ?? ''));

    if ($chatId === '') {
        db()->prepare("UPDATE users SET telegram_chat_id=NULL, updated_by=? WHERE id=?")->execute([Auth::id(), Auth::id()]);
        log_audit('user.telegram_unlink', 'user', Auth::id());
        flash('success', 'Telegram diputuskan. Notifikasi tidak lagi dikirim ke Telegram Anda.');
        redirect('/profile');
    }
    if (!Telegram::isValidChatId($chatId)) {
        flash('error', 'Chat ID Telegram harus berupa angka (boleh diawali tanda minus untuk grup).');
        redirect('/profile');
    }

    db()->prepare("UPDATE users SET telegram_chat_id=?, updated_by=? WHERE id=?")->execute([$chatId, Auth::id(), Auth::id()]);
    log_audit('user.telegram_link', 'user', Auth::id());
    flash('success', 'Chat ID Telegram disimpan. Gunakan "Kirim Tes" untuk memastikan pesannya sampai.');
    redirect('/profile');
}

/** Kirim pesan uji ke Telegram user, supaya sambungannya bisa dipastikan sendiri. */
function profile_telegram_test(): void {
    Auth::requireLogin();
    Auth::verifyCsrf();
    $stmt = db()->prepare("SELECT name, telegram_chat_id FROM users WHERE id=?");
    $stmt->execute([Auth::id()]);
    $u = $stmt->fetch();

    if (empty($u['telegram_chat_id'])) {
        flash('error', 'Isi dan simpan Chat ID Telegram terlebih dahulu.');
        redirect('/profile');
    }
    [$ok, $msg] = Telegram::send(
        (string) $u['telegram_chat_id'],
        'Tes Notifikasi SIMANTAP',
        "Halo {$u['name']}, sambungan Telegram Anda berhasil. Notifikasi SIMANTAP akan dikirim ke sini.",
        '/dashboard'
    );
    flash($ok ? 'success' : 'error', $msg);
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

/** Preview Kode Aset & No. BMD otomatis untuk kategori terpilih (dipakai form tambah alat). */
function api_next_asset_code(): void {
    Auth::requireRole('admin_gudang', 'admin', 'administrator_pembantu_manajemen_alat');
    $cat = (int) ($_GET['category_id'] ?? 0);
    if (!$cat) json_response(['ok' => false, 'message' => 'Kategori belum dipilih.'], 400);
    $gen = next_asset_code($cat);
    if (!$gen) json_response(['ok' => false, 'message' => 'Kategori ini belum punya kode singkatan.'], 422);
    json_response(['ok' => true, 'asset_code' => $gen['asset_code'], 'bmn_number' => $gen['bmn_number']]);
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
