<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    peer_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = peer_require_user();
$input = peer_input();
$inviteeUsername = trim((string)($input['username'] ?? ''));

if ($inviteeUsername === '') {
    peer_json(['ok' => false, 'message' => 'Please enter a username.'], 422);
}

$pdo = peer_db();
$inviterId = (int)$user['user_id'];
$invitee = peer_find_user_by_username($pdo, $inviteeUsername);

if (!$invitee) {
    peer_json(['ok' => false, 'message' => 'That username does not exist.'], 404);
}

$inviteeId = (int)$invitee['user_id'];
if ($inviteeId === $inviterId) {
    peer_json(['ok' => false, 'message' => 'You cannot invite yourself.'], 422);
}

if (peer_find_active_team_for_user($pdo, $inviterId) || peer_find_active_team_for_user($pdo, $inviteeId)) {
    peer_json(['ok' => false, 'message' => 'One of you is already in an active resonance team.'], 409);
}

if (peer_find_pending_invite_between($pdo, $inviterId, $inviteeId)) {
    peer_json(['ok' => false, 'message' => 'A pending resonance invite already exists between you two.'], 409);
}

$pdo->beginTransaction();
try {
    $spaceStmt = $pdo->prepare("
        INSERT INTO peer_spaces (space_type, created_by_user_id, title, status, max_members, metadata_json, created_at, updated_at)
        VALUES ('resonance', ?, ?, 'pending', 2, NULL, NOW(), NOW())
    ");
    $spaceStmt->execute([
        $inviterId,
        sprintf('%s + %s resonance', (string)$user['username'], (string)$invitee['username']),
    ]);
    $spaceId = (int)$pdo->lastInsertId();

    $memberStmt = $pdo->prepare("
        INSERT INTO peer_space_members (
            space_id, user_id, member_role, membership_status, invited_by_user_id, responded_at, joined_at, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $memberStmt->execute([$spaceId, $inviterId, 'owner', 'accepted', $inviterId, peer_now(), peer_now()]);
    $memberStmt->execute([$spaceId, $inviteeId, 'member', 'pending', $inviterId, null, null]);

    $inviteStmt = $pdo->prepare("
        INSERT INTO peer_space_invites (
            space_id, inviter_user_id, invitee_user_id, invite_type, status, invite_message, expires_at, responded_at, created_at, updated_at
        ) VALUES (?, ?, ?, 'resonance', 'pending', NULL, DATE_ADD(NOW(), INTERVAL 7 DAY), NULL, NOW(), NOW())
    ");
    $inviteStmt->execute([$spaceId, $inviterId, $inviteeId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    peer_json(['ok' => false, 'message' => 'Failed to send invite.'], 500);
}

peer_json([
    'ok' => true,
    'message' => 'Invite sent.',
    'state' => peer_build_state($pdo, $inviterId),
]);
