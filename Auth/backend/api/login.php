<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/../config/config.php';
$allowedOrigins = is_array($config['allowed_origins'] ?? null) ? $config['allowed_origins'] : [];

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth_user.php';

auth_start_session();
auth_bootstrap_roles($pdo);

$data = json_decode(file_get_contents('php://input'));

if (empty($data->identifier) || empty($data->password)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Username/email and password are required.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user = auth_fetch_user_by_identifier($pdo, trim((string)$data->identifier));

    if ($user && password_verify((string)$data->password, (string)$user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['auth_user'] = auth_map_user_row($user);

        $checkinResult = perform_daily_checkin($pdo, (int)$user['user_id']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful.',
            'user' => $_SESSION['auth_user'],
            'username' => $user['username'],
            'checkin' => $checkinResult,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid username/email or password.',
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

function perform_daily_checkin(PDO $pdo, int $userId): array {
    try {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
        $today = date('Y-m-d', strtotime('+1 day'));
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

        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as cnt
            FROM score_records
            WHERE group_id = ?
              AND rule_id = 'routine1'
              AND user_id = ?
              AND DATE(record_time) = ?
        ");
        $checkStmt->execute([$teamId, $userId, $today]);
        $existing = $checkStmt->fetch();

        if ((int)$existing['cnt'] > 0) {
            return ['status' => 'already_checked_in', 'message' => 'Already checked in today'];
        }

        $ruleStmt = $pdo->prepare("SELECT base_score FROM score_rules WHERE rule_id = 'routine1'");
        $ruleStmt->execute();
        $rule = $ruleStmt->fetch();

        if (!$rule) {
            return ['status' => 'error', 'message' => 'Check-in rule not found'];
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

        return [
            'status' => 'success',
            'message' => 'Check-in successful',
            'points_earned' => $baseScore,
            'team_name' => $team['team_name']
        ];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
