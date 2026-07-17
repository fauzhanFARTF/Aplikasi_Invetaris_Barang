<?php
declare(strict_types=1);
require_once __DIR__ . '/JWT.php';

class Auth {
    private const COOKIE = 'simassta_token';

    public static function attempt(string $email, string $password): ?array {
        // password_hash IS NOT NULL: akun yang mendaftar lewat Google tidak punya
        // password, jadi jangan sampai bisa ditembus lewat form email+password.
        // reg_status: pendaftar yang belum/tidak disetujui admin tidak boleh masuk.
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 AND deleted_at IS NULL
                               AND password_hash IS NOT NULL AND reg_status = 'approved' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) return null;
        self::issueToken($user);
        return $user;
    }

    public static function issueToken(array $user): void {
        $payload = [
            'sub'  => (int) $user['id'],
            'eml'  => $user['email'],
            'rol'  => $user['role'],
            'nam'  => $user['name'],
            'iat'  => time(),
            'exp'  => time() + JWT_TTL_SEC,
        ];
        $token = JWT::encode($payload, JWT_SECRET);
        $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        setcookie(self::COOKIE, $token, [
            'expires'  => time() + JWT_TTL_SEC,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $secure,
            'samesite' => 'Lax',
        ]);
    }

    public static function logout(): void {
        setcookie(self::COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
    }

    public static function user(): ?array {
        static $cache = false;
        if ($cache !== false) return $cache;
        $token = $_COOKIE[self::COOKIE] ?? '';
        if (!$token) return $cache = null;
        $payload = JWT::decode($token, JWT_SECRET);
        if (!$payload) return $cache = null;
        // reg_status dicek juga di sini: kalau admin menolak/menonaktifkan akun
        // saat sesinya masih hidup, token lamanya langsung tidak berlaku.
        $stmt = db()->prepare("SELECT id,name,email,role,phone,unit_kerja,is_active FROM users
                               WHERE id = ? AND is_active = 1 AND deleted_at IS NULL AND reg_status = 'approved'");
        $stmt->execute([$payload['sub']]);
        $u = $stmt->fetch();
        return $cache = ($u ?: null);
    }

    public static function id(): ?int {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    /** Peran UTAMA (dipakai untuk tampilan & percabangan default). */
    public static function role(): ?string {
        $u = self::user();
        return $u['role'] ?? null;
    }

    /** Seluruh peran efektif user: peran utama + peran tambahan (user_roles). */
    public static function roles(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $u = self::user();
        if (!$u) return $cache = [];
        $roles = [$u['role']];
        try {
            $stmt = db()->prepare("SELECT role FROM user_roles WHERE user_id = ?");
            $stmt->execute([(int) $u['id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $r) { $roles[] = $r; }
        } catch (Throwable $e) { /* tabel mungkin belum ada saat migrasi awal */ }
        return $cache = array_values(array_unique($roles));
    }

    /** True jika user memiliki salah satu dari peran yang diberikan. */
    public static function hasRole(string ...$roles): bool {
        return count(array_intersect(self::roles(), $roles)) > 0;
    }

    public static function check(): bool { return self::user() !== null; }

    public static function requireLogin(): void {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    public static function requireRole(string ...$roles): void {
        self::requireLogin();
        // Superadmin punya akses penuh; selain itu cukup memiliki salah satu
        // peran (utama atau tambahan) yang diizinkan.
        if (self::hasRole('superadmin')) return;
        if (!self::hasRole(...$roles)) {
            http_response_code(403);
            include APP_ROOT . '/views/errors/403.php';
            exit;
        }
    }

    public static function csrfToken(): string {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(24));
        }
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(): void {
        $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', (string) $t)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            exit;
        }
    }
}
