<?php
declare(strict_types=1);
require_once __DIR__ . '/JWT.php';

class Auth {
    private const COOKIE = 'simassta_token';

    public static function attempt(string $email, string $password): ?array {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
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
        $stmt = db()->prepare("SELECT id,name,email,role,phone,unit_kerja,is_active FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$payload['sub']]);
        $u = $stmt->fetch();
        return $cache = ($u ?: null);
    }

    public static function id(): ?int {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function role(): ?string {
        $u = self::user();
        return $u['role'] ?? null;
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
        if (!in_array(self::role(), $roles, true)) {
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
