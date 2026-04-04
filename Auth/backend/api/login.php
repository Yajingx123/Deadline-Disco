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
header('Access-Control-Allow-Methods: POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth_user.php';

auth_start_session();
auth_bootstrap_roles($pdo);

$data = json_decode(file_get_contents('php://input'));

if (empty($data->identifier) || empty($data->password)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Username/email and password are required.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user = auth_fetch_user_by_identifier($pdo, trim((string)$data->identifier));

    if ($user && password_verify((string)$data->password, (string)$user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['auth_user'] = auth_map_user_row($user);

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful.',
            'user' => $_SESSION['auth_user'],
            'username' => $user['username'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid username/email or password.',
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
