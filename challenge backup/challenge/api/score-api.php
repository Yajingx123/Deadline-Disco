<?php
declare(strict_types=1);

require __DIR__ . '/../../forum-project/api/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function score_api_json(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function get_current_week_dates(): array {
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
    $weekday = (int)$now->format('N');
    $weekStart = $now->setTime(0, 0)->modify('-' . ($weekday - 1) . ' days');

    return [
        'week_start' => $weekStart->format('Y-m-d'),
    ];
}

$pdo = forum_db();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_team_progress') {
    $userId = (int)($input['user_id'] ?? 0);
    $dates = get_current_week_dates();
    $weekStart = $dates['week_start'];
    $today = date('Y-m-d');

    if ($userId <= 0) {
        $rules = [
            [
                'rule_code' => 'routine1',
                'rule_name' => '小组日常签到',
                'daily_limit' => 1,
                'base_points' => 10,
                'max_points' => 10,
                'current_count' => 0,
                'current_points' => 0,
                'progress_percentage' => 0
            ],
            [
                'rule_code' => 'routine2',
                'rule_name' => '在线学习时长',
                'daily_limit' => 3,
                'base_points' => 5,
                'max_points' => 15,
                'current_count' => 0,
                'current_points' => 0,
                'progress_percentage' => 0
            ],
            [
                'rule_code' => 'routine3',
                'rule_name' => '发帖得分',
                'daily_limit' => 1,
                'base_points' => 3,
                'max_points' => 3,
                'current_count' => 0,
                'current_points' => 0,
                'progress_percentage' => 0
            ],
            [
                'rule_code' => 'routine4',
                'rule_name' => '回帖得分',
                'daily_limit' => 1,
                'base_points' => 1,
                'max_points' => 1,
                'current_count' => 0,
                'current_points' => 0,
                'progress_percentage' => 0
            ]
        ];

        score_api_json([
            'status' => 'success',
            'data' => $rules
        ]);
    }

    $teamStmt = $pdo->prepare("
        SELECT ct.team_id
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
        score_api_json([
            'status' => 'success',
            'data' => []
        ]);
    }

    $teamId = (int)$team['team_id'];

    $rulesStmt = $pdo->prepare("
        SELECT rule_id, rule_name, base_score, daily_limit
        FROM score_rules
        WHERE rule_type = 'routine'
        ORDER BY rule_id
    ");
    $rulesStmt->execute();
    $rules = $rulesStmt->fetchAll();

    $progress = [];
    foreach ($rules as $rule) {
        $ruleId = $rule['rule_id'];
        $baseScore = (int)$rule['base_score'];
        $dailyLimit = (int)($rule['daily_limit'] ?? 1);
        $maxPoints = $baseScore * $dailyLimit;

        $recordStmt = $pdo->prepare("
            SELECT COALESCE(SUM(score), 0) as total_score, COUNT(*) as count
            FROM score_records
            WHERE group_id = ?
              AND rule_id = ?
              AND DATE(record_time) = ?
        ");
        $recordStmt->execute([$teamId, $ruleId, $today]);
        $record = $recordStmt->fetch();

        $currentPoints = (int)($record['total_score'] ?? 0);
        $currentCount = (int)($record['count'] ?? 0);
        $progressPercentage = $maxPoints > 0 ? min(100, ($currentPoints / $maxPoints) * 100) : 0;

        $progress[] = [
            'rule_code' => $ruleId,
            'rule_name' => $rule['rule_name'],
            'daily_limit' => $dailyLimit,
            'base_points' => $baseScore,
            'max_points' => $maxPoints,
            'current_count' => $currentCount,
            'current_points' => $currentPoints,
            'progress_percentage' => (int)$progressPercentage
        ];
    }

    score_api_json([
        'status' => 'success',
        'data' => $progress
    ]);
}

score_api_json(['status' => 'error', 'message' => 'Invalid action'], 400);
