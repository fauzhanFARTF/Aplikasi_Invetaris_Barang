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

/**
 * Kode peminjaman: APTIKA-YYYYMMDD-NNN, NNN mulai dari 001 setiap hari.
 * Tanggalnya = hari pembuatan (created_at), jadi nomor urut otomatis mengulang
 * dari awal di hari berikutnya.
 *
 * Nomor diambil dari MAX yang sudah ada pada hari itu (+1), bukan COUNT, supaya
 * penghapusan baris tidak membuat nomor terpakai ulang. Baris yang di-soft-delete
 * ikut dihitung karena UNIQUE constraint tetap berlaku untuk mereka.
 */
function next_loan_code(?string $day = null): string {
    $day    = $day ?: date('Ymd');
    $prefix = "APTIKA-$day-";
    $q = db()->prepare("SELECT loan_code FROM loans WHERE loan_code LIKE ?");
    $q->execute([$prefix . '%']);
    $max = 0;
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $code) {
        if (preg_match('/-(\d+)$/', (string) $code, $m)) {
            $n = (int) $m[1];
            if ($n > $max) $max = $n;
        }
    }
    return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
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
/**
 * Buat Kode Aset & No. BMD otomatis dari kode singkatan kategori.
 * Contoh: prefix CAMVIDEO -> asset_code "CAMVIDEO-001", bmd "BMD-2026-CAMVIDEO-001".
 * Nomor urut = angka tertinggi yang sudah ada untuk prefix tsb + 1 (menghindari
 * tabrakan meski ada aset yang terhapus). Mengembalikan null jika kategori tidak
 * punya kode singkatan.
 */
function next_asset_code(int $categoryId): ?array {
    $stmt = db()->prepare("SELECT code_prefix FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $prefix = strtoupper(trim((string) ($stmt->fetchColumn() ?: '')));
    if ($prefix === '') return null;

    $q = db()->prepare("SELECT asset_code FROM assets WHERE asset_code LIKE ?");
    $q->execute([$prefix . '-%']);
    $max = 0;
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $code) {
        if (preg_match('/-(\d+)$/', (string) $code, $m)) {
            $n = (int) $m[1];
            if ($n > $max) $max = $n;
        }
    }
    $seq = $max + 1;
    $pad = str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    return [
        'prefix'     => $prefix,
        'seq'        => $seq,
        'asset_code' => "$prefix-$pad",
        'bmn_number' => 'BMD-' . date('Y') . "-$prefix-$pad",
    ];
}

/**
 * Cocokkan hasil pindai QR dengan alat, menerima awalan BMN- maupun BMD-.
 *
 * Nomor BMN sudah diganti jadi BMD, tapi stiker QR yang terlanjur tertempel di
 * alat masih berisi "BMN-...". Tanpa toleransi ini, seluruh stiker lama mati dan
 * penyerahan/pengembalian di lapangan gagal. Mengembalikan daftar kandidat yang
 * dicoba berurutan.
 */
function barcode_candidates(string $scanned): array {
    $scanned = trim($scanned);
    $out = [$scanned];
    if (stripos($scanned, 'BMN-') === 0) $out[] = 'BMD-' . substr($scanned, 4);
    elseif (stripos($scanned, 'BMD-') === 0) $out[] = 'BMN-' . substr($scanned, 4);
    return $out;
}

/**
 * URL aset lokal + penanda versi dari waktu ubah file (mis. app.css?v=1750...).
 *
 * Tanpa ini, nama file tidak pernah berubah sehingga browser terus memakai versi
 * lama dari cache setiap kali CSS/JS diperbarui — pengguna harus hard refresh
 * manual, dan kalau tidak, halaman bisa tampil rusak (HTML baru + CSS lama).
 * Nilai v berubah sendiri setiap file diubah, jadi cache batal secara otomatis.
 */
function asset_url(string $path): string {
    $path = '/' . ltrim($path, '/');
    $mtime = @filemtime(APP_ROOT . '/public' . $path);
    return ASSET_PREFIX . $path . ($mtime ? '?v=' . $mtime : '');
}

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
/**
 * Ubah URL berbagi Google Drive menjadi URL unduhan langsung agar bisa diambil
 * sebagai gambar. Contoh:
 *   https://drive.google.com/file/d/FILEID/view?usp=sharing
 *   https://drive.google.com/open?id=FILEID
 *   -> https://drive.google.com/uc?export=download&id=FILEID
 * URL lain dikembalikan apa adanya.
 */
function normalize_image_url(string $url): string {
    if (preg_match('#drive\.google\.com/file/d/([A-Za-z0-9_-]+)#', $url, $m)) {
        return 'https://drive.google.com/uc?export=download&id=' . $m[1];
    }
    if (preg_match('#drive\.google\.com/(?:open|uc)\?[^ ]*id=([A-Za-z0-9_-]+)#', $url, $m)) {
        return 'https://drive.google.com/uc?export=download&id=' . $m[1];
    }
    return $url;
}

/**
 * Unduh gambar dari sebuah URL (mis. link Google Drive / URL gambar langsung),
 * validasi tipe & ukuran, lalu simpan ke public/uploads/{$dir}/ seperti hasil
 * upload biasa. Mengembalikan ['filename' => string|null, 'error' => string|null].
 * filename null tanpa error = tidak ada URL diberikan.
 */
function handle_photo_from_url(string $url, ?string $oldPhoto = null, string $dir = 'assets', string $prefix = 'asset'): array {
    $url = trim($url);
    if ($url === '') return ['filename' => null, 'error' => null];
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        return ['filename' => null, 'error' => 'Link foto tidak valid (harus diawali http:// atau https://).'];
    }

    $fetchUrl = normalize_image_url($url);
    $maxBytes = 3 * 1024 * 1024; // 3 MB
    $data = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($fetchUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'SIMANTAP-BMN/1.0',
            CURLOPT_BUFFERSIZE => 65536,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($ch, $dlTotal, $dlNow) use ($maxBytes) {
                return ($dlTotal > $maxBytes || $dlNow > $maxBytes) ? 1 : 0; // batalkan bila terlalu besar
            },
        ]);
        $data = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($data === false || $data === '') {
            return ['filename' => null, 'error' => 'Gagal mengunduh foto dari link' . ($err ? ": $err" : ($code ? " (HTTP $code)" : '') ) . '.'];
        }
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 20, 'follow_location' => 1]]);
        $data = @file_get_contents($fetchUrl, false, $ctx, 0, $maxBytes + 1);
        if ($data === false) return ['filename' => null, 'error' => 'Gagal mengunduh foto dari link.'];
    }

    if (strlen($data) > $maxBytes) {
        return ['filename' => null, 'error' => 'Ukuran foto dari link maksimal 3MB.'];
    }

    // Validasi bahwa isinya benar-benar gambar (JPG/PNG/WEBP).
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($finfo, $data);
        finfo_close($finfo);
    }
    if (!isset($allowed[$mime])) {
        return ['filename' => null, 'error' => 'Link tidak mengarah ke gambar JPG/PNG/WEBP (untuk Google Drive, pastikan file dibagikan publik / "siapa saja yang memiliki link").'];
    }

    $uploadDir = APP_ROOT . '/public/uploads/' . $dir;
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
    $filename = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (file_put_contents($uploadDir . '/' . $filename, $data) === false) {
        return ['filename' => null, 'error' => 'Gagal menyimpan foto ke server.'];
    }

    if ($oldPhoto) {
        $oldPath = $uploadDir . '/' . basename($oldPhoto);
        if (is_file($oldPath)) { @unlink($oldPath); }
    }
    return ['filename' => $filename, 'error' => null];
}

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
        'Habis'      => ['bg-dark', 'Habis'],
        'AtOpd'      => ['bg-info text-dark', 'Di OPD'],
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

/**
 * Daftar OPD Kabupaten Tangerang untuk dropdown peminjaman "Untuk OPD".
 * SEMENTARA kosong — menunggu daftar resmi dari Diskominfo. Selama kosong, form
 * OPD menampilkan isian teks bebas sebagai gantinya (lihat views/loans/create.php),
 * jadi fiturnya tetap bisa dipakai tanpa mengarang nama instansi.
 *
 * Kalau diisi, boleh dikelompokkan: ['Dinas' => [...], 'Kecamatan' => [...]]
 * akan dirender sebagai <optgroup>. Daftar rata (['A','B']) juga didukung.
 */
function opd_options(): array {
    return [
        // 'Dinas' => ['Dinas Komunikasi dan Informatika', ...],
        // 'Badan' => [...],
        // 'Kecamatan' => [...],
    ];
}

/** Apakah alat ini dilacak stoknya (kabel meteran, RJ45 bungkusan)? */
function asset_is_stock(array $asset): bool {
    return !empty($asset['unit']);
}

/** Tampilkan stok alat berstok, mis. "250 meter" atau "40 butir". */
function fmt_stock($qty, ?string $unit): string {
    if ($unit === null || $unit === '') return '—';
    $q = (float) $qty;
    // Buang desimal .00 agar "250.00 meter" tampil "250 meter".
    $qStr = $q == (int) $q ? (string) (int) $q : rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.');
    return $qStr . ' ' . $unit;
}

function role_label(string $role): string {
    return [
        'superadmin' => 'Super Admin',
        'admin' => 'Administrator',
        'pemohon' => 'Personel Luar',
        'supervisor' => 'Staff Approval',
        'admin_gudang' => 'Admin Gudang',
        'inventory_staff' => 'IT Staff',
        'it_staff_pembantu' => 'IT Staff Pembantu',
        'administrator_pembantu_manajemen_user' => 'Administrator Pembantu — Manajemen User',
        'administrator_pembantu_manajemen_alat' => 'Administrator Pembantu — Manajemen Alat',
        'administrator_pembantu_manajemen_kategori' => 'Administrator Pembantu — Manajemen Kategori',
        'pimpinan' => 'Pimpinan',
    ][$role] ?? $role;
}

/**
 * Cek role user login untuk tampilan (menu/tombol). Superadmin otomatis lolos
 * SEMUA pengecekan role — konsisten dengan Auth::requireRole di sisi controller.
 */
function role_is(string ...$roles): bool {
    if (!Auth::check()) return false;
    return Auth::hasRole('superadmin') || Auth::hasRole(...$roles);
}

/**
 * Bolehkah user login mengelola (tambah/edit/hapus) alat?
 * Pengelola alat: superadmin, admin, admin_gudang, administrator_pembantu_manajemen_alat.
 * (IT Staff & pemohon TIDAK bisa mengelola alat — hanya melihat.)
 * $createdBy dipertahankan di signature untuk kompatibilitas pemanggil.
 */
function inventory_can_manage($createdBy = null): bool {
    return Auth::hasRole('superadmin', 'admin', 'admin_gudang', 'administrator_pembantu_manajemen_alat');
}

/**
 * URL foto alat untuk ditampilkan. Jika alat belum punya foto, pakai logo
 * Diskominfo sebagai gambar default (tidak pernah mengembalikan null).
 */
function asset_photo_url(?string $photo): string {
    $prefix = defined('ASSET_PREFIX') ? ASSET_PREFIX : '';
    return photo_url($photo, 'assets') ?? ($prefix . '/assets/img/logo-kominfo-icon.png');
}

/**
 * Peran yang meng-elevasi hak lihat peminjaman (boleh melihat SEMUA peminjaman).
 * Dipakai untuk menentukan apakah user dibatasi hanya melihat miliknya sendiri.
 */
function _loan_elevated_roles(): array {
    return ['admin', 'supervisor', 'admin_gudang', 'pimpinan', 'superadmin'];
}

/**
 * User "requester murni": hanya bisa melihat peminjaman miliknya sendiri.
 * Yaitu punya peran pemohon/IT Staff dan TIDAK punya peran yang lebih tinggi.
 */
function role_is_requester(): bool {
    return Auth::hasRole('pemohon', 'inventory_staff') && !Auth::hasRole(..._loan_elevated_roles());
}

/**
 * Boleh membuka detail sebuah peminjaman?
 *
 * Kartu "Jadwal Hari Ini & Selanjutnya" dan "Jadwal yang Telah Lewat" menampilkan
 * seluruh acara ke semua role, jadi IT Staff perlu bisa membuka isinya — mereka
 * bekerja bersama di lapangan. Personel Luar (pemohon murni) tetap hanya bisa
 * melihat peminjamannya sendiri karena sifatnya pribadi.
 *
 * Ini murni hak LIHAT. Membatalkan acara / mengeluarkan alat tetap dijaga
 * terpisah dan hanya boleh oleh pemohon acara itu sendiri atau admin.
 */
function can_view_loan(int $requesterId): bool {
    if (!role_is_requester()) return true;              // admin, staff approval, gudang, pimpinan, superadmin
    if (Auth::hasRole('inventory_staff')) return true;  // sesama IT Staff
    return $requesterId === Auth::id();                 // Personel Luar: hanya miliknya
}

/**
 * Peminjam pribadi (pemohon murni) — peminjaman untuk keperluan pribadi tanpa
 * personel yang dilibatkan. IT Staff & peran lain tetap boleh melibatkan personel.
 */
function is_personal_borrower(): bool {
    return Auth::hasRole('pemohon')
        && !Auth::hasRole('inventory_staff', 'admin', 'supervisor', 'admin_gudang', 'pimpinan', 'superadmin');
}

/**
 * Alat yang MASIH DIPINJAM (sudah keluar gudang, belum kembali) beserta
 * penanggung jawab (pemohon) dan personel yang dilibatkan.
 *
 * Sengaja hanya item_status 'CheckedOut'. Barang "Di OPD" (AtOpd) TIDAK ikut —
 * barang itu punya halaman sendiri (Barang di OPD) dan tidak digabung dengan
 * alat lain, sesuai alur Kebutuhan Jaringan.
 *
 * @param array|null $userIds Bila diisi, hanya alat yang dipegang orang-orang ini
 *                            (sebagai pemohon ATAU personel yang dilibatkan).
 * @param int|null   $excludeLoanId Lewati peminjaman ini (mis. yang sedang dipindai).
 */
function borrowed_items(?array $userIds = null, ?int $excludeLoanId = null): array {
    $where  = ["li.item_status = 'CheckedOut'", 'l.deleted_at IS NULL'];
    $params = [];
    if ($excludeLoanId !== null) { $where[] = 'l.id <> ?'; $params[] = $excludeLoanId; }
    if ($userIds !== null) {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) return [];
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $where[] = "(l.requester_id IN ($in) OR EXISTS (SELECT 1 FROM loan_participants lp
                        WHERE lp.loan_id = l.id AND lp.user_id IN ($in)))";
        $params = array_merge($params, $userIds, $userIds);
    }
    $sql = "SELECT a.name AS asset_name, a.asset_code, a.bmn_number,
                   l.uuid AS loan_uuid, l.loan_code, l.event_name, l.loan_type,
                   l.checkout_at, l.end_date, li.expected_return_date,
                   u.name AS requester_name,
                   (SELECT GROUP_CONCAT(pu.name ORDER BY pu.name SEPARATOR ', ')
                      FROM loan_participants lp JOIN users pu ON pu.id = lp.user_id
                     WHERE lp.loan_id = l.id) AS personnel
            FROM loan_items li
            JOIN loans l  ON l.id = li.loan_id
            JOIN assets a ON a.id = li.asset_id
            JOIN users u  ON u.id = l.requester_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY l.checkout_at DESC, a.name";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Pemohon + personel yang dilibatkan pada sebuah peminjaman (untuk borrowed_items). */
function loan_people_ids(int $loanId): array {
    $stmt = db()->prepare("SELECT requester_id AS id FROM loans WHERE id = ?
                           UNION SELECT user_id FROM loan_participants WHERE loan_id = ?");
    $stmt->execute([$loanId, $loanId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Akun bawaan per-role yang disembunyikan dari daftar user & pilihan personel
 * untuk semua role kecuali Super Admin. Akun-akun ini tetap bisa login dan
 * riwayat peminjamannya tetap tampil apa adanya.
 */
function hidden_user_emails(): array {
    return [
        'superadmin@tangerangkab.go.id',
        'admingudang@tangerangkab.go.id',
        // Domainnya memang terbalik (kabtangerang, bukan tangerangkab) — ini akun
        // yang benar-benar ada di server, bukan salah ketik. Versi di atas
        // dipertahankan: mendaftarkan email yang tidak ada tidak berefek apa pun.
        'admingudang@kabtangerang.go.id',
        'staffapproval@tangerangkab.go.id',
        'supervisor@tangerangkab.go.id',
        'pemohon@tangerangkab.go.id',
        'itstaff@tangerangkab.go.id',
        'itstaffpembantumanajemenuser@tangerangkab.go.id',
        'itstaffpembantumanajemenalat@tangerangkab.go.id',
        'itstaffpembantumanajemenkategori@tangerangkab.go.id',
    ];
}

/**
 * Potongan SQL untuk menyembunyikan akun-akun di atas. Kosong bila yang login
 * Super Admin — ia tetap melihat semuanya. $alias = alias tabel users di query
 * pemanggil. Nilainya konstanta di kode, bukan input user.
 */
function hidden_users_sql(string $alias = 'u'): string {
    if (Auth::hasRole('superadmin')) return '';
    $list = implode(',', array_map(fn ($e) => db()->quote($e), hidden_user_emails()));
    return " AND $alias.email NOT IN ($list)";
}

/**
 * User yang berperan IT Staff — baik sebagai peran utama (users.role) maupun
 * peran tambahan (user_roles). Sumber tunggal untuk pilihan "Personel yang
 * Dilibatkan" sekaligus validasinya saat peminjaman disimpan.
 *
 * $excludeUserId dipakai untuk mengeluarkan pemohon itu sendiri — ia sudah
 * tercatat sebagai penanggungjawab, jadi tidak perlu dilibatkan lagi.
 */
function it_staff_users(?int $excludeUserId = null): array {
    $base = "SELECT u.id, u.name, u.unit_kerja FROM users u WHERE ";
    $tail = " AND u.is_active = 1 AND u.deleted_at IS NULL" . hidden_users_sql('u') . " ORDER BY u.name";
    try {
        $rows = db()->query($base . "(u.role = 'inventory_staff'
                OR EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role = 'inventory_staff'))" . $tail)->fetchAll();
    } catch (Throwable $e) {
        // Tabel user_roles mungkin belum ada saat migrasi awal.
        $rows = db()->query($base . "u.role = 'inventory_staff'" . $tail)->fetchAll();
    }
    if ($excludeUserId !== null) {
        $rows = array_values(array_filter($rows, fn ($u) => (int) $u['id'] !== $excludeUserId));
    }
    return $rows;
}

/** Saring $ids, sisakan hanya yang benar-benar berperan IT Staff. */
function it_staff_filter_ids(array $ids, ?int $excludeUserId = null): array {
    $allowed = array_map('intval', array_column(it_staff_users($excludeUserId), 'id'));
    return array_values(array_intersect(array_map('intval', $ids), $allowed));
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
