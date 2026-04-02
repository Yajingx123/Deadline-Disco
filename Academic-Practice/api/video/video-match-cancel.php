<?php
declare(strict_types=1);

require __DIR__ . '/video-helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    video_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = video_require_user();
$pdo = video_db();

video_json_response(video_build_response_payload(
    video_cancel_queue($pdo, (int) $user['user_id']),
    [
        'ok' => true,
        'message' => 'Video match queue cancelled.',
        'action' => 'queue_cancelled',
    ]
));
