<?php
declare(strict_types=1);

require __DIR__ . '/video-helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    video_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = video_require_user();
$pdo = video_db();
$input = video_input();
$roomPublicId = (string) ($input['room'] ?? $input['roomPublicId'] ?? '');
$result = video_end_room($pdo, (int) $user['user_id'], $roomPublicId);

video_json_response([
    'ok' => true,
    'action' => 'room_ended',
    'message' => 'Room ended successfully.',
    'room' => $result['room'],
]);
