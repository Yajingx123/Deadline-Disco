<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    peer_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = peer_require_user();
$input = peer_input();
$inviteId = (int)($input['inviteId'] ?? 0);
$action = trim((string)($input['action'] ?? ''));

if ($inviteId <= 0 || !in_array($action, ['accept', 'decline'], true)) {
    peer_json(['ok' => false, 'message' => 'Invalid request.'], 422);
}

$pdo = peer_db();
$inviteStmt = $pdo->prepare("
    SELECT psi.invite_id, psi.space_id, psi.inviter_user_id, psi.invitee_user_id, psi.status
    FROM peer_space_invites psi
    JOIN peer_spaces ps ON ps.space_id = psi.space_id
    WHERE psi.invite_id = ?
      AND psi.invitee_user_id = ?
      AND psi.status = 'pending'
      AND ps.space_type = 'resonance'
    LIMIT 1
");
$inviteStmt->execute([$inviteId, (int)$user['user_id']]);
$inviteRow = $inviteStmt->fetch();

if (!$inviteRow) {
    peer_json(['ok' => false, 'message' => 'Invite not found.'], 404);
}

$inviteeId = (int)$inviteRow['invitee_user_id'];
$inviterId = (int)$inviteRow['inviter_user_id'];

if ($action === 'accept' && (peer_find_active_team_for_user($pdo, $inviteeId) || peer_find_active_team_for_user($pdo, $inviterId))) {
    peer_json(['ok' => false, 'message' => 'One of you is already in an active resonance team.'], 409);
}

$pdo->beginTransaction();
try {
    $inviteUpdate = $pdo->prepare("
        UPDATE peer_space_invites
        SET status = ?, responded_at = NOW(), updated_at = NOW()
        WHERE invite_id = ?
    ");
    $inviteUpdate->execute([$action === 'accept' ? 'accepted' : 'declined', $inviteId]);

    $memberUpdate = $pdo->prepare("
        UPDATE peer_space_members
        SET membership_status = ?, responded_at = NOW(), joined_at = ?, updated_at = NOW()
        WHERE space_id = ? AND user_id = ?
    ");
    $memberUpdate->execute([
        $action === 'accept' ? 'accepted' : 'declined',
        $action === 'accept' ? peer_now() : null,
        (int)$inviteRow['space_id'],
        $inviteeId,
    ]);

    $spaceUpdate = $pdo->prepare("
        UPDATE peer_spaces
        SET status = ?, activated_at = ?, updated_at = NOW()
        WHERE space_id = ?
    ");
    $spaceUpdate->execute([
        $action === 'accept' ? 'active' : 'declined',
        $action === 'accept' ? peer_now() : null,
        (int)$inviteRow['space_id'],
    ]);

    if ($action === 'accept') {
        $teamCreate = $pdo->prepare("
            INSERT INTO peer_resonance_teams (
                space_id, team_name, status, started_on, last_mutual_checkin_on, current_streak_days, longest_streak_days, created_at, updated_at
            ) VALUES (?, ?, 'active', ?, NULL, 0, 0, NOW(), NOW())
        ");
        $teamCreate->execute([
            (int)$inviteRow['space_id'],
            sprintf('Resonance %d', (int)$inviteRow['space_id']),
            peer_today(),
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    peer_json(['ok' => false, 'message' => 'Failed to update invite.'], 500);
}

peer_json([
    'ok' => true,
    'message' => $action === 'accept' ? 'Invite accepted.' : 'Invite declined.',
    'state' => peer_build_state($pdo, (int)$user['user_id']),
]);
