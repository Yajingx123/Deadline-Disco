<?php
declare(strict_types=1);

require __DIR__ . '/video-helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    video_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = video_require_user();
$pdo = video_db();
$input = video_input();
$reason = trim((string) ($input['reason'] ?? 'user_left'));
if ($reason === '') {
    $reason = 'user_left';
}

$existingSession = video_find_open_session_for_user($pdo, (int) $user['user_id']);
$endedSession = $existingSession ? video_build_session_payload($existingSession, (int) $user['user_id']) : null;
if ($endedSession) {
    $endedSession['endedReason'] = $reason;
}

$state = video_leave_session($pdo, (int) $user['user_id'], $reason);

video_json_response(video_build_response_payload($state, [
    'ok' => true,
    'message' => $endedSession ? 'Video session ended.' : 'No active video session to leave.',
    'action' => 'session_left',
    'reason' => $reason,
    'endedSession' => $endedSession,
]));
