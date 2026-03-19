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

// 校验字段
if (empty($data->username) || empty($data->email) || empty($data->password)) {
    echo json_encode(["status" => "error", "message" => "用户名、邮箱或密码不能为空"]); exit;
}

try {
    // 检查用户名或邮箱是否已被注册
    $checkSql = "SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':username' => $data->username, ':email' => $data->email]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(["status" => "error", "message" => "用户名或邮箱已被注册"]); exit;
    }

    $hashedPassword = password_hash($data->password, PASSWORD_DEFAULT);
    
    // 插入包含邮箱的数据
    $insertSql = "INSERT INTO users (username, email, password, created_at) VALUES (:username, :email, :password, NOW())";
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        ':username' => $data->username, 
        ':email' => $data->email, 
        ':password' => $hashedPassword
    ]);

    echo json_encode(["status" => "success", "message" => "注册成功", "username" => $data->username]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "数据库错误：" . $e->getMessage()]);
}