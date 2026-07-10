<?php
declare(strict_types=1);
// Polyfills for PHP 7.4 (functions added in PHP 8.0)
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

// Central configuration
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load .env
$envPath = APP_ROOT . '/.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k,$v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        if (!getenv($k)) { putenv("$k=$v"); $_ENV[$k] = $v; }
    }
}

function env(string $key, $default = null) {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

// App settings
define('APP_NAME', env('APP_NAME', 'SIMASSTA BMN — Diskominfo Kab. Tangerang'));
define('APP_URL', env('APP_URL', 'http://localhost:3000'));
define('APP_ENV', env('APP_ENV', 'production'));
define('JWT_SECRET', env('JWT_SECRET', 'change-me-in-production-simasstabmn-2026'));
define('JWT_TTL_SEC', (int) env('JWT_TTL_SEC', 60 * 60 * 8)); // 8 hours
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) env('DB_PORT', 3307));
define('DB_NAME', env('DB_NAME', 'bmn_streaming'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_SOCKET', env('DB_SOCKET', '/var/run/mysqld/mysqld.sock'));

// Auto-detect BASE_PATH + ASSET_PREFIX (supports subdirectory + no-rewrite fallback).
// - BASE_PATH  : prefix for DYNAMIC URLs (routes). Includes '/index.php' if user accesses via that.
// - ASSET_PREFIX: prefix for STATIC assets (CSS/JS/images). Never includes '/index.php'.
if (!defined('BASE_PATH')) {
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    // dirname of script → prefix WITHOUT /index.php  (used for assets & rewrite deploy)
    $assetPrefix = str_replace('\\', '/', dirname($scriptName));
    $assetPrefix = rtrim($assetPrefix, '/');
    if ($assetPrefix === '.' || $assetPrefix === '/') $assetPrefix = '';

    // Detect if request uses /index.php explicitly (no mod_rewrite)
    // e.g. REQUEST_URI = /simassta-bmn/public/index.php/login
    $usesScript = ($scriptName !== '' && strpos($requestUri, $scriptName) === 0);

    if ($usesScript) {
        $bp = $scriptName; // e.g. /simassta-bmn/public/index.php
    } else {
        $bp = $assetPrefix; // e.g. /simassta-bmn/public  (or '' for root)
    }

    define('BASE_PATH', $bp);
    define('ASSET_PREFIX', $assetPrefix);
}

// Timezone
date_default_timezone_set(env('APP_TZ', 'Asia/Jakarta'));

// Start session (used only for CSRF token; auth uses JWT cookie)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
