<?php
// PHP built-in server router: serve static files if they exist, else pass to index.php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // let server serve the static file
}
// Normalize SCRIPT_NAME so BASE_PATH detection produces '' when running via built-in server
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
