<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../config/config.php';
$allowedOrigins = is_array($config['allowed_origins'] ?? null) ? $config['allowed_origins'] : [];

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth_user.php';

auth_start_session();
auth_bootstrap_roles($pdo);

$sessionUser = $_SESSION['auth_user'] ?? null;
if (!is_array($sessionUser) || empty($sessionUser['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Not logged in',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = auth_fetch_user_by_id($pdo, (int)$sessionUser['user_id']);
if (!$user) {
    $_SESSION['auth_user'] = null;
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Not logged in',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$_SESSION['auth_user'] = auth_map_user_row($user);

echo json_encode([
    'status' => 'success',
    'user' => $_SESSION['auth_user'],
], JSON_UNESCAPED_UNICODE);
