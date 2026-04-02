<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$allowedOrigins = [
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5174',
    'http://127.0.0.1:5500',
    'http://127.0.0.1:8001',
];

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

$config = require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth_user.php';

auth_start_session();
auth_bootstrap_roles($pdo);

$data = json_decode(file_get_contents('php://input'));

if (empty($data->username) || empty($data->email) || empty($data->password)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Username, email, and password are required.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $checkStmt = $pdo->prepare("
        SELECT user_id
        FROM users
        WHERE username = :username OR email = :email
        LIMIT 1
    ");
    $checkStmt->execute([
        ':username' => trim((string)$data->username),
        ':email' => trim((string)$data->email),
    ]);

    if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'The username or email is already registered.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $hashedPassword = password_hash((string)$data->password, PASSWORD_DEFAULT);

    $insertStmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash, avatar_url, role, created_at, updated_at)
        VALUES (:username, :email, :password_hash, NULL, 'user', NOW(), NOW())
    ");
    $insertStmt->execute([
        ':username' => trim((string)$data->username),
        ':email' => trim((string)$data->email),
        ':password_hash' => $hashedPassword,
    ]);

    $userId = (int)$pdo->lastInsertId();
    $user = auth_fetch_user_by_id($pdo, $userId);

    session_regenerate_id(true);
    $_SESSION['auth_user'] = $user ? auth_map_user_row($user) : [
        'user_id' => $userId,
        'username' => trim((string)$data->username),
        'email' => trim((string)$data->email),
        'role' => 'user',
    ];

    echo json_encode([
        'status' => 'success',
        'message' => 'Registration successful.',
        'user' => $_SESSION['auth_user'],
        'username' => $_SESSION['auth_user']['username'],
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
