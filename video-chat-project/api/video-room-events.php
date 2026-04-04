<?php
declare(strict_types=1);

require __DIR__ . '/video-helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    video_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = video_require_user();
$pdo = video_db();
$roomPublicId = (string) ($_GET['room'] ?? $_GET['roomPublicId'] ?? '');
$afterEventId = max(0, (int) ($_GET['after'] ?? $_GET['afterEventId'] ?? 0));
$limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));
$result = video_fetch_room_events($pdo, (int) $user['user_id'], $roomPublicId, $afterEventId, $limit);

video_json_response([
    'ok' => true,
    'action' => 'room_events_loaded',
    'latestEventId' => $result['latestEventId'],
    'room' => $result['room'],
    'events' => $result['events'],
]);
