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
$inviteToken = (string) ($input['invite'] ?? $input['inviteToken'] ?? '');
$result = video_grant_room_access($pdo, (int) $user['user_id'], $roomPublicId, $inviteToken);

video_json_response([
    'ok' => true,
    'action' => 'room_access_granted',
    'resolvedBy' => $result['resolvedBy'],
    'membershipCreated' => $result['membershipCreated'],
    'room' => $result['room'],
]);
