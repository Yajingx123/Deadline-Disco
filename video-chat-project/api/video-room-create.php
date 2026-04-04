<?php
declare(strict_types=1);

require __DIR__ . '/video-helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    video_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = video_require_user();
$pdo = video_db();
$input = video_input();
$room = video_create_room($pdo, $user, $input);

video_json_response([
    'ok' => true,
    'message' => 'Room created successfully.',
    'action' => 'room_created',
    'room' => $room,
]);
