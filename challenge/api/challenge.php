<?php
declare(strict_types=1);

require __DIR__ . '/../../forum-project/api/bootstrap.php';
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
    $username = trim((string)($user['username'] ?? 'Team'));
    return $username !== '' ? $username . "'s Team" : 'New Team';
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

    challenge_cleanup_expired_forming_teams($pdo, $weekStartDate);
    challenge_refresh_daily_ranks($pdo, $weekStartDate);

    $signedUp = challenge_signup_is_active($pdo, $userId, $weekStartDate);
    $teamRow = challenge_find_team_for_user($pdo, $userId, $weekStartDate);
    $team = $teamRow ? challenge_team_payload($pdo, $teamRow, $userId) : null;

    $phase = 'signup';
    if ($team && (string)($team['status'] ?? 'forming') === 'locked') {
        $phase = 'locked';
    } elseif ($team) {
        $phase = 'forming';
    } elseif ($signedUp) {
        $phase = 'chooser';
    }

    $signupStmt = $pdo->prepare("
        SELECT created_at
        FROM challenge_signups
        WHERE user_id = ?
          AND week_start_date = ?
          AND signup_status = 'active'
        LIMIT 1
    ");
    $signupStmt->execute([$userId, $weekStartDate]);
    $signupAt = (string)($signupStmt->fetchColumn() ?: '');

    $receivedStmt = $pdo->prepare("
        SELECT
            cti.invite_id,
            cti.status,
            cti.created_at,
            cti.responded_at,
            ct.team_id,
            ct.team_name,
            inviter.user_id AS inviter_user_id,
            inviter.username AS inviter_username
        FROM challenge_team_invites cti
        JOIN challenge_teams ct
          ON ct.team_id = cti.team_id
         AND ct.week_start_date = cti.week_start_date
        JOIN users inviter
          ON inviter.user_id = cti.inviter_user_id
        WHERE cti.invitee_user_id = ?
          AND cti.week_start_date = ?
        ORDER BY CASE WHEN cti.status = 'pending' THEN 0 ELSE 1 END, COALESCE(cti.responded_at, cti.created_at) DESC, cti.invite_id DESC
        LIMIT 20
    ");
    $receivedStmt->execute([$userId, $weekStartDate]);
    $receivedInvites = array_map(static function (array $row): array {
        return [
            'id' => (int)$row['invite_id'],
            'status' => (string)($row['status'] ?? 'pending'),
            'teamId' => (int)$row['team_id'],
            'teamName' => (string)($row['team_name'] ?? ''),
            'createdAt' => (string)($row['created_at'] ?? ''),
            'respondedAt' => (string)($row['responded_at'] ?? ''),
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
          AND ct.status = 'locked'
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

    $publicStmt = $pdo->prepare("
        SELECT
            l.listing_id,
            ct.team_id,
            ct.team_name,
            ct.expires_at,
            captain.username AS captain_username,
            COUNT(CASE WHEN ctm.membership_status = 'active' THEN 1 END) AS member_count
        FROM challenge_team_public_listings l
        JOIN challenge_teams ct
          ON ct.team_id = l.team_id
         AND ct.status = 'forming'
        JOIN users captain
          ON captain.user_id = ct.captain_user_id
        LEFT JOIN challenge_team_members ctm
          ON ctm.team_id = ct.team_id
        WHERE l.week_start_date = ?
          AND l.status = 'active'
        GROUP BY l.listing_id, ct.team_id, ct.team_name, ct.expires_at, captain.username
        ORDER BY ct.created_at DESC, l.listing_id DESC
    ");
    $publicStmt->execute([$weekStartDate]);
    $publicListings = array_map(static function (array $row) use ($team): array {
        $expiresAt = (string)($row['expires_at'] ?? '');
        $expiresAtDt = challenge_datetime_from_db($expiresAt);
        return [
            'listingId' => (int)$row['listing_id'],
            'teamId' => (int)$row['team_id'],
            'teamName' => (string)($row['team_name'] ?? ''),
            'captain' => (string)($row['captain_username'] ?? ''),
            'memberCount' => (int)($row['member_count'] ?? 0),
            'expiresAt' => $expiresAt,
            'expiresAtTs' => $expiresAtDt?->getTimestamp(),
            'secondsRemaining' => $expiresAtDt ? max(0, $expiresAtDt->getTimestamp() - challenge_now()->getTimestamp()) : 0,
            'isOwnTeam' => $team ? (int)$team['id'] === (int)$row['team_id'] : false,
            'isFull' => (int)($row['member_count'] ?? 0) >= CHALLENGE_MAX_MEMBERS,
        ];
    }, $publicStmt->fetchAll());

    $publicByTeamId = [];
    foreach ($publicListings as $listing) {
        $publicByTeamId[(int)$listing['teamId']] = $listing;
    }

    return [
        'cycle' => $cycle,
        'serverNowTs' => challenge_now()->getTimestamp(),
        'phase' => $phase,
        'signup' => [
            'isSignedUp' => $signedUp,
            'signedAt' => $signupAt,
        ],
        'team' => $team,
        'receivedInvites' => $receivedInvites,
        'sentInvites' => $sentInvites,
        'publicListings' => $publicListings,
        'publicListing' => $team ? ($publicByTeamId[(int)$team['id']] ?? null) : null,
        'leaderboard' => $leaderboard,
        'rules' => [
            'maxMembers' => CHALLENGE_MAX_MEMBERS,
            'formationSeconds' => CHALLENGE_FORMATION_SECONDS,
            'showLeaderboard' => count($leaderboard) > 0,
        ],
        'access' => [
            'isAdmin' => forum_is_admin($user),
            'canParticipate' => !forum_is_admin($user),
        ],
    ];
}

function challenge_create_team(PDO $pdo, array $user, array $cycle): array {
    $userId = (int)$user['user_id'];
    $weekStartDate = (string)$cycle['weekStartDate'];
    $weekEndDate = (string)$cycle['weekEndDate'];
    $defaultName = challenge_default_team_name($user);

    $teamStmt = $pdo->prepare("
        INSERT INTO challenge_teams (
            week_start_date,
            week_end_date,
            team_name,
            captain_user_id,
            score,
            daily_rank,
            rank_updated_on,
            status,
            expires_at,
            locked_at,
            team_name_confirmed_at,
            created_at,
            updated_at,
            archived_at
        ) VALUES (?, ?, ?, ?, 0, NULL, NULL, 'forming', DATE_ADD(NOW(), INTERVAL 1 HOUR), NULL, NULL, NOW(), NOW(), NULL)
    ");
    $teamStmt->execute([$weekStartDate, $weekEndDate, $defaultName, $userId]);
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

    $row = challenge_find_captain_team($pdo, $userId, $weekStartDate);
    if (!$row) {
        throw new RuntimeException('Failed to create team.', 500);
    }
    return $row;
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

if ($action === 'signup') {
    challenge_upsert_signup($pdo, $userId, $weekStartDate);
    challenge_publish_update('signup', [
        'weekStartDate' => $weekStartDate,
        'userIds' => [$userId],
    ]);
    forum_json([
        'ok' => true,
        'message' => 'You are registered for this week’s team challenge.',
        'state' => challenge_build_state($pdo, $user, $cycle),
    ]);
}

if ($action === 'create_team') {
    if (!challenge_signup_is_active($pdo, $userId, $weekStartDate)) {
        forum_json(['ok' => false, 'message' => 'Sign up first before creating a team.'], 422);
    }
    if (challenge_user_has_locked_team($pdo, $userId, $weekStartDate)) {
        forum_json(['ok' => false, 'message' => 'You are already in a completed team this week.'], 422);
    }
    if (challenge_find_team_for_user($pdo, $userId, $weekStartDate)) {
        forum_json(['ok' => false, 'message' => 'You already have a forming team this week.'], 422);
    }

    challenge_create_team($pdo, $user, $cycle);
    challenge_publish_update('team_created', [
        'weekStartDate' => $weekStartDate,
        'userIds' => [$userId],
    ]);
    forum_json([
        'ok' => true,
        'message' => 'Your 4-person team has been created.',
        'state' => challenge_build_state($pdo, $user, $cycle),
    ]);
}

if ($action === 'confirm_team_name') {
    $teamRow = challenge_find_captain_team($pdo, $userId, $weekStartDate);
    if (!$teamRow || (string)($teamRow['status'] ?? '') !== 'locked') {
        forum_json(['ok' => false, 'message' => 'You can confirm the team name only after the team is full.'], 403);
    }
    $teamName = challenge_clean_team_name((string)($input['teamName'] ?? ''), (string)$teamRow['team_name']);
    $stmt = $pdo->prepare("
        UPDATE challenge_teams
        SET team_name = ?, team_name_confirmed_at = NOW(), updated_at = NOW()
        WHERE team_id = ?
    ");
    $stmt->execute([$teamName, (int)$teamRow['team_id']]);
    challenge_publish_update('team_name_confirmed', [
        'teamId' => (int)$teamRow['team_id'],
        'weekStartDate' => $weekStartDate,
        'userIds' => [$userId],
    ]);
    forum_json([
        'ok' => true,
        'message' => 'Team name confirmed.',
        'state' => challenge_build_state($pdo, $user, $cycle),
    ]);
}

if ($action === 'send_invite') {
    $inviteeUsername = trim((string)($input['inviteeUsername'] ?? ''));
    if ($inviteeUsername === '') {
        forum_json(['ok' => false, 'message' => 'Enter a username to invite.'], 422);
    }

    $teamRow = challenge_find_captain_team($pdo, $userId, $weekStartDate);
    if (!$teamRow || (string)($teamRow['status'] ?? '') !== 'forming') {
        forum_json(['ok' => false, 'message' => 'Only captains of forming teams can send invites.'], 403);
    }
    if ((int)($teamRow['member_count'] ?? 0) >= CHALLENGE_MAX_MEMBERS) {
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
    if (!challenge_signup_is_active($pdo, $inviteeId, $weekStartDate)) {
        forum_json(['ok' => false, 'message' => 'That user has not signed up for this week’s challenge.'], 422);
    }
    if (challenge_user_has_locked_team($pdo, $inviteeId, $weekStartDate)) {
        forum_json(['ok' => false, 'message' => 'That user is already in a completed team this week.'], 422);
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
    $duplicateStmt->execute([(int)$teamRow['team_id'], $inviteeId, $weekStartDate]);
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
        (int)$teamRow['team_id'],
        $weekStartDate,
        $userId,
        $inviteeId,
        (string)$invitee['username'],
    ]);

    challenge_publish_update('invite_sent', [
        'teamId' => (int)$teamRow['team_id'],
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
                ct.status AS team_status
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
        if ((string)($invite['team_status'] ?? '') !== 'forming') {
            throw new RuntimeException('That team is no longer open for forming.', 422);
        }
        if (!challenge_signup_is_active($pdo, $userId, $weekStartDate)) {
            throw new RuntimeException('Sign up first before joining a team.', 422);
        }
        if (challenge_user_has_locked_team($pdo, $userId, $weekStartDate)) {
            throw new RuntimeException('You are already in a completed team this week.', 422);
        }

        if ($decision === 'decline') {
            $stmt = $pdo->prepare("
                UPDATE challenge_team_invites
                SET status = 'declined', responded_at = NOW(), updated_at = NOW()
                WHERE invite_id = ?
            ");
            $stmt->execute([$inviteId]);
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

        challenge_release_user_from_forming_team($pdo, $userId, $weekStartDate, (int)$invite['team_id']);
        if (!challenge_find_team_for_user($pdo, $userId, $weekStartDate)) {
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
        }

        $acceptStmt = $pdo->prepare("
            UPDATE challenge_team_invites
            SET status = 'accepted', responded_at = NOW(), updated_at = NOW()
            WHERE invite_id = ?
        ");
        $acceptStmt->execute([$inviteId]);

        $closeOtherStmt = $pdo->prepare("
            UPDATE challenge_team_invites
            SET status = 'cancelled', responded_at = NOW(), updated_at = NOW()
            WHERE invitee_user_id = ?
              AND week_start_date = ?
              AND status = 'pending'
              AND invite_id <> ?
        ");
        $closeOtherStmt->execute([$userId, $weekStartDate, $inviteId]);

        challenge_lock_team_if_full($pdo, (int)$invite['team_id'], $weekStartDate);
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
            'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Failed to update invite.',
        ], $status);
    }
}

if ($action === 'toggle_public_listing') {
    $teamRow = challenge_find_captain_team($pdo, $userId, $weekStartDate);
    if (!$teamRow || (string)($teamRow['status'] ?? '') !== 'forming') {
        forum_json(['ok' => false, 'message' => 'Only captains of forming teams can publish to the lobby.'], 403);
    }

    $teamId = (int)$teamRow['team_id'];
    $mode = trim((string)($input['mode'] ?? 'publish'));
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
        ]);
        forum_json([
            'ok' => true,
            'message' => 'Public listing removed.',
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
        ) VALUES (?, ?, 'active', NULL, NOW(), NOW())
        ON DUPLICATE KEY UPDATE status = 'active', updated_at = NOW()
    ");
    $stmt->execute([$teamId, $weekStartDate]);
    challenge_publish_update('public_opened', [
        'teamId' => $teamId,
        'weekStartDate' => $weekStartDate,
    ]);
    forum_json([
        'ok' => true,
        'message' => 'Team posted to the public lobby.',
        'state' => challenge_build_state($pdo, $user, $cycle),
    ]);
}

if ($action === 'join_public_team') {
    $teamId = (int)($input['teamId'] ?? 0);
    if ($teamId <= 0) {
        forum_json(['ok' => false, 'message' => 'Invalid team.'], 422);
    }
    if (!challenge_signup_is_active($pdo, $userId, $weekStartDate)) {
        forum_json(['ok' => false, 'message' => 'Sign up first before joining a team.'], 422);
    }
    if (challenge_user_has_locked_team($pdo, $userId, $weekStartDate)) {
        forum_json(['ok' => false, 'message' => 'You are already in a completed team this week.'], 422);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT
                ct.team_id,
                ct.team_name,
                ct.captain_user_id,
                ct.status
            FROM challenge_team_public_listings l
            JOIN challenge_teams ct
              ON ct.team_id = l.team_id
            WHERE l.team_id = ?
              AND l.week_start_date = ?
              AND l.status = 'active'
            FOR UPDATE
        ");
        $stmt->execute([$teamId, $weekStartDate]);
        $target = $stmt->fetch();
        if (!is_array($target) || (string)($target['status'] ?? '') !== 'forming') {
            throw new RuntimeException('That team is no longer open.', 404);
        }
        if ((int)$target['captain_user_id'] === $userId) {
            throw new RuntimeException('This is already your own team.', 422);
        }
        if (challenge_team_member_count($pdo, $teamId) >= CHALLENGE_MAX_MEMBERS) {
            throw new RuntimeException('That team is already full.', 422);
        }

        challenge_release_user_from_forming_team($pdo, $userId, $weekStartDate, $teamId);
        if (!challenge_find_team_for_user($pdo, $userId, $weekStartDate)) {
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
            $memberStmt->execute([$teamId, $userId]);
        }

        $inviteCloseStmt = $pdo->prepare("
            UPDATE challenge_team_invites
            SET status = 'cancelled', responded_at = NOW(), updated_at = NOW()
            WHERE invitee_user_id = ?
              AND week_start_date = ?
              AND status = 'pending'
        ");
        $inviteCloseStmt->execute([$userId, $weekStartDate]);

        challenge_lock_team_if_full($pdo, $teamId, $weekStartDate);
        $pdo->commit();

        challenge_publish_update('public_joined', [
            'teamId' => $teamId,
            'weekStartDate' => $weekStartDate,
            'userIds' => [$userId, (int)$target['captain_user_id']],
        ]);
        forum_json([
            'ok' => true,
            'message' => sprintf('You joined "%s".', (string)$target['team_name']),
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
            'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Failed to join team.',
        ], $status);
    }
}

forum_json(['ok' => false, 'message' => 'Unsupported action.'], 422);
