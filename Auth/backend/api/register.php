<?php
header('Content-Type: application/json; charset=utf-8');

$allowedOrigins = [
    'http://127.0.0.1:5173',
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); exit;
}

$config = require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$data = json_decode(file_get_contents("php://input"));

// 校验字段
if (empty($data->username) || empty($data->email) || empty($data->password)) {
    echo json_encode(["status" => "error", "message" => "用户名、邮箱或密码不能为空"]); exit;
}

try {
    $checkSql = "SELECT user_id FROM users WHERE username = :username OR email = :email LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':username' => $data->username, ':email' => $data->email]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(["status" => "error", "message" => "用户名或邮箱已被注册"]); exit;
    }

    $hashedPassword = password_hash($data->password, PASSWORD_DEFAULT);
    
    $insertSql = "INSERT INTO users (username, email, password_hash, created_at, updated_at) VALUES (:username, :email, :password_hash, NOW(), NOW())";
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        ':username' => $data->username, 
        ':email' => $data->email, 
        ':password_hash' => $hashedPassword
    ]);

    $userId = (int)$pdo->lastInsertId();
    session_regenerate_id(true);
    $_SESSION['auth_user'] = [
        'user_id' => $userId,
        'username' => (string)$data->username,
        'email' => (string)$data->email,
    ];

    echo json_encode([
        "status" => "success",
        "message" => "注册成功",
        "user" => $_SESSION['auth_user'],
        "username" => $data->username
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "数据库错误：" . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
