<?php
// ==============================================================
//  SIMANTAP BMN — Entry Point
//  This file MUST use only PHP 5.x-compatible syntax so that
//  older PHP versions can display a friendly upgrade message.
// ==============================================================

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(500);
    $ver = PHP_VERSION;
    echo '<!DOCTYPE html><html><head><title>Perlu Upgrade PHP</title>';
    echo '<style>body{font-family:Arial,sans-serif;max-width:640px;margin:60px auto;padding:24px;color:#1E293B}';
    echo 'h1{color:#DC2626;margin-bottom:8px}.card{background:#FEF2F2;border:1px solid #FCA5A5;border-radius:12px;padding:20px;margin:16px 0}';
    echo '.ok{background:#ECFDF5;border-color:#A7F3D0;color:#065F46}';
    echo 'code{background:#F1F5F9;padding:2px 6px;border-radius:4px;font-family:ui-monospace,monospace}';
    echo 'a{color:#0F172A;font-weight:600}</style></head><body>';
    echo '<h1>⚠️ PHP Anda terlalu lama</h1>';
    echo '<p><strong>SIMANTAP BMN</strong> membutuhkan minimal <strong>PHP 7.4</strong>. ';
    echo 'Anda saat ini menggunakan <strong>PHP ' . $ver . '</strong>.</p>';
    echo '<div class="card"><h3 style="margin-top:0">🔧 Cara memperbaiki:</h3><ol>';
    echo '<li>Download <a href="https://www.apachefriends.org/download.html" target="_blank">XAMPP versi terbaru</a> (yang mengandung PHP 8.2+).</li>';
    echo '<li>Uninstall XAMPP lama Anda (backup database dulu!).</li>';
    echo '<li>Install XAMPP baru → letakkan folder <code>simassta-bmn</code> di <code>htdocs</code>.</li>';
    echo '<li>Jalankan Apache + MySQL → import <code>database/schema.sql</code> di phpMyAdmin.</li>';
    echo '<li>Buka kembali URL ini.</li>';
    echo '</ol></div>';
    echo '<div class="card ok"><strong>Info:</strong> XAMPP versi terbaru (Agu 2024+) sudah termasuk PHP 8.2 yang aman dan didukung penuh.</div>';
    echo '<p style="color:#64748B;font-size:13px;">Sistem: PHP ' . $ver . ' · ' . htmlspecialchars(php_sapi_name()) . '</p>';
    echo '</body></html>';
    exit;
}

// PHP version OK — hand off to the real application.
require __DIR__ . '/bootstrap.php';
