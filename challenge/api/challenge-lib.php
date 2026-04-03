<?php
declare(strict_types=1);

if (!defined('CHALLENGE_FORMATION_SECONDS')) {
    define('CHALLENGE_FORMATION_SECONDS', 3600);
}

function challenge_timezone(): DateTimeZone {
    static $tz = null;
    if ($tz instanceof DateTimeZone) {
        return $tz;
    }
    $tz = new DateTimeZone('Asia/Shanghai');
    return $tz;
}

function challenge_now(): DateTimeImmutable {
    return new DateTimeImmutable('now', challenge_timezone());
}

function challenge_datetime_from_db(?string $value): ?DateTimeImmutable {
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, challenge_timezone());
    return $dt instanceof DateTimeImmutable ? $dt : null;
}

function challenge_cycle_now(): array {
    $now = challenge_now();
    $weekday = (int)$now->format('N');
    $weekStart = $now->setTime(0, 0)->modify('-' . ($weekday - 1) . ' days');
    $weekEnd = $weekStart->modify('+6 days');

    return [
        'weekStartDate' => $weekStart->format('Y-m-d'),
        'weekEndDate' => $weekEnd->format('Y-m-d'),
        'label' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
        'resetRule' => 'Challenge teams reset every Monday at 00:00.',
        'formationSeconds' => CHALLENGE_FORMATION_SECONDS,
    ];
}

function challenge_meta_get(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare('SELECT meta_value FROM challenge_meta WHERE meta_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return is_string($value) ? $value : $default;
}

function challenge_meta_set(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare("
        INSERT INTO challenge_meta (meta_key, meta_value, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = NOW()
    ");
    $stmt->execute([$key, $value]);
}

function challenge_team_member_count(PDO $pdo, int $teamId): int {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM challenge_team_members
        WHERE team_id = ?
          AND membership_status = 'active'
    ");
    $stmt->execute([$teamId]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function challenge_signup_is_active(PDO $pdo, int $userId, string $weekStartDate): bool {
    $stmt = $pdo->prepare("
        SELECT signup_id
        FROM challenge_signups
        WHERE user_id = ?
          AND week_start_date = ?
          AND signup_status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$userId, $weekStartDate]);
    return (bool)$stmt->fetchColumn();
}

function challenge_upsert_signup(PDO $pdo, int $userId, string $weekStartDate): void {
    $stmt = $pdo->prepare("
        INSERT INTO challenge_signups (
            week_start_date,
            user_id,
            signup_status,
            created_at,
            updated_at
        ) VALUES (?, ?, 'active', NOW(), NOW())
        ON DUPLICATE KEY UPDATE signup_status = 'active', updated_at = NOW()
    ");
    $stmt->execute([$weekStartDate, $userId]);
}

function challenge_insert_user_notification(PDO $pdo, int $recipientUserId, string $type, string $title, string $body, string $ctaLabel = 'Open challenge', string $ctaUrl = 'http://127.0.0.1:8001/home.html?challenge=1'): void {
    if ($recipientUserId <= 0) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO message_center_notifications (
            recipient_user_id,
            actor_user_id,
            notification_type,
            post_id,
            comment_id,
            title,
            body_text,
            cta_label,
            cta_url,
            is_read,
            created_at,
            updated_at
        ) VALUES (?, NULL, ?, NULL, NULL, ?, ?, ?, ?, 0, NOW(), NOW())
    ");
    $stmt->execute([$recipientUserId, $type, $title, $body, $ctaLabel, $ctaUrl]);
}

function challenge_notify_signup(PDO $pdo, int $userId, array $cycle): void {
    challenge_insert_user_notification(
        $pdo,
        $userId,
        'system',
        'Challenge sign-up confirmed',
        sprintf('You have successfully signed up for the weekly challenge cycle %s.', (string)($cycle['label'] ?? '')),
    );
}

function challenge_notify_team_locked(PDO $pdo, int $teamId, string $weekStartDate): void {
    $stmt = $pdo->prepare("
        SELECT
            ct.team_name,
            u.user_id
        FROM challenge_teams ct
        JOIN challenge_team_members ctm
          ON ctm.team_id = ct.team_id
         AND ctm.membership_status = 'active'
        JOIN users u
          ON u.user_id = ctm.user_id
        WHERE ct.team_id = ?
          AND ct.week_start_date = ?
    ");
    $stmt->execute([$teamId, $weekStartDate]);

    foreach ($stmt->fetchAll() as $row) {
        challenge_insert_user_notification(
            $pdo,
            (int)($row['user_id'] ?? 0),
            'system',
            'Challenge team formed successfully',
            sprintf('Your team "%s" is now complete with 4 members and has been locked in for this week.', (string)($row['team_name'] ?? 'Your team')),
        );
    }
}

function challenge_notify_team_expired(PDO $pdo, int $teamId, string $weekStartDate): void {
    $stmt = $pdo->prepare("
        SELECT
            ct.team_name,
            u.user_id
        FROM challenge_teams ct
        JOIN challenge_team_members ctm
          ON ctm.team_id = ct.team_id
         AND ctm.membership_status = 'active'
        JOIN users u
          ON u.user_id = ctm.user_id
        WHERE ct.team_id = ?
          AND ct.week_start_date = ?
    ");
    $stmt->execute([$teamId, $weekStartDate]);

    foreach ($stmt->fetchAll() as $row) {
        challenge_insert_user_notification(
            $pdo,
            (int)($row['user_id'] ?? 0),
            'system',
            'Challenge team formation failed',
            sprintf('Your team "%s" did not reach 4 members within 1 hour, so the team has expired.', (string)($row['team_name'] ?? 'Your team')),
        );
    }
}

function challenge_find_team_for_user(PDO $pdo, int $userId, string $weekStartDate): ?array {
    $stmt = $pdo->prepare("
        SELECT
            ct.team_id,
            ct.team_name,
            ct.week_start_date,
            ct.week_end_date,
            ct.captain_user_id,
            ct.score,
            ct.daily_rank,
            ct.rank_updated_on,
            ct.status,
            ct.expires_at,
            ct.locked_at,
            ct.team_name_confirmed_at,
            ctm.member_role,
            (
                SELECT COUNT(*)
                FROM challenge_team_members x
                WHERE x.team_id = ct.team_id
                  AND x.membership_status = 'active'
            ) AS member_count
        FROM challenge_team_members ctm
        JOIN challenge_teams ct
          ON ct.team_id = ctm.team_id
        WHERE ctm.user_id = ?
          AND ctm.membership_status = 'active'
          AND ct.week_start_date = ?
          AND ct.status IN ('forming', 'locked')
        ORDER BY CASE WHEN ct.status = 'locked' THEN 0 ELSE 1 END, ctm.joined_at DESC, ctm.team_member_id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $weekStartDate]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function challenge_find_captain_team(PDO $pdo, int $captainUserId, string $weekStartDate): ?array {
    $stmt = $pdo->prepare("
        SELECT
            ct.team_id,
            ct.team_name,
            ct.week_start_date,
            ct.week_end_date,
            ct.captain_user_id,
            ct.score,
            ct.daily_rank,
            ct.rank_updated_on,
            ct.status,
            ct.expires_at,
            ct.locked_at,
            ct.team_name_confirmed_at,
            'captain' AS member_role,
            (
                SELECT COUNT(*)
                FROM challenge_team_members x
                WHERE x.team_id = ct.team_id
                  AND x.membership_status = 'active'
            ) AS member_count
        FROM challenge_teams ct
        WHERE ct.captain_user_id = ?
          AND ct.week_start_date = ?
          AND ct.status IN ('forming', 'locked')
        ORDER BY CASE WHEN ct.status = 'locked' THEN 0 ELSE 1 END, ct.created_at DESC, ct.team_id DESC
        LIMIT 1
    ");
    $stmt->execute([$captainUserId, $weekStartDate]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function challenge_user_has_locked_team(PDO $pdo, int $userId, string $weekStartDate): bool {
    $stmt = $pdo->prepare("
        SELECT ct.team_id
        FROM challenge_team_members ctm
        JOIN challenge_teams ct
          ON ct.team_id = ctm.team_id
        WHERE ctm.user_id = ?
          AND ctm.membership_status = 'active'
          AND ct.week_start_date = ?
          AND ct.status = 'locked'
        LIMIT 1
    ");
    $stmt->execute([$userId, $weekStartDate]);
    return (bool)$stmt->fetchColumn();
}

function challenge_team_members(PDO $pdo, int $teamId): array {
    $stmt = $pdo->prepare("
        SELECT
            ctm.user_id,
            ctm.member_role,
            u.username,
            u.email
        FROM challenge_team_members ctm
        JOIN users u ON u.user_id = ctm.user_id
        WHERE ctm.team_id = ?
          AND ctm.membership_status = 'active'
        ORDER BY CASE WHEN ctm.member_role = 'captain' THEN 0 ELSE 1 END, ctm.joined_at ASC, ctm.team_member_id ASC
    ");
    $stmt->execute([$teamId]);

    return array_map(static function (array $row): array {
        $username = (string)($row['username'] ?? 'Unknown');
        return [
            'id' => (int)$row['user_id'],
            'username' => $username,
            'email' => (string)($row['email'] ?? ''),
            'role' => (string)($row['member_role'] ?? 'member'),
            'avatar' => forum_avatar_letters($username),
        ];
    }, $stmt->fetchAll());
}

function challenge_team_payload(PDO $pdo, array $teamRow, int $currentUserId): array {
    $members = challenge_team_members($pdo, (int)$teamRow['team_id']);
    $captainUserId = (int)($teamRow['captain_user_id'] ?? 0);
    $status = (string)($teamRow['status'] ?? 'forming');
    $expiresAt = (string)($teamRow['expires_at'] ?? '');
    $expiresAtDt = challenge_datetime_from_db($expiresAt);
    $secondsRemaining = ($status === 'forming' && $expiresAtDt)
        ? max(0, $expiresAtDt->getTimestamp() - challenge_now()->getTimestamp())
        : 0;

    return [
        'id' => (int)$teamRow['team_id'],
        'name' => (string)($teamRow['team_name'] ?? ''),
        'score' => (int)($teamRow['score'] ?? 0),
        'dailyRank' => isset($teamRow['daily_rank']) ? (int)$teamRow['daily_rank'] : null,
        'rankUpdatedOn' => (string)($teamRow['rank_updated_on'] ?? ''),
        'memberCount' => count($members),
        'maxMembers' => CHALLENGE_MAX_MEMBERS,
        'captainUserId' => $captainUserId,
        'isCaptain' => $captainUserId === $currentUserId,
        'status' => $status,
        'isLocked' => $status === 'locked',
        'expiresAt' => $expiresAt,
        'expiresAtTs' => $expiresAtDt?->getTimestamp(),
        'lockedAt' => (string)($teamRow['locked_at'] ?? ''),
        'secondsRemaining' => $secondsRemaining,
        'remainingSlots' => max(0, CHALLENGE_MAX_MEMBERS - count($members)),
        'isNameConfirmed' => !empty($teamRow['team_name_confirmed_at']),
        'members' => $members,
    ];
}

function challenge_close_team_side_effects(PDO $pdo, int $teamId, string $weekStartDate, string $inviteStatus = 'expired'): void {
    $inviteStmt = $pdo->prepare("
        UPDATE challenge_team_invites
        SET status = ?, responded_at = NOW(), updated_at = NOW()
        WHERE team_id = ?
          AND week_start_date = ?
          AND status = 'pending'
    ");
    $inviteStmt->execute([$inviteStatus, $teamId, $weekStartDate]);

    $listingStmt = $pdo->prepare("
        UPDATE challenge_team_public_listings
        SET status = 'closed', updated_at = NOW()
        WHERE team_id = ?
          AND week_start_date = ?
          AND status = 'active'
    ");
    $listingStmt->execute([$teamId, $weekStartDate]);
}

function challenge_archive_team(PDO $pdo, int $teamId, string $newStatus, string $weekStartDate): void {
    $memberStmt = $pdo->prepare("
        UPDATE challenge_team_members
        SET membership_status = 'removed', left_at = NOW(), updated_at = NOW()
        WHERE team_id = ?
          AND membership_status = 'active'
    ");
    $memberStmt->execute([$teamId]);

    $teamStmt = $pdo->prepare("
        UPDATE challenge_teams
        SET status = ?, archived_at = NOW(), updated_at = NOW(), daily_rank = NULL
        WHERE team_id = ?
          AND status IN ('forming', 'locked')
    ");
    $teamStmt->execute([$newStatus, $teamId]);

    challenge_close_team_side_effects($pdo, $teamId, $weekStartDate, $newStatus === 'expired' ? 'expired' : 'cancelled');
}

function challenge_release_user_from_forming_team(PDO $pdo, int $userId, string $weekStartDate, ?int $exceptTeamId = null): void {
    $stmt = $pdo->prepare("
        SELECT
            ctm.team_member_id,
            ctm.member_role,
            ct.team_id
        FROM challenge_team_members ctm
        JOIN challenge_teams ct
          ON ct.team_id = ctm.team_id
        WHERE ctm.user_id = ?
          AND ctm.membership_status = 'active'
          AND ct.week_start_date = ?
          AND ct.status = 'forming'
        FOR UPDATE
    ");
    $stmt->execute([$userId, $weekStartDate]);

    foreach ($stmt->fetchAll() as $row) {
        $teamId = (int)($row['team_id'] ?? 0);
        if ($teamId <= 0 || ($exceptTeamId !== null && $teamId === $exceptTeamId)) {
            continue;
        }

        if ((string)($row['member_role'] ?? 'member') === 'captain') {
            challenge_archive_team($pdo, $teamId, 'expired', $weekStartDate);
            continue;
        }

        $removeStmt = $pdo->prepare("
            UPDATE challenge_team_members
            SET membership_status = 'removed', left_at = NOW(), updated_at = NOW()
            WHERE team_member_id = ?
        ");
        $removeStmt->execute([(int)$row['team_member_id']]);
    }
}

function challenge_lock_team_if_full(PDO $pdo, int $teamId, string $weekStartDate): bool {
    if (challenge_team_member_count($pdo, $teamId) < CHALLENGE_MAX_MEMBERS) {
        return false;
    }

    $stmt = $pdo->prepare("
        UPDATE challenge_teams
        SET status = 'locked', locked_at = NOW(), updated_at = NOW()
        WHERE team_id = ?
          AND status = 'forming'
    ");
    $stmt->execute([$teamId]);

    if ($stmt->rowCount() === 0) {
        return false;
    }

    challenge_close_team_side_effects($pdo, $teamId, $weekStartDate, 'expired');
    challenge_notify_team_locked($pdo, $teamId, $weekStartDate);
    return true;
}

function challenge_cleanup_expired_forming_teams(PDO $pdo, string $weekStartDate): void {
    $stmt = $pdo->prepare("
        SELECT team_id
        FROM challenge_teams
        WHERE week_start_date = ?
          AND status = 'forming'
          AND expires_at <= NOW()
    ");
    $stmt->execute([$weekStartDate]);
    $teamIds = array_map(static fn(array $row): int => (int)$row['team_id'], $stmt->fetchAll());

    foreach ($teamIds as $teamId) {
        challenge_notify_team_expired($pdo, $teamId, $weekStartDate);
        challenge_archive_team($pdo, $teamId, 'expired', $weekStartDate);
    }

    if ($teamIds) {
        forum_realtime_publish('challenge.updated', [
            'reason' => 'forming_expired',
            'scope' => 'global',
            'weekStartDate' => $weekStartDate,
        ]);
        forum_realtime_publish('message-center.updated', [
            'scope' => 'challenge',
        ]);
    }
}

function challenge_refresh_daily_ranks(PDO $pdo, string $weekStartDate): void {
    $resetStmt = $pdo->prepare("
        UPDATE challenge_teams
        SET daily_rank = NULL, rank_updated_on = CURDATE(), updated_at = NOW()
        WHERE week_start_date = ?
          AND status = 'locked'
    ");
    $resetStmt->execute([$weekStartDate]);

    $stmt = $pdo->prepare("
        SELECT
            ct.team_id,
            ct.score
        FROM challenge_teams ct
        WHERE ct.week_start_date = ?
          AND ct.status = 'locked'
          AND ct.score > 0
        ORDER BY ct.score DESC, ct.created_at ASC, ct.team_id ASC
    ");
    $stmt->execute([$weekStartDate]);
    $rows = $stmt->fetchAll();

    $updateStmt = $pdo->prepare("
        UPDATE challenge_teams
        SET daily_rank = ?, rank_updated_on = CURDATE(), updated_at = NOW()
        WHERE team_id = ?
    ");

    $rank = 1;
    foreach ($rows as $row) {
        $updateStmt->execute([$rank++, (int)$row['team_id']]);
    }
}

function challenge_maintain_weekly_cycle(PDO $pdo): array {
    $cycle = challenge_cycle_now();
    $currentWeekStart = $cycle['weekStartDate'];
    $lastResetWeek = challenge_meta_get($pdo, 'challenge_last_reset_week', '');

    if ($lastResetWeek !== $currentWeekStart) {
        $pdo->beginTransaction();
        try {
            $teamStmt = $pdo->prepare("
                SELECT DISTINCT
                    ct.team_name,
                    u.user_id,
                    u.role
                FROM challenge_teams ct
                JOIN challenge_team_members ctm
                  ON ctm.team_id = ct.team_id
                 AND ctm.membership_status = 'active'
                JOIN users u
                  ON u.user_id = ctm.user_id
                WHERE ct.week_start_date < ?
                  AND ct.status IN ('forming', 'locked')
            ");
            $teamStmt->execute([$currentWeekStart]);

            $notifyStmt = $pdo->prepare("
                INSERT INTO message_center_notifications (
                    recipient_user_id,
                    actor_user_id,
                    notification_type,
                    post_id,
                    comment_id,
                    title,
                    body_text,
                    cta_label,
                    cta_url,
                    is_read,
                    created_at,
                    updated_at
                ) VALUES (?, NULL, 'challenge_reset', NULL, NULL, ?, ?, 'Open challenge', ?, 0, NOW(), NOW())
            ");

            foreach ($teamStmt->fetchAll() as $row) {
                if ((string)($row['role'] ?? 'user') === 'admin') {
                    continue;
                }
                $notifyStmt->execute([
                    (int)$row['user_id'],
                    'Weekly challenge teams have reset',
                    sprintf(
                        'Your previous team "%s" has been cleared. Sign up again and form a new 4-person team for %s.',
                        (string)$row['team_name'],
                        $cycle['label']
                    ),
                    'http://127.0.0.1:8001/home.html?challenge=1',
                ]);
            }

            $expireInvitesStmt = $pdo->prepare("
                UPDATE challenge_team_invites
                SET status = 'expired', responded_at = NOW(), updated_at = NOW()
                WHERE week_start_date < ?
                  AND status = 'pending'
            ");
            $expireInvitesStmt->execute([$currentWeekStart]);

            $archiveMembersStmt = $pdo->prepare("
                UPDATE challenge_team_members ctm
                JOIN challenge_teams ct
                  ON ct.team_id = ctm.team_id
                SET ctm.membership_status = 'removed', ctm.left_at = NOW(), ctm.updated_at = NOW()
                WHERE ct.week_start_date < ?
                  AND ct.status IN ('forming', 'locked')
                  AND ctm.membership_status = 'active'
            ");
            $archiveMembersStmt->execute([$currentWeekStart]);

            $archiveTeamsStmt = $pdo->prepare("
                UPDATE challenge_teams
                SET status = 'archived', archived_at = NOW(), updated_at = NOW(), daily_rank = NULL
                WHERE week_start_date < ?
                  AND status IN ('forming', 'locked', 'expired')
            ");
            $archiveTeamsStmt->execute([$currentWeekStart]);

            $closeListingsStmt = $pdo->prepare("
                UPDATE challenge_team_public_listings
                SET status = 'closed', updated_at = NOW()
                WHERE week_start_date < ?
                  AND status = 'active'
            ");
            $closeListingsStmt->execute([$currentWeekStart]);

            challenge_meta_set($pdo, 'challenge_last_reset_week', $currentWeekStart);
            $pdo->commit();

            forum_realtime_publish('challenge.updated', [
                'reason' => 'weekly_reset',
                'scope' => 'global',
                'weekStartDate' => $currentWeekStart,
            ]);
            forum_realtime_publish('message-center.updated', [
                'scope' => 'challenge',
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    challenge_cleanup_expired_forming_teams($pdo, $currentWeekStart);
    challenge_refresh_daily_ranks($pdo, $currentWeekStart);
    return $cycle;
}
