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
$targetUserId = (int) ($input['targetUserId'] ?? $input['userId'] ?? 0);
$result = video_remove_room_member($pdo, (int) $user['user_id'], $roomPublicId, $targetUserId);

video_json_response([
    'ok' => true,
    'action' => 'room_member_removed',
    'removedMember' => $result['removedMember'],
    'room' => $result['room'],
]);
