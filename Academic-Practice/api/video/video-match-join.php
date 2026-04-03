<?php
declare(strict_types=1);

require __DIR__ . '/video-helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    video_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = video_require_user();
$pdo = video_db();
$input = video_input();
$state = video_join_queue($pdo, (int) $user['user_id'], $input);
$message = $state['mode'] === 'matched' ? 'Matched successfully.' : 'Joined video match queue.';

video_json_response(video_build_response_payload($state, [
    'ok' => true,
    'message' => $message,
    'action' => 'queue_joined',
]));
