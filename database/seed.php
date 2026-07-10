<?php
// Seed data script - run: php /app/php_app/database/seed.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pdo = db();

echo "Seeding data...\n";

// -------- Users --------
$users = [
    ['name' => 'Administrator Sistem', 'email' => 'admin@diskominfo.tangerangkab.go.id', 'password' => 'admin123', 'role' => 'admin',        'phone' => '021-000-1111', 'unit_kerja' => 'Diskominfo — IT'],
    ['name' => 'Andi Pratama (Tim Liputan)', 'email' => 'andi@diskominfo.tangerangkab.go.id', 'password' => 'pemohon123', 'role' => 'pemohon', 'phone' => '0812-1111-2222', 'unit_kerja' => 'Diskominfo — Multimedia'],
    ['name' => 'Siti Rahayu (Tim Liputan)', 'email' => 'siti@diskominfo.tangerangkab.go.id', 'password' => 'pemohon123', 'role' => 'pemohon', 'phone' => '0812-3333-4444', 'unit_kerja' => 'Diskominfo — Multimedia'],
    ['name' => 'Budi Santoso (Kepala Bagian)', 'email' => 'budi@diskominfo.tangerangkab.go.id', 'password' => 'supervisor123', 'role' => 'supervisor', 'phone' => '0812-5555-6666', 'unit_kerja' => 'Diskominfo — Kabag Publikasi'],
    ['name' => 'Dewi Lestari (Admin Gudang)', 'email' => 'dewi@diskominfo.tangerangkab.go.id', 'password' => 'gudang123', 'role' => 'admin_gudang', 'phone' => '0812-7777-8888', 'unit_kerja' => 'Diskominfo — Gudang Aset'],
];

$stmt = $pdo->prepare("INSERT INTO users (name,email,password_hash,role,phone,unit_kerja) VALUES (?,?,?,?,?,?)");
foreach ($users as $u) {
    $stmt->execute([$u['name'], $u['email'], password_hash($u['password'], PASSWORD_BCRYPT), $u['role'], $u['phone'], $u['unit_kerja']]);
}
echo "  ✓ Users seeded\n";

// -------- Categories --------
$cats = [
    'Kamera Video' => 'Kamera video / camcorder / DSLR',
    'Lensa' => 'Lensa kamera',
    'Tripod & Gimbal' => 'Penyangga & stabilizer',
    'Audio' => 'Microphone, mixer, recorder',
    'Lighting' => 'Lampu LED & softbox',
    'Streaming Hardware' => 'Capture card, encoder, switcher',
    'Aksesoris' => 'Kabel, memory card, baterai',
];
$stmt = $pdo->prepare("INSERT INTO categories (name,description) VALUES (?,?)");
foreach ($cats as $n => $d) { $stmt->execute([$n, $d]); }
$catIds = $pdo->query("SELECT id,name FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
$catIds = array_flip($catIds);
echo "  ✓ Categories seeded\n";

// -------- Assets --------
// Foto contoh per kategori (file sudah disiapkan di public/uploads/assets/).
// Silakan ganti dengan foto asli inventaris Anda kapan saja lewat menu "Ubah Alat".
$categoryPhotos = [
    'Kamera Video'        => 'cat_kamera_video.jpg',
    'Lensa'                => 'cat_lensa.jpg',
    'Tripod & Gimbal'      => 'cat_tripod.jpg',
    'Audio'                => 'cat_audio.jpg',
    'Lighting'             => 'cat_lighting.jpg',
    'Streaming Hardware'   => 'cat_streaming.jpg',
    'Aksesoris'            => 'cat_aksesoris.jpg',
];
$assets = [
    ['CAM-001', 'BMN-2024-KMR-001', 'Sony PXW-Z150 Camcorder', 'Kamera Video', 'Sony', 'PXW-Z150', 'SN-CAM-001'],
    ['CAM-002', 'BMN-2024-KMR-002', 'Sony Alpha A7 III', 'Kamera Video', 'Sony', 'ILCE-7M3', 'SN-CAM-002'],
    ['CAM-003', 'BMN-2024-KMR-003', 'Canon EOS R6', 'Kamera Video', 'Canon', 'EOS R6', 'SN-CAM-003'],
    ['LNS-001', 'BMN-2024-LNS-001', 'Sony FE 24-70mm f/2.8 GM', 'Lensa', 'Sony', 'SEL2470GM', 'SN-LNS-001'],
    ['LNS-002', 'BMN-2024-LNS-002', 'Canon RF 24-105mm f/4 L', 'Lensa', 'Canon', 'RF24-105L', 'SN-LNS-002'],
    ['TRP-001', 'BMN-2024-TRP-001', 'Manfrotto MVK502 Tripod', 'Tripod & Gimbal', 'Manfrotto', 'MVK502', 'SN-TRP-001'],
    ['TRP-002', 'BMN-2024-TRP-002', 'DJI RS3 Pro Gimbal', 'Tripod & Gimbal', 'DJI', 'RS3 Pro', 'SN-TRP-002'],
    ['AUD-001', 'BMN-2024-AUD-001', 'Rode Wireless GO II', 'Audio', 'Rode', 'Wireless GO II', 'SN-AUD-001'],
    ['AUD-002', 'BMN-2024-AUD-002', 'Zoom H6 Recorder', 'Audio', 'Zoom', 'H6', 'SN-AUD-002'],
    ['AUD-003', 'BMN-2024-AUD-003', 'Yamaha MG10XU Mixer', 'Audio', 'Yamaha', 'MG10XU', 'SN-AUD-003'],
    ['LGT-001', 'BMN-2024-LGT-001', 'Godox SL-60W LED', 'Lighting', 'Godox', 'SL-60W', 'SN-LGT-001'],
    ['LGT-002', 'BMN-2024-LGT-002', 'Aputure Amaran 100D', 'Lighting', 'Aputure', 'AL-100D', 'SN-LGT-002'],
    ['STR-001', 'BMN-2024-STR-001', 'Blackmagic ATEM Mini Pro', 'Streaming Hardware', 'Blackmagic', 'ATEM Mini Pro', 'SN-STR-001'],
    ['STR-002', 'BMN-2024-STR-002', 'Elgato Cam Link 4K', 'Streaming Hardware', 'Elgato', 'Cam Link 4K', 'SN-STR-002'],
    ['ACC-001', 'BMN-2024-ACC-001', 'SanDisk Extreme Pro 128GB', 'Aksesoris', 'SanDisk', 'SDXC-128', 'SN-ACC-001'],
    ['ACC-002', 'BMN-2024-ACC-002', 'Baterai NP-FZ100 Cadangan', 'Aksesoris', 'Sony', 'NP-FZ100', 'SN-ACC-002'],
];
$stmt = $pdo->prepare("INSERT INTO assets (asset_code,bmn_number,name,category_id,brand,model,serial_number,barcode,photo,status,condition_note) VALUES (?,?,?,?,?,?,?,?,?, 'Available', 'Kondisi baik saat awal registrasi')");
foreach ($assets as $a) {
    $photo = $categoryPhotos[$a[3]] ?? null;
    $stmt->execute([$a[0], $a[1], $a[2], $catIds[$a[3]], $a[4], $a[5], $a[6], $a[1], $photo]); // barcode == BMN number
}
echo "  ✓ Assets seeded (dengan foto contoh per kategori)\n";

// -------- Packages --------
$assetIds = $pdo->query("SELECT asset_code,id FROM assets")->fetchAll(PDO::FETCH_KEY_PAIR);
$packages = [
    ['name' => 'Paket Live Streaming Rapat Dinas', 'desc' => 'Setup lengkap untuk streaming rapat internal', 'items' => ['CAM-001','TRP-001','AUD-003','STR-001','ACC-001']],
    ['name' => 'Paket Liputan Lapangan (Outdoor)', 'desc' => 'Setup ringkas untuk peliputan di luar ruangan', 'items' => ['CAM-002','LNS-001','TRP-002','AUD-001','LGT-001']],
    ['name' => 'Paket Wawancara Studio', 'desc' => 'Setup wawancara satu narasumber di studio', 'items' => ['CAM-003','LNS-002','TRP-001','AUD-002','LGT-002']],
];
$pStmt = $pdo->prepare("INSERT INTO packages (name,description) VALUES (?,?)");
$piStmt = $pdo->prepare("INSERT INTO package_items (package_id,asset_id) VALUES (?,?)");
foreach ($packages as $p) {
    $pStmt->execute([$p['name'], $p['desc']]);
    $pid = $pdo->lastInsertId();
    foreach ($p['items'] as $code) { $piStmt->execute([$pid, $assetIds[$code]]); }
}
echo "  ✓ Packages seeded\n";

echo "\nDone!\n";
echo "\nDefault credentials (juga tersimpan di /app/memory/test_credentials.md):\n";
echo "  Admin       : admin@diskominfo.tangerangkab.go.id / admin123\n";
echo "  Pemohon     : andi@diskominfo.tangerangkab.go.id / pemohon123\n";
echo "  Supervisor  : budi@diskominfo.tangerangkab.go.id / supervisor123\n";
echo "  Admin Gudang: dewi@diskominfo.tangerangkab.go.id / gudang123\n";
