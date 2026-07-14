<?php
declare(strict_types=1);
function e($v): string {
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function old(string $key, $default = ''): string {
    return e($_SESSION['_old'][$key] ?? $default);
}

function flash(string $key, ?string $value = null) {
    if ($value !== null) { $_SESSION['_flash'][$key] = $value; return; }
    $v = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $v;
}

function redirect(string $url): void {
    // Auto-prefix BASE_PATH for absolute-path redirects
    if (defined('BASE_PATH') && BASE_PATH !== '' && str_starts_with($url, '/') && !str_starts_with($url, BASE_PATH . '/') && $url !== BASE_PATH) {
        $url = BASE_PATH . $url;
    }
    header("Location: $url");
    exit;
}

function view(string $name, array $data = []): void {
    extract($data);
    $viewFile = APP_ROOT . '/views/' . $name . '.php';
    if (!file_exists($viewFile)) {
        throw new RuntimeException("View not found: $name");
    }
    include $viewFile;
}

function layout(string $layout, string $view, array $data = []): void {
    ob_start();
    extract($data);
    include APP_ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    include APP_ROOT . '/views/layouts/' . $layout . '.php';
}

function url(string $path = ''): string {
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    if ($path === '' || $path === '/') return $base . '/';
    return $base . '/' . ltrim($path, '/');
}

function active(string $path, string $current, string $class = 'active'): string {
    if ($path === '/' && $current === '/') return $class;
    if ($path !== '/' && str_starts_with($current, $path)) return $class;
    return '';
}

function fmt_datetime(?string $dt): string {
    if (!$dt) return '—';
    try {
        return (new DateTime($dt))->format('d M Y H:i');
    } catch (Throwable) { return $dt; }
}

function fmt_rupiah($value): string {
    if ($value === null || $value === '') return '—';
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function fmt_date(?string $d): string {
    if (!$d) return '—';
    try {
        return (new DateTime($d))->format('d M Y');
    } catch (Throwable) { return $d; }
}

function generate_code(string $prefix, string $table, string $col = 'loan_code'): string {
    $year = date('Y');
    $stmt = db()->prepare("SELECT COUNT(*) c FROM $table WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $n = ((int) $stmt->fetchColumn()) + 1;
    return sprintf('%s-%s-%06d', $prefix, $year, $n);
}

/**
 * Bangun URL publik untuk foto (aset atau user) di public/uploads/{$dir}/.
 * Mengembalikan null kalau tidak ada foto.
 */
/** UUID v4 acak (RFC 4122) untuk identitas publik entitas di URL. */
function generate_uuid(): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/**
 * Terjemahkan uuid publik menjadi id integer internal untuk $table (whitelist).
 * Mengembalikan id, atau null jika tidak ditemukan / tabel tak dikenal.
 */
function uuid_to_id(string $table, string $uuid): ?int {
    $allowed = ['users', 'categories', 'assets', 'packages', 'loans', 'repairs'];
    if (!in_array($table, $allowed, true)) return null;
    $stmt = db()->prepare("SELECT id FROM $table WHERE uuid = ?");
    $stmt->execute([$uuid]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

/** Resolusi uuid->id atau hentikan dengan 404 bila tidak ada. */
function uuid_to_id_or_404(string $table, string $uuid): int {
    $id = uuid_to_id($table, $uuid);
    if ($id === null) {
        http_response_code(404);
        include APP_ROOT . '/views/errors/404.php';
        exit;
    }
    return $id;
}

function photo_url(?string $photo, string $dir = 'assets'): ?string {
    if (!$photo) return null;
    $prefix = defined('ASSET_PREFIX') ? ASSET_PREFIX : '';
    return $prefix . '/uploads/' . $dir . '/' . rawurlencode($photo);
}

/**
 * URL avatar user untuk ditampilkan. Jika user belum mengunggah foto,
 * pakai logo Diskominfo sebagai foto default (tidak pernah mengembalikan null).
 */
function user_avatar_url(?string $photo): string {
    $prefix = defined('ASSET_PREFIX') ? ASSET_PREFIX : '';
    return photo_url($photo, 'users') ?? ($prefix . '/assets/img/logo-kominfo-icon.png');
}

/**
 * Tangani upload foto dari $_FILES[$field] ke public/uploads/{$dir}/. Mengembalikan array:
 *  ['filename' => string|null, 'error' => string|null]
 * 'filename' adalah null jika tidak ada file baru yang diupload (bukan error).
 */
function handle_photo_upload(string $field, ?string $oldPhoto = null, string $dir = 'assets', string $prefix = 'asset'): array {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['filename' => null, 'error' => null]; // Tidak ada file baru diupload
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['filename' => null, 'error' => 'Gagal mengupload foto (kode error: ' . $file['error'] . ').'];
    }

    $maxBytes = 3 * 1024 * 1024; // 3 MB
    if ($file['size'] > $maxBytes) {
        return ['filename' => null, 'error' => 'Ukuran foto maksimal 3MB.'];
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $mime = $file['type'];
    }
    if (!isset($allowed[$mime])) {
        return ['filename' => null, 'error' => 'Format foto harus JPG, PNG, atau WEBP.'];
    }

    $uploadDir = APP_ROOT . '/public/uploads/' . $dir;
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }

    $ext = $allowed[$mime];
    $filename = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['filename' => null, 'error' => 'Gagal menyimpan file foto ke server.'];
    }

    // Hapus foto lama supaya tidak menumpuk file sampah
    if ($oldPhoto) {
        $oldPath = $uploadDir . '/' . basename($oldPhoto);
        if (is_file($oldPath)) { @unlink($oldPath); }
    }

    return ['filename' => $filename, 'error' => null];
}

/**
 * Hapus file foto (aset atau user) dari disk (dipakai saat user memilih "hapus foto").
 */
function delete_photo(?string $photo, string $dir = 'assets'): void {
    if (!$photo) return;
    $path = APP_ROOT . '/public/uploads/' . $dir . '/' . basename($photo);
    if (is_file($path)) { @unlink($path); }
}

function status_badge(string $status): string {
    $map = [
        // Loan
        'Pending'    => ['bg-warning text-dark', 'Menunggu'],
        'Approved'   => ['bg-info text-dark', 'Disetujui'],
        'Rejected'   => ['bg-danger', 'Ditolak'],
        'CheckedOut' => ['bg-primary', 'Dipinjam'],
        'Returned'   => ['bg-success', 'Dikembalikan'],
        'Completed'  => ['bg-secondary', 'Selesai'],
        'Cancelled'  => ['bg-dark', 'Dibatalkan'],
        // Asset
        'Available'  => ['bg-success', 'Tersedia'],
        'Booked'     => ['bg-warning text-dark', 'Dipesan'],
        'Damaged'    => ['bg-danger', 'Rusak / Perbaikan'],
        'Retired'    => ['bg-dark', 'Dihapus'],
        'Lost'       => ['bg-dark', 'Hilang'],
        // Repair
        'Open'        => ['bg-warning text-dark', 'Baru'],
        'FormPrinted' => ['bg-info text-dark', 'SPK Dicetak'],
        'InRepair'    => ['bg-primary', 'Diperbaiki'],
        // loan_items
        'Reserved'         => ['bg-warning text-dark', 'Dipesan'],
        'ReturnedGood'     => ['bg-success', 'Kembali Baik'],
        'ReturnedDamaged'  => ['bg-danger', 'Kembali Rusak'],
        'ReturnedLost'     => ['bg-dark', 'Hilang'],
        'Restored'         => ['bg-success', 'Diperbaiki'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-secondary', $status];
    return '<span class="badge ' . $cls . '">' . e($label) . '</span>';
}

/** Daftar Bidang/Unit Kerja di Diskominfo Kab. Tangerang (untuk dropdown). */
function unit_kerja_options(): array {
    return [
        'Bidang Pengelolaan Aplikasi Informatika',
        'Bidang Informasi dan Komunikasi Publik (IKP)',
        'Bidang Statistik Sektoral',
        'Bidang Penyelenggaraan Persandian untuk Keamanan Informasi',
        'Sekretariat',
    ];
}

function role_label(string $role): string {
    return [
        'superadmin' => 'Super Admin',
        'admin' => 'Administrator',
        'pemohon' => 'Pemohon',
        'supervisor' => 'Kepala Bagian / Supervisor',
        'admin_gudang' => 'Admin Gudang',
        'inventory_staff' => 'Inventory Staff',
    ][$role] ?? $role;
}

/**
 * Cek role user login untuk tampilan (menu/tombol). Superadmin otomatis lolos
 * SEMUA pengecekan role — konsisten dengan Auth::requireRole di sisi controller.
 */
function role_is(string ...$roles): bool {
    $r = Auth::role();
    if ($r === null) return false;
    return $r === 'superadmin' || in_array($r, $roles, true);
}

/**
 * Bolehkah user login mengedit/menghapus alat tertentu?
 * - superadmin / admin / admin_gudang: penuh (semua alat).
 * - inventory_staff: HANYA alat yang dia tambahkan sendiri (created_by).
 * - selain itu: tidak.
 * $createdBy = nilai kolom assets.created_by (boleh null).
 */
function inventory_can_manage($createdBy): bool {
    $r = Auth::role();
    if (in_array($r, ['superadmin', 'admin', 'admin_gudang'], true)) return true;
    if ($r === 'inventory_staff') return $createdBy !== null && (int)$createdBy === Auth::id();
    return false;
}

/** Apakah alat pernah/masih dipinjam (punya baris di loan_items)? */
function asset_has_loan_history(int $assetId): bool {
    $stmt = db()->prepare("SELECT 1 FROM loan_items WHERE asset_id = ? LIMIT 1");
    $stmt->execute([$assetId]);
    return (bool) $stmt->fetchColumn();
}

function json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function log_audit(string $action, ?string $entityType = null, $entityId = null, $details = null): void {
    try {
        $stmt = db()->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?,?,?,?,?)");
        $stmt->execute([
            Auth::id(),
            $action,
            $entityType,
            $entityId !== null ? (string) $entityId : null,
            is_string($details) ? $details : json_encode($details),
        ]);
    } catch (Throwable $e) { /* ignore */ }
}

/**
 * Soft delete satu baris: tandai deleted_at/deleted_by, tidak dihapus permanen.
 * $table selalu literal hardcoded di tiap call site, bukan input pengguna.
 */
function soft_delete(string $table, int $id): void {
    db()->prepare("UPDATE $table SET deleted_at = NOW(), deleted_by = ? WHERE id = ?")->execute([Auth::id(), $id]);
}

/** Pulihkan baris yang sudah di-soft-delete (dipakai halaman Riwayat Terhapus). */
function restore_record(string $table, int $id): void {
    // deleted_by sengaja TIDAK di-null-kan — tetap jadi jejak historis "terakhir
    // dihapus oleh X", berdampingan dengan restored_by/restored_at yang baru.
    db()->prepare("UPDATE $table SET deleted_at = NULL, restored_by = ?, restored_at = NOW() WHERE id = ?")->execute([Auth::id(), $id]);
}

/**
 * Turnstile (CAPTCHA Cloudflare) hanya aktif jika kedua key diisi di .env.
 * Kalau kosong (mis. saat dev lokal), fitur dilewati total tanpa mengganggu login.
 */
function turnstile_enabled(): bool {
    return TURNSTILE_SITE_KEY !== '' && TURNSTILE_SECRET_KEY !== '';
}

/**
 * Verifikasi token Turnstile ke server Cloudflare. Mengembalikan true jika valid.
 * Fail-closed: kalau aktif tapi token kosong/verifikasi gagal -> false (login ditolak).
 */
function turnstile_verify(string $token): bool {
    if (TURNSTILE_SECRET_KEY === '') return true; // tidak diaktifkan -> lewati
    if ($token === '') return false;
    $data = http_build_query([
        'secret'   => TURNSTILE_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $result = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
    }
    if ($result === false) {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $data,
            'timeout' => 8,
        ]]);
        $result = @file_get_contents($url, false, $ctx);
    }
    if (!$result) return false;
    $json = json_decode((string) $result, true);
    return is_array($json) && !empty($json['success']);
}

/**
 * Render baris kecil "Dibuat oleh X · Diubah oleh Y · Dipulihkan oleh Z" dari
 * kolom created_by_name/updated_by_name/restored_by_name (hasil JOIN ke users)
 * yang ada di $row. Dipakai di halaman detail/edit tiap entitas ber-audit-trail.
 */
function audit_trail_info(array $row): string {
    $parts = [];
    if (!empty($row['created_by_name'])) {
        $parts[] = 'Dibuat: ' . e($row['created_by_name']) . ' · ' . fmt_datetime($row['created_at'] ?? null);
    }
    if (!empty($row['updated_by_name'])) {
        $parts[] = 'Diubah: ' . e($row['updated_by_name']) . ' · ' . fmt_datetime($row['updated_at'] ?? null);
    }
    if (!empty($row['restored_by_name'])) {
        $parts[] = 'Dipulihkan: ' . e($row['restored_by_name']) . ' · ' . fmt_datetime($row['restored_at'] ?? null);
    }
    if (!$parts) return '';
    return '<div class="text-slate small mt-2" data-testid="audit-trail-info">' . implode(' &middot; ', $parts) . '</div>';
}
