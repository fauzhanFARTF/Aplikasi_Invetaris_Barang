<?php
declare(strict_types=1);
// ==== Front Controller / Router =====================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/JWT.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Helpers.php';
require_once __DIR__ . '/../src/Notification.php';
require_once __DIR__ . '/../src/Google.php';

// Load all controllers (each defines procedural functions)
foreach (glob(APP_ROOT . '/src/Controllers/*.php') as $ctrl) {
    require_once $ctrl;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
// Strip BASE_PATH from URI so route regexes still match /login, /dashboard, etc.
if (BASE_PATH !== '' && str_starts_with($uri, BASE_PATH)) {
    $uri = substr($uri, strlen(BASE_PATH)) ?: '/';
}
$uri    = '/' . trim($uri, '/');
if ($uri === '/') $uri = '/';

// Route table: [METHOD, regex, handler]
$routes = [
    // Auth
    ['GET',  '#^/login$#',  'auth_login_get'],
    ['POST', '#^/login$#',  'auth_login_post'],
    ['POST', '#^/logout$#', 'auth_logout'],

    // Login dengan Google + pendaftaran mandiri (menunggu verifikasi admin)
    ['GET',  '#^/auth/google$#',          'google_start'],
    ['GET',  '#^/auth/google/callback$#', 'google_callback'],
    ['GET',  '#^/daftar$#',               'register_form'],
    ['POST', '#^/daftar$#',               'register_submit'],
    ['GET',  '#^/daftar/menunggu$#',      'register_pending'],

    // Verifikasi pendaftaran (admin)
    ['GET',  '#^/registrations$#',                 'registration_index'],
    ['POST', '#^/registrations/([\w-]+)/approve$#', 'registration_approve'],
    ['POST', '#^/registrations/([\w-]+)/reject$#',  'registration_reject'],

    // Dashboard
    ['GET',  '#^/$#',           'dashboard_index'],
    ['GET',  '#^/dashboard$#',  'dashboard_index'],

    // Loans (booking) — pemohon + others
    ['GET',  '#^/loans$#',                    'loan_index'],
    ['GET',  '#^/loans/create$#',             'loan_create_get'],
    ['POST', '#^/loans/create$#',             'loan_create_post'],
    ['GET',  '#^/loans/([0-9a-f-]{36})$#',              'loan_show'],
    ['POST', '#^/loans/([0-9a-f-]{36})/cancel$#',       'loan_cancel'],
    ['POST', '#^/loans/([0-9a-f-]{36})/items/(\d+)/remove$#', 'loan_item_remove'],
    ['GET',  '#^/loans/([0-9a-f-]{36})/berita-acara$#',  'loan_berita_acara'],
    ['POST', '#^/loans/([0-9a-f-]{36})/delete$#',       'loan_delete'],
    ['POST', '#^/loans/([0-9a-f-]{36})/edit-name$#',    'loan_edit_name'],
    ['POST', '#^/loans/delete-all$#',         'loan_delete_all'],

    // Approvals
    ['GET',  '#^/approvals$#',                'approval_index'],
    ['POST', '#^/loans/([0-9a-f-]{36})/approve$#',      'loan_approve'],
    ['POST', '#^/loans/([0-9a-f-]{36})/reject$#',       'loan_reject'],

    // Penyerahan (checkout)
    ['GET',  '#^/checkout$#',                 'checkout_index'],
    ['GET',  '#^/checkout/([0-9a-f-]{36})$#',           'checkout_scan_page'],
    ['POST', '#^/checkout/scan$#',            'checkout_scan_submit'],
    ['POST', '#^/checkout/([0-9a-f-]{36})/finalize$#',  'checkout_finalize'],

    // Pengembalian (checkin)
    ['GET',  '#^/checkin$#',                  'checkin_index'],
    ['GET',  '#^/checkin/([0-9a-f-]{36})$#',            'checkin_scan_page'],
    ['POST', '#^/checkin/scan$#',             'checkin_scan_submit'],
    ['POST', '#^/checkin/([0-9a-f-]{36})/finalize$#',   'checkin_finalize'],

    // Barang di OPD (Kebutuhan Jaringan) — daftar & tarik kembali bila rusak
    ['GET',  '#^/opd-items$#',                'opd_items_index'],
    ['POST', '#^/opd-items/(\d+)/return$#',   'opd_item_return'],

    // Koreksi data operasional oleh superadmin (edit / batalkan / hapus)
    ['POST', '#^/sa/checkout-item/(\d+)/undo$#',   'sa_checkout_undo'],
    ['POST', '#^/sa/checkin-item/(\d+)/undo$#',    'sa_checkin_undo'],
    ['POST', '#^/sa/opd-item/(\d+)/edit$#',        'sa_opd_item_edit'],
    ['POST', '#^/sa/opd-item/(\d+)/delete$#',      'sa_opd_item_delete'],
    ['POST', '#^/sa/repairs/([0-9a-f-]{36})/edit$#', 'sa_repair_edit'],

    // Repairs
    ['GET',  '#^/repairs$#',                  'repair_index'],
    ['GET',  '#^/repairs/([0-9a-f-]{36})$#',            'repair_show'],
    ['GET',  '#^/repairs/([0-9a-f-]{36})/print$#',      'repair_print'],
    ['POST', '#^/repairs/([0-9a-f-]{36})/complete$#',   'repair_complete'],
    ['POST', '#^/repairs/([0-9a-f-]{36})/delete$#',     'repair_delete'],
    ['POST', '#^/repairs/delete-all$#',       'repair_delete_all'],

    // Inventory
    ['GET',  '#^/inventory$#',                'inventory_index'],
    ['GET',  '#^/inventory/create$#',         'inventory_create_get'],
    ['POST', '#^/inventory/create$#',         'inventory_create_post'],
    ['GET',  '#^/inventory/barcode/print$#',  'inventory_barcode_bulk'],
    ['GET',  '#^/inventory/([0-9a-f-]{36})/barcode$#',  'inventory_barcode_single'],
    ['GET',  '#^/inventory/([0-9a-f-]{36})/edit$#',     'inventory_edit_get'],
    ['POST', '#^/inventory/([0-9a-f-]{36})/edit$#',     'inventory_edit_post'],
    ['POST', '#^/inventory/([0-9a-f-]{36})/retire$#',   'inventory_retire'],
    ['POST', '#^/inventory/([0-9a-f-]{36})/unretire$#', 'inventory_unretire'],
    ['POST', '#^/inventory/([0-9a-f-]{36})/delete$#',   'inventory_delete'],

    // Packages
    ['GET',  '#^/packages$#',                 'package_index'],
    ['GET',  '#^/packages/create$#',          'package_create_get'],
    ['POST', '#^/packages/create$#',          'package_create_post'],
    ['GET',  '#^/packages/([0-9a-f-]{36})/edit$#',      'package_edit_get'],
    ['POST', '#^/packages/([0-9a-f-]{36})/edit$#',      'package_edit_post'],
    ['POST', '#^/packages/([0-9a-f-]{36})/delete$#',    'package_delete'],

    // Categories (admin)
    ['GET',  '#^/categories$#',               'category_index'],
    ['GET',  '#^/categories/create$#',        'category_create_get'],
    ['POST', '#^/categories/create$#',        'category_create_post'],
    ['GET',  '#^/categories/([0-9a-f-]{36})/edit$#',    'category_edit_get'],
    ['POST', '#^/categories/([0-9a-f-]{36})/edit$#',    'category_edit_post'],
    ['POST', '#^/categories/([0-9a-f-]{36})/delete$#',  'category_delete'],

    // Users (admin)
    ['GET',  '#^/users$#',                    'user_index'],
    ['GET',  '#^/users/create$#',             'user_create_get'],
    ['POST', '#^/users/create$#',             'user_create_post'],
    ['GET',  '#^/users/([0-9a-f-]{36})/edit$#',         'user_edit_get'],
    ['POST', '#^/users/([0-9a-f-]{36})/edit$#',         'user_edit_post'],
    ['POST', '#^/users/([0-9a-f-]{36})/toggle$#',       'user_toggle'],
    ['POST', '#^/users/([0-9a-f-]{36})/delete$#',       'user_delete'],

    // Reset data per-manajemen (KHUSUS superadmin)
    ['POST', '#^/reset/loans$#',      'reset_loans'],
    ['POST', '#^/reset/users$#',      'reset_users'],
    ['POST', '#^/reset/assets$#',     'reset_assets'],
    ['POST', '#^/reset/categories$#', 'reset_categories'],
    ['POST', '#^/reset/packages$#',   'reset_packages'],
    ['POST', '#^/reset/repairs$#',    'reset_repairs'],

    // Riwayat Terhapus / Trash (admin)
    ['GET',  '#^/trash$#',                            'trash_index'],
    ['POST', '#^/trash/([a-z_]+)/(\d+)/restore$#',    'trash_restore'],
    ['POST', '#^/trash/([a-z_]+)/(\d+)/purge$#',      'trash_purge'],

    // Notifications
    ['GET',  '#^/notifications$#',            'notification_index'],
    ['POST', '#^/notifications/(\d+)/read$#', 'notification_mark_read'],
    ['POST', '#^/notifications/read-all$#',   'notification_mark_all_read'],
    ['POST', '#^/notifications/(\d+)/delete$#', 'notification_delete'],
    ['POST', '#^/notifications/delete-all$#',   'notification_delete_all'],

    // Profile
    ['GET',  '#^/profile$#',                  'profile_get'],
    ['POST', '#^/profile$#',                  'profile_post'],
    ['POST', '#^/profile/photo$#',            'profile_photo_post'],

    // AJAX/API endpoints — served under /ajax to avoid conflict with reserved /api ingress path
    ['GET',  '#^/ajax/availability$#',         'api_availability'],
    ['GET',  '#^/ajax/assets/search$#',        'api_asset_search'],
    ['GET',  '#^/ajax/next-asset-code$#',      'api_next_asset_code'],
    ['GET',  '#^/ajax/loans/(\d+)$#',          'api_loan_detail'],
    ['GET',  '#^/ajax/notifications/unread$#', 'api_unread_notif'],
    ['GET',  '#^/ajax/health$#',               'api_health'],
    // Legacy alias
    ['GET',  '#^/health$#',                    'api_health'],
];

foreach ($routes as [$m, $pat, $handler]) {
    if ($m !== $method) continue;
    if (preg_match($pat, $uri, $matches)) {
        array_shift($matches);
        if (function_exists($handler)) {
            $handler(...$matches);
            exit;
        }
        http_response_code(500);
        echo "Handler <b>$handler</b> not implemented.";
        exit;
    }
}

http_response_code(404);
include APP_ROOT . '/views/errors/404.php';
