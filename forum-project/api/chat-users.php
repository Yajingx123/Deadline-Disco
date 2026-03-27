<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = forum_require_user();
$currentUserId = (int)$user['user_id'];
$query = trim((string)($_GET['q'] ?? ''));

$pdo = forum_db();

if ($query !== '') {
    $stmt = $pdo->prepare("
        SELECT user_id, username, email
        FROM users
        WHERE user_id <> ?
          AND (username LIKE ? OR email LIKE ?)
        ORDER BY username ASC
        LIMIT 12
    ");
    $like = '%' . $query . '%';
    $stmt->execute([$currentUserId, $like, $like]);
} else {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.email
        FROM users u
        WHERE u.user_id <> ?
        ORDER BY u.username ASC
        LIMIT 8
    ");
    $stmt->execute([$currentUserId]);
}

$users = array_map('forum_user_payload', $stmt->fetchAll());

forum_json([
    'ok' => true,
    'users' => $users,
]);
