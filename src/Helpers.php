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
function photo_url(?string $photo, string $dir = 'assets'): ?string {
    if (!$photo) return null;
    $prefix = defined('ASSET_PREFIX') ? ASSET_PREFIX : '';
    return $prefix . '/uploads/' . $dir . '/' . rawurlencode($photo);
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

function role_label(string $role): string {
    return [
        'admin' => 'Administrator',
        'pemohon' => 'Pemohon',
        'supervisor' => 'Kepala Bagian / Supervisor',
        'admin_gudang' => 'Admin Gudang',
    ][$role] ?? $role;
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
