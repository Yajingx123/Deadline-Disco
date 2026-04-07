<?php
declare(strict_types=1);

function score_add_routine_points(PDO $pdo, int $userId, string $ruleId, string $description): array {
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
    $weekday = (int)$now->format('N');
    $weekStart = $now->setTime(0, 0)->modify('-' . ($weekday - 1) . ' days');
    $weekStartStr = $weekStart->format('Y-m-d');

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
    $teamStmt->execute([$userId, $weekStartStr]);
    $team = $teamStmt->fetch();

    if (!$team) {
        return ['status' => 'skipped', 'message' => 'Not in an active team'];
    }

    $teamId = (int)$team['team_id'];

    $ruleStmt = $pdo->prepare("
        SELECT base_score, daily_count_limit 
        FROM score_rules 
        WHERE rule_id = ? AND rule_type = 'routine'
    ");
    $ruleStmt->execute([$ruleId]);
    $rule = $ruleStmt->fetch();

    if (!$rule) {
        return ['status' => 'error', 'message' => 'Rule not found'];
    }

    $baseScore = (int)$rule['base_score'];
    $dailyLimit = (int)($rule['daily_count_limit'] ?? 1);

    $today = date('Y-m-d', strtotime('+1 day'));
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) as cnt
        FROM score_records
        WHERE group_id = ?
          AND rule_id = ?
          AND user_id = ?
          AND DATE(record_time) = ?
    ");
    $checkStmt->execute([$teamId, $ruleId, $userId, $today]);
    $existing = $checkStmt->fetch();

    if ((int)$existing['cnt'] >= $dailyLimit) {
        return ['status' => 'limit_reached', 'message' => 'Daily limit reached'];
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO score_records (group_id, rule_id, score, user_id, description, record_time)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $insertStmt->execute([$teamId, $ruleId, $baseScore, $userId, $description]);

    $updateStmt = $pdo->prepare("
        UPDATE challenge_teams 
        SET score = score + ? 
        WHERE team_id = ?
    ");
    $updateStmt->execute([$baseScore, $teamId]);

    return [
        'status' => 'success',
        'message' => 'Points added',
        'points_earned' => $baseScore,
        'team_name' => $team['team_name']
    ];
}
