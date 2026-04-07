<?php
declare(strict_types=1);

require __DIR__ . '/../../forum-project/api/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function user_info_json(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$user = forum_current_user();

if (!$user) {
    user_info_json(['status' => 'error', 'message' => 'Not logged in'], 401);
}

$pdo = forum_db();
$userId = (int)$user['user_id'];

$teamStmt = $pdo->prepare("
    SELECT ctm.team_id, ct.team_name
    FROM challenge_team_members ctm
    JOIN challenge_teams ct ON ct.team_id = ctm.team_id
    WHERE ctm.user_id = ?
      AND ctm.membership_status = 'active'
      AND ct.status = 'locked'
    LIMIT 1
");
$teamStmt->execute([$userId]);
$team = $teamStmt->fetch();

user_info_json([
    'status' => 'success',
    'user' => [
        'user_id' => $userId,
        'username' => $user['username'],
        'team_id' => $team ? (int)$team['team_id'] : null,
        'team_name' => $team ? $team['team_name'] : null
    ]
]);
