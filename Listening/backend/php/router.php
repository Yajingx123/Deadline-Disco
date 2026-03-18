<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$fullPath = __DIR__ . '/public' . $path;
if ($path !== '/' && is_file($fullPath)) {
    return false;
}
require __DIR__ . '/public/index.php';
