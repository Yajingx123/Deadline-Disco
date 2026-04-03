<?php
declare(strict_types=1);

require __DIR__ . '/video-helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    video_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = video_require_user();
$pdo = video_db();
$touchHeartbeat = !isset($_GET['touch']) || $_GET['touch'] !== '0';

video_json_response(video_build_response_payload(
    video_fetch_state($pdo, (int) $user['user_id'], $touchHeartbeat),
    [
        'ok' => true,
        'action' => 'state_synced',
    ]
));
