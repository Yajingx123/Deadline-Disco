<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); exit;
}

$config = require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/db.php';

$data = json_decode(file_get_contents("php://input"));

// 前端传过来的统一叫 identifier (标识符)，可能是用户名也可能是邮箱
if (empty($data->identifier) || empty($data->password)) {
    echo json_encode(["status" => "error", "message" => "账号或密码不能为空"]); exit;
}

try {
    // 用 OR 语法同时匹配 username 或 email
    $sql = "SELECT * FROM users WHERE username = :identifier OR email = :identifier LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':identifier' => $data->identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 校验密码
    if ($user && password_verify($data->password, $user['password'])) {
        echo json_encode(["status" => "success", "message" => "登录成功", "username" => $user['username']]);
    } else {
        echo json_encode(["status" => "error", "message" => "账号或密码错误"]);
    }
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "数据库错误：" . $e->getMessage()]);
}