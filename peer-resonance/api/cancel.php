<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    peer_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = peer_require_user();
$input = peer_input();
$inviteId = (int)($input['inviteId'] ?? 0);

if ($inviteId <= 0) {
    peer_json(['ok' => false, 'message' => 'Invalid invite id.'], 422);
}

$pdo = peer_db();
$inviteStmt = $pdo->prepare("
    SELECT invite_id, space_id
    FROM peer_space_invites
    WHERE invite_id = ?
      AND inviter_user_id = ?
      AND status = 'pending'
    LIMIT 1
");
$inviteStmt->execute([$inviteId, (int)$user['user_id']]);
$inviteRow = $inviteStmt->fetch();

if (!$inviteRow) {
    peer_json(['ok' => false, 'message' => 'Invite not found.'], 404);
}

$pdo->beginTransaction();
try {
    $pdo->prepare("
        UPDATE peer_space_invites
        SET status = 'cancelled', responded_at = NOW(), updated_at = NOW()
        WHERE invite_id = ?
    ")->execute([$inviteId]);

    $pdo->prepare("
        UPDATE peer_space_members
        SET membership_status = 'removed', responded_at = NOW(), updated_at = NOW()
        WHERE space_id = ?
          AND membership_status = 'pending'
    ")->execute([(int)$inviteRow['space_id']]);

    $pdo->prepare("
        UPDATE peer_spaces
        SET status = 'cancelled', ended_at = NOW(), updated_at = NOW()
        WHERE space_id = ?
    ")->execute([(int)$inviteRow['space_id']]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    peer_json(['ok' => false, 'message' => 'Failed to cancel invite.'], 500);
}

peer_json([
    'ok' => true,
    'message' => 'Invite cancelled.',
    'state' => peer_build_state($pdo, (int)$user['user_id']),
]);
