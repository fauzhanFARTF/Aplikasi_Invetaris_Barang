<?php
declare(strict_types=1);
// ==== Front Controller / Router =====================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/JWT.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Helpers.php';
require_once __DIR__ . '/../src/Notification.php';

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

    // Dashboard
    ['GET',  '#^/$#',           'dashboard_index'],
    ['GET',  '#^/dashboard$#',  'dashboard_index'],

    // Loans (booking) — pemohon + others
    ['GET',  '#^/loans$#',                    'loan_index'],
    ['GET',  '#^/loans/create$#',             'loan_create_get'],
    ['POST', '#^/loans/create$#',             'loan_create_post'],
    ['GET',  '#^/loans/(\d+)$#',              'loan_show'],
    ['POST', '#^/loans/(\d+)/cancel$#',       'loan_cancel'],
    ['POST', '#^/loans/(\d+)/delete$#',       'loan_delete'],
    ['POST', '#^/loans/delete-all$#',         'loan_delete_all'],

    // Approvals
    ['GET',  '#^/approvals$#',                'approval_index'],
    ['POST', '#^/loans/(\d+)/approve$#',      'loan_approve'],
    ['POST', '#^/loans/(\d+)/reject$#',       'loan_reject'],

    // Penyerahan (checkout)
    ['GET',  '#^/checkout$#',                 'checkout_index'],
    ['GET',  '#^/checkout/(\d+)$#',           'checkout_scan_page'],
    ['POST', '#^/checkout/scan$#',            'checkout_scan_submit'],
    ['POST', '#^/checkout/(\d+)/finalize$#',  'checkout_finalize'],

    // Pengembalian (checkin)
    ['GET',  '#^/checkin$#',                  'checkin_index'],
    ['GET',  '#^/checkin/(\d+)$#',            'checkin_scan_page'],
    ['POST', '#^/checkin/scan$#',             'checkin_scan_submit'],
    ['POST', '#^/checkin/(\d+)/finalize$#',   'checkin_finalize'],

    // Repairs
    ['GET',  '#^/repairs$#',                  'repair_index'],
    ['GET',  '#^/repairs/(\d+)$#',            'repair_show'],
    ['GET',  '#^/repairs/(\d+)/print$#',      'repair_print'],
    ['POST', '#^/repairs/(\d+)/complete$#',   'repair_complete'],
    ['POST', '#^/repairs/(\d+)/delete$#',     'repair_delete'],
    ['POST', '#^/repairs/delete-all$#',       'repair_delete_all'],

    // Inventory
    ['GET',  '#^/inventory$#',                'inventory_index'],
    ['GET',  '#^/inventory/create$#',         'inventory_create_get'],
    ['POST', '#^/inventory/create$#',         'inventory_create_post'],
    ['GET',  '#^/inventory/barcode/print$#',  'inventory_barcode_bulk'],
    ['GET',  '#^/inventory/(\d+)/barcode$#',  'inventory_barcode_single'],
    ['GET',  '#^/inventory/(\d+)/edit$#',     'inventory_edit_get'],
    ['POST', '#^/inventory/(\d+)/edit$#',     'inventory_edit_post'],
    ['POST', '#^/inventory/(\d+)/retire$#',   'inventory_retire'],
    ['POST', '#^/inventory/(\d+)/unretire$#', 'inventory_unretire'],
    ['POST', '#^/inventory/(\d+)/delete$#',   'inventory_delete'],

    // Packages
    ['GET',  '#^/packages$#',                 'package_index'],
    ['GET',  '#^/packages/create$#',          'package_create_get'],
    ['POST', '#^/packages/create$#',          'package_create_post'],
    ['GET',  '#^/packages/(\d+)/edit$#',      'package_edit_get'],
    ['POST', '#^/packages/(\d+)/edit$#',      'package_edit_post'],
    ['POST', '#^/packages/(\d+)/delete$#',    'package_delete'],

    // Categories (admin)
    ['GET',  '#^/categories$#',               'category_index'],
    ['GET',  '#^/categories/create$#',        'category_create_get'],
    ['POST', '#^/categories/create$#',        'category_create_post'],
    ['GET',  '#^/categories/(\d+)/edit$#',    'category_edit_get'],
    ['POST', '#^/categories/(\d+)/edit$#',    'category_edit_post'],
    ['POST', '#^/categories/(\d+)/delete$#',  'category_delete'],

    // Users (admin)
    ['GET',  '#^/users$#',                    'user_index'],
    ['GET',  '#^/users/create$#',             'user_create_get'],
    ['POST', '#^/users/create$#',             'user_create_post'],
    ['GET',  '#^/users/(\d+)/edit$#',         'user_edit_get'],
    ['POST', '#^/users/(\d+)/edit$#',         'user_edit_post'],
    ['POST', '#^/users/(\d+)/toggle$#',       'user_toggle'],
    ['POST', '#^/users/(\d+)/delete$#',       'user_delete'],

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

    // AJAX/API endpoints — served under /ajax to avoid conflict with reserved /api ingress path
    ['GET',  '#^/ajax/availability$#',         'api_availability'],
    ['GET',  '#^/ajax/assets/search$#',        'api_asset_search'],
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
