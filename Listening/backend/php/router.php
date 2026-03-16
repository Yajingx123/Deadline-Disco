<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$fullPath = __DIR__ . '/public' . $path;
if ($path !== '/' && is_file($fullPath)) {
    return false;
}
require __DIR__ . '/public/index.php';
