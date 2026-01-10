<?php
// router.php
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$file = __DIR__ . $path;

// serve existing files directly (css/js/images)
if ($path !== '/' && is_file($file)) {
    return false;
}

// everything else goes through index.php
require __DIR__ . '/index.php';
