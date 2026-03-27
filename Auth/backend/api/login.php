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

// 前端传过来的统一叫 identifier (标识符)，可能是用户名也可能是邮箱
if (empty($data->identifier) || empty($data->password)) {
    echo json_encode(["status" => "error", "message" => "账号或密码不能为空"]); exit;
}

try {
    $sql = "SELECT user_id, username, email, password_hash FROM users WHERE username = :identifier OR email = :identifier LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':identifier' => $data->identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($data->password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['auth_user'] = [
            'user_id' => (int)$user['user_id'],
            'username' => (string)$user['username'],
            'email' => (string)$user['email'],
        ];
        echo json_encode([
            "status" => "success",
            "message" => "登录成功",
            "user" => $_SESSION['auth_user'],
            "username" => $user['username']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "账号或密码错误"], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "数据库错误：" . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
