<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$user = $_SESSION['auth_user'] ?? null;

if (!$user) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Not logged in',
    ]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'user' => $user,
], JSON_UNESCAPED_UNICODE);
