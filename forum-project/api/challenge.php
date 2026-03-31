<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/challenge-lib.php';

const CHALLENGE_MAX_MEMBERS = 4;

function challenge_clean_team_name(string $raw, string $fallback): string {
    $value = trim(preg_replace('/\s+/', ' ', $raw) ?? '');
    if ($value === '') {
        return $fallback;
    }
    return mb_substr($value, 0, 120);
}

function challenge_default_team_name(array $user): string {
    $username = trim((string)($user['username'] ?? 'Squad'));
    return $username !== '' ? $username . "'s Squad" : 'New Squad';
}

function challenge_require_non_admin(array $user): void {
    if (forum_is_admin($user)) {
        forum_json([
            'ok' => false,
            'message' => 'Admins do not participate in weekly challenge teams.',
        ], 403);
    }
}

function challenge_publish_update(string $reason, array $extra = []): void {
    forum_realtime_publish('challenge.updated', array_merge([
        'reason' => $reason,
        'scope' => 'global',
    ], $extra));
}

function challenge_build_state(PDO $pdo, array $user, array $cycle): array {
    $userId = (int)($user['user_id'] ?? 0);
    $weekStartDate = (string)$cycle['weekStartDate'];

    challenge_refresh_daily_ranks($pdo, $weekStartDate);
    challenge_reconcile_user_memberships($pdo, $userId, $weekStartDate);

    $memberTeamRow = challenge_find_team_for_user($pdo, $userId, $weekStartDate, false);
    $lockedTeamRow = challenge_find_active_team_for_user($pdo, $userId, $weekStartDate);
    $captainTeamRow = challenge_find_captain_team($pdo, $userId, $weekStartDate);
    $teamRow = $memberTeamRow;
    if (
        !$teamRow
        || (
            $captainTeamRow
            && (int)($captainTeamRow['team_id'] ?? 0) === (int)($memberTeamRow['team_id'] ?? 0)
        )
    ) {
        $teamRow = $captainTeamRow ?: $lockedTeamRow;
    }
    $team = $teamRow ? challenge_team_payload($pdo, $teamRow, $userId) : null;
    $isTeamLocked = $lockedTeamRow && $team && (int)$lockedTeamRow['team_id'] === (int)$team['id'];

    $receivedStmt = $pdo->prepare("
        SELECT
            cti.invite_id,
            cti.created_at,
            cti.responded_at,
            cti.status,
            ct.team_id,
            ct.team_name,
            inviter.user_id AS inviter_user_id,
            inviter.username AS inviter_username
        FROM challenge_team_invites cti
        JOIN challenge_teams ct
          ON ct.team_id = cti.team_id
         AND ct.status = 'active'
        JOIN users inviter
          ON inviter.user_id = cti.inviter_user_id
        WHERE cti.invitee_user_id = ?
          AND cti.week_start_date = ?
          AND cti.status IN ('pending', 'accepted', 'declined', 'cancelled', 'expired')
        ORDER BY CASE WHEN cti.status = 'pending' THEN 0 ELSE 1 END, COALESCE(cti.responded_at, cti.created_at) DESC, cti.invite_id DESC
        LIMIT 20
    ");
    $receivedStmt->execute([$userId, $weekStartDate]);
    $receivedInvites = array_map(static function (array $row): array {
        return [
            'id' => (int)$row['invite_id'],
            'teamId' => (int)$row['team_id'],
            'teamName' => (string)($row['team_name'] ?? ''),
            'createdAt' => (string)($row['created_at'] ?? ''),
            'respondedAt' => (string)($row['responded_at'] ?? ''),
            'status' => (string)($row['status'] ?? 'pending'),
            'inviter' => [
                'id' => (int)($row['inviter_user_id'] ?? 0),
                'username' => (string)($row['inviter_username'] ?? 'Unknown'),
                'avatar' => forum_avatar_letters((string)($row['inviter_username'] ?? 'Unknown')),
            ],
        ];
    }, $receivedStmt->fetchAll());

    $sentInvites = [];
    if ($team) {
        $sentStmt = $pdo->prepare("
            SELECT
                cti.invite_id,
                cti.status,
                cti.created_at,
                invitee.user_id AS invitee_user_id,
                invitee.username AS invitee_username
            FROM challenge_team_invites cti
            JOIN users invitee ON invitee.user_id = cti.invitee_user_id
            WHERE cti.team_id = ?
              AND cti.week_start_date = ?
              AND cti.status = 'pending'
            ORDER BY cti.created_at DESC, cti.invite_id DESC
        ");
        $sentStmt->execute([(int)$team['id'], $weekStartDate]);
        $sentInvites = array_map(static function (array $row): array {
            return [
                'id' => (int)$row['invite_id'],
                'status' => (string)($row['status'] ?? 'pending'),
                'createdAt' => (string)($row['created_at'] ?? ''),
                'invitee' => [
                    'id' => (int)($row['invitee_user_id'] ?? 0),
                    'username' => (string)($row['invitee_username'] ?? 'Unknown'),
                    'avatar' => forum_avatar_letters((string)($row['invitee_username'] ?? 'Unknown')),
                ],
            ];
        }, $sentStmt->fetchAll());
    }

    $leaderboardStmt = $pdo->prepare("
        SELECT
            ct.team_id,
            ct.team_name,
            ct.score,
            ct.daily_rank,
            captain.username AS captain_username,
            COUNT(CASE WHEN ctm.membership_status = 'active' THEN 1 END) AS member_count
        FROM challenge_teams ct
        JOIN users captain ON captain.user_id = ct.captain_user_id
        LEFT JOIN challenge_team_members ctm
          ON ctm.team_id = ct.team_id
        WHERE ct.week_start_date = ?
          AND ct.status = 'active'
          AND ct.score > 0
        GROUP BY ct.team_id, ct.team_name, ct.score, ct.daily_rank, captain.username
        ORDER BY ct.daily_rank IS NULL, ct.daily_rank ASC, ct.score DESC, ct.team_id ASC
        LIMIT 8
    ");
    $leaderboardStmt->execute([$weekStartDate]);
    $leaderboard = array_map(static function (array $row): array {
        return [
            'teamId' => (int)$row['team_id'],
            'rank' => isset($row['daily_rank']) ? (int)$row['daily_rank'] : null,
            'teamName' => (string)($row['team_name'] ?? ''),
            'score' => (int)($row['score'] ?? 0),
            'memberCount' => (int)($row['member_count'] ?? 0),
            'captain' => (string)($row['captain_username'] ?? ''),
        ];
    }, $leaderboardStmt->fetchAll());

    $publicListingsStmt = $pdo->prepare("
        SELECT
            l.listing_id,
            ct.team_id,
            ct.team_name,
            ct.score,
            captain.username AS captain_username,
            l.description_text,
            COUNT(CASE WHEN ctm.membership_status = 'active' THEN 1 END) AS member_count
        FROM challenge_team_public_listings l
        JOIN challenge_teams ct
          ON ct.team_id = l.team_id
         AND ct.status = 'active'
        JOIN users captain
          ON captain.user_id = ct.captain_user_id
        LEFT JOIN challenge_team_members ctm
          ON ctm.team_id = ct.team_id
        WHERE l.week_start_date = ?
          AND l.status = 'active'
        GROUP BY l.listing_id, ct.team_id, ct.team_name, ct.score, captain.username, l.description_text
        ORDER BY ct.created_at DESC, l.listing_id DESC
    ");
    $publicListingsStmt->execute([$weekStartDate]);
    $currentUsername = (string)($user['username'] ?? '');
    $publicListings = array_map(static function (array $row) use ($team, $currentUsername): array {
        return [
            'listingId' => (int)$row['listing_id'],
            'teamId' => (int)$row['team_id'],
            'teamName' => (string)($row['team_name'] ?? ''),
            'captain' => (string)($row['captain_username'] ?? ''),
            'score' => (int)($row['score'] ?? 0),
            'memberCount' => (int)($row['member_count'] ?? 0),
            'description' => trim((string)($row['description_text'] ?? '')),
            'isOwnTeam' => $team ? (int)$team['id'] === (int)$row['team_id'] : false,
            'isFull' => (int)($row['member_count'] ?? 0) >= CHALLENGE_MAX_MEMBERS,
            'isCaptainSelf' => (string)($row['captain_username'] ?? '') === $currentUsername,
        ];
    }, $publicListingsStmt->fetchAll());

    $publicByTeamId = [];
    foreach ($publicListings as $listing) {
        $publicByTeamId[(int)$listing['teamId']] = $listing;
    }

    $myJoinRequestStmt = $pdo->prepare("
        SELECT team_id, status
        FROM challenge_team_join_requests
        WHERE requester_user_id = ?
          AND week_start_date = ?
          AND status = 'pending'
    ");
    $myJoinRequestStmt->execute([$userId, $weekStartDate]);
    $myPendingRequests = [];
    foreach ($myJoinRequestStmt->fetchAll() as $row) {
        $myPendingRequests[(int)$row['team_id']] = (string)$row['status'];
    }

    $captainRequests = [];
    if ($team && $team['isCaptain']) {
        $captainRequestStmt = $pdo->prepare("
            SELECT
                r.join_request_id,
                r.team_id,
                r.message_text,
                r.created_at,
                r.responded_at,
                r.status,
                requester.user_id AS requester_user_id,
                requester.username AS requester_username,
                requester.email AS requester_email
            FROM challenge_team_join_requests r
            JOIN users requester ON requester.user_id = r.requester_user_id
            WHERE r.team_id = ?
              AND r.week_start_date = ?
              AND r.status IN ('pending', 'accepted', 'declined', 'cancelled', 'expired')
            ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END, COALESCE(r.responded_at, r.created_at) DESC, r.join_request_id DESC
            LIMIT 20
        ");
        $captainRequestStmt->execute([(int)$team['id'], $weekStartDate]);
        $captainRequests = array_map(static function (array $row): array {
            return [
                'id' => (int)$row['join_request_id'],
                'teamId' => (int)$row['team_id'],
                'message' => (string)($row['message_text'] ?? ''),
                'createdAt' => (string)($row['created_at'] ?? ''),
                'respondedAt' => (string)($row['responded_at'] ?? ''),
                'status' => (string)($row['status'] ?? 'pending'),
                'requester' => [
                    'id' => (int)($row['requester_user_id'] ?? 0),
                    'username' => (string)($row['requester_username'] ?? 'Unknown'),
                    'email' => (string)($row['requester_email'] ?? ''),
                    'avatar' => forum_avatar_letters((string)($row['requester_username'] ?? 'Unknown')),
                ],
            ];
        }, $captainRequestStmt->fetchAll());
    }

    $publicListings = array_map(static function (array $listing) use ($myPendingRequests): array {
        $listing['hasPendingRequest'] = isset($myPendingRequests[(int)$listing['teamId']]);
        return $listing;
    }, $publicListings);

    return [
        'cycle' => $cycle,
        'team' => $team,
        'receivedInvites' => $receivedInvites,
        'sentInvites' => $sentInvites,
        'captainJoinRequests' => $captainRequests,
        'publicListings' => $publicListings,
        'publicListing' => $team ? ($publicByTeamId[(int)$team['id']] ?? null) : null,
        'leaderboard' => $leaderboard,
        'rules' => [
            'maxMembers' => CHALLENGE_MAX_MEMBERS,
            'showLeaderboard' => count($leaderboard) > 0,
        ],
        'teamLocked' => (bool)$isTeamLocked,
        'access' => [
            'isAdmin' => forum_is_admin($user),
            'canParticipate' => !forum_is_admin($user),
        ],
    ];
}

function challenge_ensure_captain_team(PDO $pdo, array $user, array $cycle, string $teamName = ''): array {
    $userId = (int)$user['user_id'];
    $weekStartDate = (string)$cycle['weekStartDate'];
    $weekEndDate = (string)$cycle['weekEndDate'];

    $teamRow = challenge_find_captain_team($pdo, $userId, $weekStartDate);
    if ($teamRow) {
        if ((string)($teamRow['member_role'] ?? 'member') !== 'captain') {
            forum_json([
                'ok' => false,
                'message' => 'Only the captain can send challenge invitations.',
            ], 403);
        }

        $cleanName = challenge_clean_team_name($teamName, (string)$teamRow['team_name']);
        if ($cleanName !== (string)$teamRow['team_name']) {
            $renameStmt = $pdo->prepare("
                UPDATE challenge_teams
                SET team_name = ?, updated_at = NOW()
                WHERE team_id = ?
            ");
            $renameStmt->execute([$cleanName, (int)$teamRow['team_id']]);
            $teamRow['team_name'] = $cleanName;
        }
        return $teamRow;
    }

    $cleanName = challenge_clean_team_name($teamName, challenge_default_team_name($user));
    $createTeamStmt = $pdo->prepare("
        INSERT INTO challenge_teams (
            week_start_date,
            week_end_date,
            team_name,
            captain_user_id,
            score,
            status,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, 0, 'active', NOW(), NOW())
    ");
    $createTeamStmt->execute([$weekStartDate, $weekEndDate, $cleanName, $userId]);
    $teamId = (int)$pdo->lastInsertId();

    $memberStmt = $pdo->prepare("
        INSERT INTO challenge_team_members (
            team_id,
            user_id,
            member_role,
            membership_status,
            joined_at,
            created_at,
            updated_at
        ) VALUES (?, ?, 'captain', 'active', NOW(), NOW(), NOW())
    ");
    $memberStmt->execute([$teamId, $userId]);

    return challenge_find_active_team_for_user($pdo, $userId, $weekStartDate) ?: [
        'team_id' => $teamId,
        'team_name' => $cleanName,
        'captain_user_id' => $userId,
        'member_role' => 'captain',
        'score' => 0,
        'daily_rank' => null,
        'rank_updated_on' => null,
        'member_count' => 1,
    ];
}

$user = forum_require_user();
$pdo = forum_db();
$cycle = challenge_maintain_weekly_cycle($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    forum_json([
        'ok' => true,
        'state' => challenge_build_state($pdo, $user, $cycle),
    ]);
}

challenge_require_non_admin($user);

$input = forum_input();
$action = trim((string)($input['action'] ?? ''));
$userId = (int)($user['user_id'] ?? 0);
$weekStartDate = (string)$cycle['weekStartDate'];

if ($action === 'rename_team') {
    $teamRow = challenge_find_captain_team($pdo, $userId, $weekStartDate);
    if (!$teamRow || (string)($teamRow['member_role'] ?? '') !== 'captain') {
        forum_json(['ok' => false, 'message' => 'Only the captain can rename the team.'], 403);
    }

    $teamName = challenge_clean_team_name((string)($input['teamName'] ?? ''), (string)$teamRow['team_name']);
    $stmt = $pdo->prepare("
        UPDATE challenge_teams
        SET team_name = ?, updated_at = NOW()
        WHERE team_id = ?
    ");
    $stmt->execute([$teamName, (int)$teamRow['team_id']]);

    challenge_publish_update('team_renamed', [
        'teamId' => (int)$teamRow['team_id'],
        'weekStartDate' => $weekStartDate,
        'userIds' => [$userId],
    ]);

    forum_json([
        'ok' => true,
        'message' => 'Team name updated.',
        'state' => challenge_build_state($pdo, $user, $cycle),
    ]);
}

if ($action === 'send_invite') {
    $inviteeUsername = trim((string)($input['inviteeUsername'] ?? ''));
    if ($inviteeUsername === '') {
        forum_json(['ok' => false, 'message' => 'Enter a username to invite.'], 422);
    }

    $teamRow = challenge_ensure_captain_team($pdo, $user, $cycle, (string)($input['teamName'] ?? ''));
    $teamId = (int)($teamRow['team_id'] ?? 0);
    $memberCount = (int)($teamRow['member_count'] ?? 1);
    if ($memberCount >= CHALLENGE_MAX_MEMBERS) {
        forum_json(['ok' => false, 'message' => 'This team is already full.'], 422);
    }

    $userStmt = $pdo->prepare("
        SELECT user_id, username, role
        FROM users
        WHERE LOWER(username) = LOWER(?)
        LIMIT 1
    ");
    $userStmt->execute([$inviteeUsername]);
    $invitee = $userStmt->fetch();
    if (!is_array($invitee)) {
        forum_json(['ok' => false, 'message' => 'No user found with that username.'], 404);
    }

    $inviteeId = (int)($invitee['user_id'] ?? 0);
    if ($inviteeId === $userId) {
        forum_json(['ok' => false, 'message' => 'You cannot invite yourself.'], 422);
    }
    if ((string)($invitee['role'] ?? 'user') === 'admin') {
        forum_json(['ok' => false, 'message' => 'Admins are excluded from challenge teams.'], 422);
    }
    if (challenge_user_has_active_team($pdo, $inviteeId, $weekStartDate)) {
        forum_json(['ok' => false, 'message' => 'That user is already on a team this week.'], 422);
    }

    $duplicateStmt = $pdo->prepare("
        SELECT invite_id
        FROM challenge_team_invites
        WHERE team_id = ?
          AND invitee_user_id = ?
          AND week_start_date = ?
          AND status = 'pending'
        LIMIT 1
    ");
    $duplicateStmt->execute([$teamId, $inviteeId, $weekStartDate]);
    if ($duplicateStmt->fetch()) {
        forum_json(['ok' => false, 'message' => 'A pending invite already exists for that user.'], 422);
    }

    $inviteStmt = $pdo->prepare("
        INSERT INTO challenge_team_invites (
            team_id,
            week_start_date,
            inviter_user_id,
            invitee_user_id,
            invitee_username,
            status,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
    ");
    $inviteStmt->execute([
        $teamId,
        $weekStartDate,
        $userId,
        $inviteeId,
        (string)$invitee['username'],
    ]);

    challenge_publish_update('invite_sent', [
        'teamId' => $teamId,
        'weekStartDate' => $weekStartDate,
        'userIds' => [$userId, $inviteeId],
    ]);

    forum_json([
        'ok' => true,
        'message' => 'Invite sent.',
        'state' => challenge_build_state($pdo, $user, $cycle),
    ]);
}

if ($action === 'respond_invite') {
    $inviteId = (int)($input['inviteId'] ?? 0);
    $decision = trim((string)($input['decision'] ?? ''));
    if ($inviteId <= 0 || !in_array($decision, ['accept', 'decline'], true)) {
        forum_json(['ok' => false, 'message' => 'Invalid invite response.'], 422);
    }

    $pdo->beginTransaction();

    try {
        $inviteStmt = $pdo->prepare("
            SELECT
                cti.invite_id,
                cti.team_id,
                cti.status,
                ct.team_name,
                ct.status AS team_status,
                (
                    SELECT COUNT(*)
                    FROM challenge_team_members x
                    WHERE x.team_id = cti.team_id
                      AND x.membership_status = 'active'
                ) AS member_count
            FROM challenge_team_invites cti
            JOIN challenge_teams ct ON ct.team_id = cti.team_id
            WHERE cti.invite_id = ?
              AND cti.invitee_user_id = ?
              AND cti.week_start_date = ?
            FOR UPDATE
        ");
        $inviteStmt->execute([$inviteId, $userId, $weekStartDate]);
        $invite = $inviteStmt->fetch();

        if (!is_array($invite) || (string)($invite['status'] ?? '') !== 'pending') {
            throw new RuntimeException('That invite is no longer available.', 404);
        }
        if ((string)($invite['team_status'] ?? '') !== 'active') {
            throw new RuntimeException('That team is no longer active.', 422);
        }

        if ($decision === 'decline') {
            $declineStmt = $pdo->prepare("
                UPDATE challenge_team_invites
                SET status = 'declined', responded_at = NOW(), updated_at = NOW()
                WHERE invite_id = ?
            ");
            $declineStmt->execute([$inviteId]);
            $pdo->commit();

            challenge_publish_update('invite_declined', [
                'teamId' => (int)$invite['team_id'],
                'weekStartDate' => $weekStartDate,
                'userIds' => [$userId],
            ]);

            forum_json([
                'ok' => true,
                'message' => 'Invite declined.',
                'state' => challenge_build_state($pdo, $user, $cycle),
            ]);
        }

        if (challenge_user_has_active_team($pdo, $userId, $weekStartDate)) {
            throw new RuntimeException('You already joined a team this week.', 422);
        }
        if ((int)($invite['member_count'] ?? 0) >= CHALLENGE_MAX_MEMBERS) {
            $fullStmt = $pdo->prepare("
                UPDATE challenge_team_invites
                SET status = 'expired', responded_at = NOW(), updated_at = NOW()
                WHERE invite_id = ?
            ");
            $fullStmt->execute([$inviteId]);
            throw new RuntimeException('That team is already full.', 422);
        }

        $acceptStmt = $pdo->prepare("
            UPDATE challenge_team_invites
            SET status = 'accepted', responded_at = NOW(), updated_at = NOW()
            WHERE invite_id = ?
        ");
        $acceptStmt->execute([$inviteId]);

        challenge_release_user_from_provisional_teams($pdo, $userId, $weekStartDate, (int)$invite['team_id']);

        $memberStmt = $pdo->prepare("
            INSERT INTO challenge_team_members (
                team_id,
                user_id,
                member_role,
                membership_status,
                joined_at,
                created_at,
                updated_at
            ) VALUES (?, ?, 'member', 'active', NOW(), NOW(), NOW())
        ");
        $memberStmt->execute([(int)$invite['team_id'], $userId]);

        $closeOtherInvitesStmt = $pdo->prepare("
            UPDATE challenge_team_invites
            SET status = 'cancelled', responded_at = NOW(), updated_at = NOW()
            WHERE invitee_user_id = ?
              AND week_start_date = ?
              AND status = 'pending'
        ");
        $closeOtherInvitesStmt->execute([$userId, $weekStartDate]);

        $teamFullStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM challenge_team_members
            WHERE team_id = ?
              AND membership_status = 'active'
        ");
        $teamFullStmt->execute([(int)$invite['team_id']]);
        $newCount = (int)($teamFullStmt->fetchColumn() ?: 0);

        if ($newCount >= CHALLENGE_MAX_MEMBERS) {
            $expireTeamInvitesStmt = $pdo->prepare("
                UPDATE challenge_team_invites
                SET status = 'expired', responded_at = NOW(), updated_at = NOW()
                WHERE team_id = ?
                  AND week_start_date = ?
                  AND status = 'pending'
            ");
            $expireTeamInvitesStmt->execute([(int)$invite['team_id'], $weekStartDate]);

            $expireJoinRequestsStmt = $pdo->prepare("
                UPDATE challenge_team_join_requests
                SET status = 'expired', responded_at = NOW(), updated_at = NOW()
                WHERE team_id = ?
                  AND week_start_date = ?
                  AND status = 'pending'
            ");
            $expireJoinRequestsStmt->execute([(int)$invite['team_id'], $weekStartDate]);

            $closeListingStmt = $pdo->prepare("
                UPDATE challenge_team_public_listings
                SET status = 'closed', updated_at = NOW()
                WHERE team_id = ?
                  AND week_start_date = ?
                  AND status = 'active'
            ");
            $closeListingStmt->execute([(int)$invite['team_id'], $weekStartDate]);
        }

        $pdo->commit();

        challenge_publish_update('invite_accepted', [
            'teamId' => (int)$invite['team_id'],
            'weekStartDate' => $weekStartDate,
            'userIds' => [$userId],
        ]);

        forum_json([
            'ok' => true,
            'message' => sprintf('You joined "%s".', (string)$invite['team_name']),
            'state' => challenge_build_state($pdo, $user, $cycle),
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $status = (int)$e->getCode();
        if ($status < 400 || $status >= 600) {
            $status = 500;
        }
        forum_json([
            'ok' => false,
            'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Failed to respond to invite.',
        ], $status);
    }
}

if ($action === 'toggle_public_listing') {
    $teamRow = challenge_ensure_captain_team($pdo, $user, $cycle, (string)($input['teamName'] ?? ''));
    $teamId = (int)($teamRow['team_id'] ?? 0);
    $mode = trim((string)($input['mode'] ?? 'publish'));
    $description = mb_substr(trim((string)($input['description'] ?? '')), 0, 255);

    if ($mode === 'close') {
        $stmt = $pdo->prepare("
            UPDATE challenge_team_public_listings
            SET status = 'closed', updated_at = NOW()
            WHERE team_id = ?
              AND week_start_date = ?
              AND status = 'active'
        ");
        $stmt->execute([$teamId, $weekStartDate]);
        challenge_publish_update('public_closed', [
            'teamId' => $teamId,
            'weekStartDate' => $weekStartDate,
            'userIds' => [$userId],
        ]);
        forum_json([
            'ok' => true,
            'message' => 'Public listing closed.',
            'state' => challenge_build_state($pdo, $user, $cycle),
        ]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO challenge_team_public_listings (
            team_id,
            week_start_date,
            status,
            description_text,
            created_at,
            updated_at
        ) VALUES (?, ?, 'active', ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            status = 'active',
            description_text = VALUES(description_text),
            updated_at = NOW()
    ");
    $stmt->execute([$teamId, $weekStartDate, $description]);

    challenge_publish_update('public_opened', [
        'teamId' => $teamId,
        'weekStartDate' => $weekStartDate,
        'userIds' => [$userId],
    ]);

    forum_json([
        'ok' => true,
        'message' => 'Team posted to public lobby.',
        'state' => challenge_build_state($pdo, $user, $cycle),
    ]);
}

if ($action === 'request_join_public') {
    $teamId = (int)($input['teamId'] ?? 0);
    $messageText = mb_substr(trim((string)($input['message'] ?? '')), 0, 255);
    if ($teamId <= 0) {
        forum_json(['ok' => false, 'message' => 'Invalid public team.'], 422);
    }
    if (challenge_user_has_active_team($pdo, $userId, $weekStartDate)) {
        forum_json(['ok' => false, 'message' => 'You already have a team this week.'], 422);
    }

    $teamStmt = $pdo->prepare("
        SELECT ct.team_id, ct.team_name, ct.captain_user_id
        FROM challenge_team_public_listings l
        JOIN challenge_teams ct
          ON ct.team_id = l.team_id
         AND ct.status = 'active'
        WHERE l.team_id = ?
          AND l.week_start_date = ?
          AND l.status = 'active'
        LIMIT 1
    ");
    $teamStmt->execute([$teamId, $weekStartDate]);
    $publicTeam = $teamStmt->fetch();
    if (!is_array($publicTeam)) {
        forum_json(['ok' => false, 'message' => 'That public team is no longer available.'], 404);
    }
    if ((int)$publicTeam['captain_user_id'] === $userId) {
        forum_json(['ok' => false, 'message' => 'This is already your own team.'], 422);
    }
    if (!challenge_team_has_capacity($pdo, $teamId, CHALLENGE_MAX_MEMBERS)) {
        forum_json(['ok' => false, 'message' => 'That team is already full.'], 422);
    }

    $dupStmt = $pdo->prepare("
        SELECT join_request_id
        FROM challenge_team_join_requests
        WHERE team_id = ?
          AND requester_user_id = ?
          AND week_start_date = ?
          AND status = 'pending'
        LIMIT 1
    ");
    $dupStmt->execute([$teamId, $userId, $weekStartDate]);
    if ($dupStmt->fetch()) {
        forum_json(['ok' => false, 'message' => 'You already sent a request to this team.'], 422);
    }

    $closeOtherStmt = $pdo->prepare("
        UPDATE challenge_team_join_requests
        SET status = 'cancelled', responded_at = NOW(), updated_at = NOW()
        WHERE requester_user_id = ?
          AND week_start_date = ?
          AND status = 'pending'
    ");
    $closeOtherStmt->execute([$userId, $weekStartDate]);

    $insertStmt = $pdo->prepare("
        INSERT INTO challenge_team_join_requests (
            team_id,
            week_start_date,
            requester_user_id,
            captain_user_id,
            status,
            message_text,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, 'pending', ?, NOW(), NOW())
    ");
    $insertStmt->execute([
        $teamId,
        $weekStartDate,
        $userId,
        (int)$publicTeam['captain_user_id'],
        $messageText,
    ]);

    challenge_publish_update('join_request_sent', [
        'teamId' => $teamId,
        'weekStartDate' => $weekStartDate,
        'userIds' => [$userId, (int)$publicTeam['captain_user_id']],
    ]);

    forum_json([
        'ok' => true,
        'message' => sprintf('Join request sent to "%s".', (string)$publicTeam['team_name']),
        'state' => challenge_build_state($pdo, $user, $cycle),
    ]);
}

if ($action === 'respond_join_request') {
    $requestId = (int)($input['requestId'] ?? 0);
    $decision = trim((string)($input['decision'] ?? ''));
    if ($requestId <= 0 || !in_array($decision, ['accept', 'decline'], true)) {
        forum_json(['ok' => false, 'message' => 'Invalid join request response.'], 422);
    }

    $pdo->beginTransaction();
    try {
        $requestStmt = $pdo->prepare("
            SELECT
                r.join_request_id,
                r.team_id,
                r.requester_user_id,
                r.status,
                ct.team_name,
                ct.status AS team_status
            FROM challenge_team_join_requests r
            JOIN challenge_teams ct ON ct.team_id = r.team_id
            WHERE r.join_request_id = ?
              AND r.captain_user_id = ?
              AND r.week_start_date = ?
            FOR UPDATE
        ");
        $requestStmt->execute([$requestId, $userId, $weekStartDate]);
        $request = $requestStmt->fetch();

        if (!is_array($request) || (string)($request['status'] ?? '') !== 'pending') {
            throw new RuntimeException('That join request is no longer available.', 404);
        }
        if ((string)($request['team_status'] ?? '') !== 'active') {
            throw new RuntimeException('That team is no longer active.', 422);
        }

        if ($decision === 'decline') {
            $declineStmt = $pdo->prepare("
                UPDATE challenge_team_join_requests
                SET status = 'declined', responded_at = NOW(), updated_at = NOW()
                WHERE join_request_id = ?
            ");
            $declineStmt->execute([$requestId]);
            $pdo->commit();
            challenge_publish_update('join_request_declined', [
                'teamId' => (int)$request['team_id'],
                'weekStartDate' => $weekStartDate,
                'userIds' => [$userId, (int)$request['requester_user_id']],
            ]);
            forum_json([
                'ok' => true,
                'message' => 'Join request declined.',
                'state' => challenge_build_state($pdo, $user, $cycle),
            ]);
        }

        if (challenge_user_has_active_team($pdo, (int)$request['requester_user_id'], $weekStartDate)) {
            throw new RuntimeException('That user already joined another team.', 422);
        }
        if (!challenge_team_has_capacity($pdo, (int)$request['team_id'], CHALLENGE_MAX_MEMBERS)) {
            throw new RuntimeException('Your team is already full.', 422);
        }

        $acceptStmt = $pdo->prepare("
            UPDATE challenge_team_join_requests
            SET status = 'accepted', responded_at = NOW(), updated_at = NOW()
            WHERE join_request_id = ?
        ");
        $acceptStmt->execute([$requestId]);

        challenge_release_user_from_provisional_teams($pdo, (int)$request['requester_user_id'], $weekStartDate, (int)$request['team_id']);

        $memberStmt = $pdo->prepare("
            INSERT INTO challenge_team_members (
                team_id,
                user_id,
                member_role,
                membership_status,
                joined_at,
                created_at,
                updated_at
            ) VALUES (?, ?, 'member', 'active', NOW(), NOW(), NOW())
        ");
        $memberStmt->execute([(int)$request['team_id'], (int)$request['requester_user_id']]);

        $closeOtherRequestsStmt = $pdo->prepare("
            UPDATE challenge_team_join_requests
            SET status = 'cancelled', responded_at = NOW(), updated_at = NOW()
            WHERE requester_user_id = ?
              AND week_start_date = ?
              AND status = 'pending'
        ");
        $closeOtherRequestsStmt->execute([(int)$request['requester_user_id'], $weekStartDate]);

        $closeOtherInvitesStmt = $pdo->prepare("
            UPDATE challenge_team_invites
            SET status = 'cancelled', responded_at = NOW(), updated_at = NOW()
            WHERE invitee_user_id = ?
              AND week_start_date = ?
              AND status = 'pending'
        ");
        $closeOtherInvitesStmt->execute([(int)$request['requester_user_id'], $weekStartDate]);

        if (!challenge_team_has_capacity($pdo, (int)$request['team_id'], CHALLENGE_MAX_MEMBERS)) {
            $expirePublicRequestsStmt = $pdo->prepare("
                UPDATE challenge_team_join_requests
                SET status = 'expired', responded_at = NOW(), updated_at = NOW()
                WHERE team_id = ?
                  AND week_start_date = ?
                  AND status = 'pending'
            ");
            $expirePublicRequestsStmt->execute([(int)$request['team_id'], $weekStartDate]);

            $closeListingStmt = $pdo->prepare("
                UPDATE challenge_team_public_listings
                SET status = 'closed', updated_at = NOW()
                WHERE team_id = ?
                  AND week_start_date = ?
                  AND status = 'active'
            ");
            $closeListingStmt->execute([(int)$request['team_id'], $weekStartDate]);
        }

        $pdo->commit();
        challenge_publish_update('join_request_accepted', [
            'teamId' => (int)$request['team_id'],
            'weekStartDate' => $weekStartDate,
            'userIds' => [$userId, (int)$request['requester_user_id']],
        ]);
        forum_json([
            'ok' => true,
            'message' => sprintf('Approved request for "%s".', (string)$request['team_name']),
            'state' => challenge_build_state($pdo, $user, $cycle),
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $status = (int)$e->getCode();
        if ($status < 400 || $status >= 600) {
            $status = 500;
        }
        forum_json([
            'ok' => false,
            'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Failed to respond to join request.',
        ], $status);
    }
}

forum_json(['ok' => false, 'message' => 'Unsupported action.'], 422);
