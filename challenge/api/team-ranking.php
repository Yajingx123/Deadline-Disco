<?php
declare(strict_types=1);

require __DIR__ . '/../../forum-project/api/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function team_ranking_json(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function get_current_week_dates(): array {
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
    $weekday = (int)$now->format('N');
    $weekStart = $now->setTime(0, 0)->modify('-' . ($weekday - 1) . ' days');
    $weekEnd = $weekStart->modify('+6 days');

    return [
        'week_start' => $weekStart->format('Y-m-d'),
        'week_end' => $weekEnd->format('Y-m-d'),
    ];
}

$pdo = forum_db();
$dates = get_current_week_dates();
$weekStart = $dates['week_start'];

$action = $_GET['action'] ?? '';

if ($action === 'get_user_team') {
    $user = forum_current_user();
    if (!$user) {
        team_ranking_json(['status' => 'error', 'message' => 'Not logged in'], 401);
    }

    $userId = (int)$user['user_id'];

    $teamStmt = $pdo->prepare("
        SELECT 
            ct.team_id,
            ct.team_name,
            ct.captain_user_id,
            ct.score,
            ct.daily_rank,
            ct.status,
            u.username AS captain_username
        FROM challenge_team_members ctm
        JOIN challenge_teams ct ON ct.team_id = ctm.team_id
        JOIN users u ON u.user_id = ct.captain_user_id
        WHERE ctm.user_id = ?
          AND ctm.membership_status = 'active'
          AND ct.week_start_date = ?
          AND ct.status = 'locked'
        LIMIT 1
    ");
    $teamStmt->execute([$userId, $weekStart]);
    $team = $teamStmt->fetch();

    if (!$team) {
        team_ranking_json([
            'status' => 'success',
            'team' => null,
            'message' => 'User is not in a locked team this week'
        ]);
    }

    $membersStmt = $pdo->prepare("
        SELECT ctm.user_id, u.username, ctm.member_role
        FROM challenge_team_members ctm
        JOIN users u ON u.user_id = ctm.user_id
        WHERE ctm.team_id = ?
          AND ctm.membership_status = 'active'
        ORDER BY ctm.member_role DESC, ctm.joined_at ASC
    ");
    $membersStmt->execute([(int)$team['team_id']]);
    $members = $membersStmt->fetchAll();

    $today = date('Y-m-d', strtotime('+1 day'));
    $teamId = (int)$team['team_id'];

    $progressStmt = $pdo->prepare("
        SELECT 
            sr.rule_id,
            sr.score,
            sr.user_id,
            u.username
        FROM score_records sr
        JOIN users u ON u.user_id = sr.user_id
        WHERE sr.group_id = ?
          AND sr.user_id = ?
          AND DATE(sr.record_time) = ?
          AND sr.rule_id LIKE 'routine%'
        ORDER BY sr.record_time ASC
    ");
    $progressStmt->execute([$teamId, $userId, $today]);
    $records = $progressStmt->fetchAll();

    $rulesStmt = $pdo->prepare("
        SELECT rule_id, rule_name, base_score, daily_count_limit 
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
        $dailyCountLimit = (int)($rule['daily_count_limit'] ?? 1);
        $maxPoints = $baseScore * $dailyCountLimit;

        $ruleRecords = array_filter($records, fn($r) => $r['rule_id'] === $ruleId);
        $totalScore = array_sum(array_column($ruleRecords, 'score'));
        $count = count($ruleRecords);
        $percentage = $maxPoints > 0 ? min(100, ($totalScore / $maxPoints) * 100) : 0;

        $progress[] = [
            'rule_id' => $ruleId,
            'rule_name' => $rule['rule_name'],
            'base_score' => $baseScore,
            'daily_count_limit' => $dailyCountLimit,
            'max_points' => $maxPoints,
            'current_score' => $totalScore,
            'current_count' => $count,
            'percentage' => (int)$percentage,
            'records' => array_map(fn($r) => [
                'user_id' => (int)$r['user_id'],
                'username' => $r['username'],
                'score' => (int)$r['score']
            ], array_values($ruleRecords))
        ];
    }

    $weeklyProgressStmt = $pdo->prepare("
        SELECT 
            sr.rule_id,
            sr.score
        FROM score_records sr
        WHERE sr.user_id = ?
          AND sr.rule_id IN ('compete1_win', 'compete1_lose')
          AND DATE(sr.record_time) >= ?
        ORDER BY sr.record_time ASC
    ");
    $weeklyProgressStmt->execute([$userId, $weekStart]);
    $weeklyRecords = $weeklyProgressStmt->fetchAll();

    $weeklyWinCount = 0;
    $weeklyLoseCount = 0;
    $weeklyTotalScore = 0;

    foreach ($weeklyRecords as $record) {
        $weeklyTotalScore += (int)$record['score'];
        if ($record['rule_id'] === 'compete1_win') {
            $weeklyWinCount++;
        } else {
            $weeklyLoseCount++;
        }
    }

    $weeklyProgress = [
        'win_count' => $weeklyWinCount,
        'lose_count' => $weeklyLoseCount,
        'total_score' => $weeklyTotalScore,
        'records' => $weeklyRecords
    ];

    team_ranking_json([
        'status' => 'success',
        'team' => [
            'team_id' => $teamId,
            'team_name' => $team['team_name'],
            'captain_user_id' => (int)$team['captain_user_id'],
            'captain_username' => $team['captain_username'],
            'score' => (int)$team['score'],
            'rank' => $team['daily_rank'] ? (int)$team['daily_rank'] : null,
            'status' => $team['status'],
            'members' => array_map(function($m) {
                return [
                    'user_id' => (int)$m['user_id'],
                    'username' => $m['username'],
                    'member_role' => $m['member_role']
                ];
            }, $members),
            'daily_progress' => $progress,
            'weekly_progress' => $weeklyProgress
        ]
    ]);
}

if ($action === 'get_team_ranking') {
    $stmt = $pdo->prepare("
        SELECT 
            ct.team_id,
            ct.team_name,
            ct.score,
            ct.daily_rank,
            u.username AS captain_username
        FROM challenge_teams ct
        JOIN users u ON u.user_id = ct.captain_user_id
        WHERE ct.week_start_date = ?
          AND ct.status = 'locked'
        ORDER BY ct.score DESC, ct.created_at ASC
    ");
    $stmt->execute([$weekStart]);
    $teams = $stmt->fetchAll();

    $rankings = [];
    $rank = 1;
    foreach ($teams as $team) {
        $membersStmt = $pdo->prepare("
            SELECT ctm.user_id, u.username, ctm.member_role
            FROM challenge_team_members ctm
            JOIN users u ON u.user_id = ctm.user_id
            WHERE ctm.team_id = ?
              AND ctm.membership_status = 'active'
            ORDER BY ctm.member_role DESC, ctm.joined_at ASC
        ");
        $membersStmt->execute([(int)$team['team_id']]);
        $members = $membersStmt->fetchAll();

        $rankings[] = [
            'team_id' => (int)$team['team_id'],
            'team_name' => $team['team_name'],
            'score' => (int)$team['score'],
            'rank' => $rank++,
            'captain_username' => $team['captain_username'],
            'members' => array_map(function($m) {
                return [
                    'user_id' => (int)$m['user_id'],
                    'username' => $m['username'],
                    'member_role' => $m['member_role']
                ];
            }, $members)
        ];
    }

    team_ranking_json([
        'status' => 'success',
        'rankings' => $rankings,
        'week_start' => $weekStart
    ]);
}

team_ranking_json(['status' => 'error', 'message' => 'Invalid action'], 400);
