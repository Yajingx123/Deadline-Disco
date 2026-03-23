<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    peer_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = peer_require_user();
$pdo = peer_db();
$activeTeam = peer_find_active_team_for_user($pdo, (int)$user['user_id']);

if (!$activeTeam) {
    peer_json(['ok' => false, 'message' => 'You are not in an active resonance team.'], 409);
}

$today = peer_today();

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare("
        INSERT INTO peer_resonance_daily_logs (
            team_id, user_id, checkin_date, log_status, source, created_at
        ) VALUES (?, ?, ?, 'committed', 'owner', NOW())
        ON DUPLICATE KEY UPDATE source = VALUES(source)
    ");
    $insert->execute([
        (int)$activeTeam['team_id'],
        (int)$user['user_id'],
        $today,
    ]);

    $metrics = peer_update_team_streak($pdo, (int)$activeTeam['team_id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    peer_json(['ok' => false, 'message' => 'Failed to record today\'s commitment.'], 500);
}

peer_json([
    'ok' => true,
    'message' => 'Commitment recorded.',
    'metrics' => $metrics,
    'state' => peer_build_state($pdo, (int)$user['user_id']),
]);
