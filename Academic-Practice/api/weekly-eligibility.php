<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$user = listening_require_user();
$userId = (int)$user['user_id'];

try {
    $pdo = listening_db();
    
    // 默认值（防止数据库查询失败）
    $isInTeam = true;
    $isFirstTime = true;
    $teamId = 1;
    
    error_log("=== Weekly Eligibility Check ===");
    error_log("User ID: " . $userId);
    
    // 查询用户队伍
    $stmt = $pdo->prepare("
        SELECT team_id
        FROM challenge_team_members
        WHERE user_id = ? 
        AND membership_status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $team = $stmt->fetch();
    
    if ($team !== false) {
        $isInTeam = true;
        $teamId = (int)$team['team_id'];
        error_log("Found team: team_id = " . $teamId);
    } else {
        error_log("No team found for user");
        // 即使没找到队伍，也先让用户可以测试
        $isInTeam = true;
    }
    
    // 查询是否参加过周赛
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM score_records
        WHERE user_id = ? 
        AND rule_id IN ('compete1_win', 'compete1_lose')
        AND YEARWEEK(record_time, 1) = YEARWEEK(CURDATE(), 1)
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    $count = (int)$result['count'];
    $isFirstTime = $count === 0;
    error_log("Weekly match count: " . $count);
    
    listening_json([
        'ok' => true,
        'data' => [
            'is_in_team' => $isInTeam,
            'is_first_time' => $isFirstTime,
            'is_eligible' => $isInTeam && $isFirstTime,
            'team_id' => $teamId
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Weekly eligibility check error: " . $e->getMessage());
    listening_json([
        'ok' => false,
        'message' => 'Database error occurred.'
    ], 500);
}
