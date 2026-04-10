<?php
declare(strict_types=1);

require __DIR__ . '/../../forum-project/api/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function checkin_json(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function get_current_week_start(): string {
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
    $weekday = (int)$now->format('N');
    $weekStart = $now->setTime(0, 0)->modify('-' . ($weekday - 1) . ' days');
    return $weekStart->format('Y-m-d');
}

$pdo = forum_db();
$user = forum_current_user();

if (!$user) {
    checkin_json(['status' => 'error', 'message' => 'Not logged in'], 401);
}

$userId = (int)$user['user_id'];
$today = date('Y-m-d');
$weekStart = get_current_week_start();

$teamStmt = $pdo->prepare("
    SELECT ct.team_id, ct.team_name
    FROM challenge_team_members ctm
    JOIN challenge_teams ct ON ct.team_id = ctm.team_id
    WHERE ctm.user_id = ?
      AND ctm.membership_status = 'active'
      AND ct.week_start_date = ?
      AND ct.status = 'locked'
    LIMIT 1
");
$teamStmt->execute([$userId, $weekStart]);
$team = $teamStmt->fetch();

if (!$team) {
    checkin_json([
        'status' => 'error',
        'message' => 'You are not in an active team this week'
    ], 400);
}

$teamId = (int)$team['team_id'];

$checkStmt = $pdo->prepare("    SELECT COUNT(*) as cnt
    FROM score_records
    WHERE group_id = ?
      AND rule_id = 'routine1'
      AND user_id = ?
      AND DATE(record_time) = ?
");
$checkStmt->execute([$teamId, $userId, $today]);
$existing = $checkStmt->fetch();

if ((int)$existing['cnt'] > 0) {
    checkin_json([
        'status' => 'success',
        'message' => 'Already checked in today',
        'already_checked_in' => true
    ]);
}

$ruleStmt = $pdo->prepare("SELECT base_score FROM score_rules WHERE rule_id = 'routine1'");
$ruleStmt->execute();
$rule = $ruleStmt->fetch();

if (!$rule) {
    checkin_json(['status' => 'error', 'message' => 'Check-in rule not found'], 500);
}

$baseScore = (int)$rule['base_score'];

$insertStmt = $pdo->prepare("
    INSERT INTO score_records (group_id, rule_id, score, user_id, description, record_time)
    VALUES (?, 'routine1', ?, ?, 'Daily check-in', NOW())
");
$insertStmt->execute([$teamId, $baseScore, $userId]);

$updateStmt = $pdo->prepare("
    UPDATE challenge_teams 
    SET score = score + ? 
    WHERE team_id = ?
");
$updateStmt->execute([$baseScore, $teamId]);

checkin_json([
    'status' => 'success',
    'message' => 'Check-in successful',
    'points_earned' => $baseScore,
    'team_id' => $teamId,
    'team_name' => $team['team_name']
]);
