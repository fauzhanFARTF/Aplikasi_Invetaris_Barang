<?php
declare(strict_types=1);
// Login dengan Google + pendaftaran mandiri (IT Staff / Personel Luar) yang
// baru aktif setelah diverifikasi Administrator.

/** Role yang boleh dipilih sendiri saat mendaftar. Sengaja hanya dua. */
function _register_roles(): array
{
    return ['inventory_staff', 'pemohon'];
}

function google_start(): void
{
    if (!Google::enabled()) { flash('error', 'Login dengan Google belum dikonfigurasi.'); redirect('/login'); }
    header('Location: ' . Google::authUrl());
    exit;
}

function google_callback(): void
{
    if (!Google::enabled()) { flash('error', 'Login dengan Google belum dikonfigurasi.'); redirect('/login'); }

    if (isset($_GET['error'])) { flash('error', 'Login dengan Google dibatalkan.'); redirect('/login'); }
    if (!Google::verifyState($_GET['state'] ?? null)) {
        flash('error', 'Sesi login Google tidak valid. Silakan coba lagi.');
        redirect('/login');
    }
    $code = (string) ($_GET['code'] ?? '');
    if ($code === '') { flash('error', 'Login dengan Google gagal.'); redirect('/login'); }

    $profile = Google::fetchProfile($code);
    if (!$profile) { flash('error', 'Tidak bisa mengambil data akun Google Anda. Coba lagi.'); redirect('/login'); }

    google_route_profile(Google::resolveProfile($profile), $profile);
}

/**
 * Terjemahkan hasil Google::resolveProfile() jadi respons HTTP.
 * Dipisah dari google_callback() supaya bisa dipanggil tanpa jaringan.
 */
function google_route_profile(array $res, array $profile): void
{
    switch ($res['action']) {
        case 'login':
            Auth::issueToken($res['user']);
            log_audit('auth.login_google', 'user', (int) $res['user']['id']);
            redirect('/dashboard');

        case 'register':
            // Profil ditahan di session; form pendaftaran memakainya sebagai isian awal.
            $_SESSION['google_pending_profile'] = $profile;
            redirect('/daftar');

        case 'pending':
            redirect('/daftar/menunggu');

        case 'rejected':
            flash('error', 'Pendaftaran Anda tidak disetujui oleh Administrator. Silakan hubungi Diskominfo.');
            redirect('/login');

        case 'inactive':
            flash('error', 'Akun Anda dinonaktifkan. Silakan hubungi Administrator.');
            redirect('/login');

        default:
            flash('error', $res['message'] ?? 'Login dengan Google gagal.');
            redirect('/login');
    }
}

function register_form(): void
{
    $profile = $_SESSION['google_pending_profile'] ?? null;
    if (!$profile) { flash('error', 'Silakan masuk dengan Google terlebih dahulu.'); redirect('/login'); }
    layout('auth', 'auth/register', ['title' => 'Lengkapi Pendaftaran', 'profile' => $profile]);
}

function register_submit(): void
{
    Auth::verifyCsrf();
    $profile = $_SESSION['google_pending_profile'] ?? null;
    if (!$profile) { flash('error', 'Silakan masuk dengan Google terlebih dahulu.'); redirect('/login'); }

    $name = trim((string) ($_POST['name'] ?? ''));
    $role = (string) ($_POST['role'] ?? '');
    $unit = _capture_unit_kerja(); // dipakai bersama form Manajemen User
    $phone = trim((string) ($_POST['phone'] ?? ''));

    if ($name === '')                            { flash('error', 'Nama lengkap wajib diisi.'); redirect('/daftar'); }
    if (!in_array($role, _register_roles(), true)) { flash('error', 'Role yang dipilih tidak valid.'); redirect('/daftar'); }

    $pdo = db();
    // Email/google_id bisa saja sudah terdaftar bila form dikirim dua kali.
    $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? OR google_id = ? LIMIT 1");
    $dup->execute([$profile['email'], $profile['sub']]);
    if ($dup->fetch()) { redirect('/daftar/menunggu'); }

    try {
        // password_hash NULL = akun ini hanya bisa masuk lewat Google.
        // reg_status 'pending' = belum bisa login sampai Administrator menyetujui.
        $stmt = $pdo->prepare("INSERT INTO users (uuid, name, email, google_id, password_hash, role, phone, unit_kerja, photo, is_active, reg_status)
                               VALUES (?,?,?,?,NULL,?,?,?,?,1,'pending')");
        $stmt->execute([
            generate_uuid(), $name, $profile['email'], $profile['sub'],
            $role, $phone ?: null, $unit ?: null, $profile['picture'] ?: null,
        ]);
        $newId = (int) $pdo->lastInsertId();
        unset($_SESSION['google_pending_profile']);

        log_audit('auth.register_google', 'user', $newId, ['email' => $profile['email'], 'role' => $role]);
        Notification::pushToRole('admin', 'Pendaftaran Baru Menunggu Verifikasi',
            "$name ({$profile['email']}) mendaftar sebagai " . role_label($role) . '. Tinjau di menu Verifikasi Pendaftaran.',
            '/registrations');
        redirect('/daftar/menunggu');
    } catch (Throwable $e) {
        flash('error', 'Pendaftaran gagal: ' . $e->getMessage());
        redirect('/daftar');
    }
}

function register_pending(): void
{
    layout('auth', 'auth/register_pending', ['title' => 'Menunggu Verifikasi']);
}

// ── Verifikasi oleh Administrator ────────────────────────────────────────────

function registration_index(): void
{
    Auth::requireRole('admin', 'administrator_pembantu_manajemen_user');
    $rows = db()->query("SELECT u.*, v.name AS verified_by_name
                         FROM users u LEFT JOIN users v ON v.id = u.verified_by
                         WHERE u.reg_status IN ('pending','rejected') AND u.deleted_at IS NULL
                         ORDER BY FIELD(u.reg_status,'pending','rejected'), u.created_at DESC")->fetchAll();
    layout('main', 'registrations/index', [
        'title' => 'Verifikasi Pendaftaran',
        'rows' => $rows,
        'currentPath' => '/registrations',
    ]);
}

function registration_approve(string $uuid): void
{
    Auth::requireRole('admin', 'administrator_pembantu_manajemen_user');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('users', $uuid);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT name, email, reg_status FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) { flash('error', 'Pendaftar tidak ditemukan.'); redirect('/registrations'); }

    $pdo->prepare("UPDATE users SET reg_status='approved', is_active=1, verified_by=?, verified_at=NOW(), updated_by=? WHERE id=?")
        ->execute([Auth::id(), Auth::id(), $id]);
    log_audit('registration.approve', 'user', $id, ['email' => $u['email']]);
    Notification::push($id, 'Pendaftaran Disetujui',
        'Pendaftaran Anda telah disetujui. Silakan masuk dengan Google.', '/dashboard');
    flash('success', "Pendaftaran {$u['name']} disetujui. Yang bersangkutan sudah bisa masuk.");
    redirect('/registrations');
}

function registration_reject(string $uuid): void
{
    Auth::requireRole('admin', 'administrator_pembantu_manajemen_user');
    Auth::verifyCsrf();
    $id = uuid_to_id_or_404('users', $uuid);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    if (!$u) { flash('error', 'Pendaftar tidak ditemukan.'); redirect('/registrations'); }

    $pdo->prepare("UPDATE users SET reg_status='rejected', is_active=0, verified_by=?, verified_at=NOW(), updated_by=? WHERE id=?")
        ->execute([Auth::id(), Auth::id(), $id]);
    log_audit('registration.reject', 'user', $id, ['email' => $u['email']]);
    flash('success', "Pendaftaran {$u['name']} ditolak.");
    redirect('/registrations');
}

/** Jumlah pendaftaran yang menunggu — dipakai lencana di sidebar. */
function pending_registration_count(): int
{
    if (!role_is('admin', 'administrator_pembantu_manajemen_user')) return 0;
    try {
        return (int) db()->query("SELECT COUNT(*) FROM users WHERE reg_status = 'pending' AND deleted_at IS NULL")->fetchColumn();
    } catch (Throwable $e) {
        return 0; // kolom mungkin belum ada saat migrasi pertama
    }
}
