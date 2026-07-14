<?php
declare(strict_types=1);
// Buat / promosikan akun Super Admin. Jalankan dari CLI di root aplikasi:
//   php scripts/create_superadmin.php "Nama Lengkap" email@dinas.go.id PasswordKuat123
// Jika email sudah terdaftar, akun itu DIPROMOSIKAN jadi superadmin (password ikut
// diganti bila diberikan). Superadmin punya akses penuh + tombol reset data.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

[$self, $name, $email, $password] = array_pad($argv, 4, null);
if (!$name || !$email || !$password) {
    fwrite(STDERR, "Pemakaian: php scripts/create_superadmin.php \"Nama Lengkap\" email password\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "Email tidak valid.\n"); exit(1); }
if (strlen($password) < 8) { fwrite(STDERR, "Password minimal 8 karakter.\n"); exit(1); }

$pdo = db();
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {
    $pdo->prepare("UPDATE users SET name=?, role='superadmin', password_hash=?, is_active=1, deleted_at=NULL WHERE id=?")
        ->execute([$name, $hash, $existing['id']]);
    echo "OK: akun {$email} (id {$existing['id']}) dipromosikan menjadi SUPERADMIN.\n";
} else {
    $pdo->prepare("INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?,?,?,'superadmin',1)")
        ->execute([$name, $email, $hash]);
    echo "OK: superadmin baru dibuat — {$email} (id " . $pdo->lastInsertId() . ").\n";
}
echo "Login di aplikasi memakai email & password tersebut.\n";
