<?php
declare(strict_types=1);

require __DIR__ . '/video-helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    video_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = video_require_user();
$pdo = video_db();
$state = video_get_room($pdo, (int) $user['user_id']);

video_json_response(video_build_response_payload($state, [
    'ok' => true,
    'message' => 'Video room is ready.',
    'action' => 'room_opened',
]));
