<?php
declare(strict_types=1);

require __DIR__ . '/video-helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    video_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = video_require_user();
$pdo = video_db();
$rooms = video_list_rooms($pdo, (int) $user['user_id']);

video_json_response([
    'ok' => true,
    'action' => 'rooms_listed',
    'total' => count($rooms),
    'rooms' => $rooms,
]);
