<?php
declare(strict_types=1);

require __DIR__ . '/video-helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    video_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = video_require_user();
$pdo = video_db();
$roomPublicId = (string) ($_GET['room'] ?? $_GET['roomPublicId'] ?? '');
$legacyRoomId = video_parse_zego_room_id((string) ($_GET['roomID'] ?? $_GET['zegoRoomId'] ?? ''));
$inviteToken = (string) ($_GET['invite'] ?? $_GET['inviteToken'] ?? '');

if ($roomPublicId === '' && $legacyRoomId !== '') {
    $roomRow = video_find_room_by_zego_room_id($pdo, $legacyRoomId);
    if (!$roomRow) {
        video_json_response(['ok' => false, 'message' => 'Room not found.'], 404);
    }

    $roomPublicId = (string) $roomRow['room_public_id'];
}

$detail = video_get_room_detail($pdo, (int) $user['user_id'], $roomPublicId, $inviteToken);

video_json_response([
    'ok' => true,
    'action' => 'room_detail_loaded',
    'resolvedBy' => $detail['resolvedBy'],
    'membershipCreated' => $detail['membershipCreated'],
    'activeInvite' => $detail['activeInvite'] ?? null,
    'room' => $detail['room'],
]);
