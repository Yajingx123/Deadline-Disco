<?php
declare(strict_types=1);

require __DIR__ . '/video-helpers.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user = video_require_user();
$pdo = video_db();

try {
    if ($method === 'GET') {
        video_json_response([
            'ok' => true,
            'rooms' => video_call_list_rooms($pdo, (int) $user['user_id']),
        ]);
    }

    if ($method !== 'POST') {
        video_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $room = video_call_create_room($pdo, (int) $user['user_id'], video_input());
    video_json_response([
        'ok' => true,
        'message' => 'Room created.',
        'room' => $room,
    ], 201);
} catch (VideoCallException $e) {
    video_json_response(['ok' => false, 'message' => $e->getMessage()], (int) $e->getCode() ?: 400);
} catch (Throwable $e) {
    video_json_response(['ok' => false, 'message' => 'Video room request failed.'], 500);
}
