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
    $payload = video_call_leave_room(
        $pdo,
        (int) $user['user_id'],
        (string) ($input['roomId'] ?? ''),
        trim((string) ($input['reason'] ?? 'user_left')) ?: 'user_left'
    );
    video_json_response([
        'ok' => true,
        'result' => $payload,
    ]);
} catch (VideoCallException $e) {
    video_json_response(['ok' => false, 'message' => $e->getMessage()], (int) $e->getCode() ?: 400);
} catch (Throwable $e) {
    video_json_response(['ok' => false, 'message' => 'Unable to leave room.'], 500);
}
