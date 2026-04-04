<?php
declare(strict_types=1);

require __DIR__ . '/video-helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    video_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = video_require_user();
$pdo = video_db();
$input = video_input();

try {
    $room = video_call_access_room($pdo, (int) $user['user_id'], (string) ($input['roomId'] ?? ''));
    video_json_response([
        'ok' => true,
        'room' => $room,
    ]);
} catch (VideoCallException $e) {
    video_json_response(['ok' => false, 'message' => $e->getMessage()], (int) $e->getCode() ?: 400);
} catch (Throwable $e) {
    video_json_response(['ok' => false, 'message' => 'Unable to open room.'], 500);
}
