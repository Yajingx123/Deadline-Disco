<?php
declare(strict_types=1);

function challenge_cycle_now(): array {
    $now = new DateTimeImmutable('now');
    $weekday = (int)$now->format('N');
    $weekStart = $now->setTime(0, 0)->modify('-' . ($weekday - 1) . ' days');
    $weekEnd = $weekStart->modify('+6 days');

    return [
        'weekStartDate' => $weekStart->format('Y-m-d'),
        'weekEndDate' => $weekEnd->format('Y-m-d'),
        'label' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
        'resetRule' => 'Teams reset every Monday at 00:00.',
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

function challenge_refresh_daily_ranks(PDO $pdo, string $weekStartDate): void {
    $resetStmt = $pdo->prepare("
        UPDATE challenge_teams
        SET daily_rank = NULL, rank_updated_on = CURDATE(), updated_at = NOW()
        WHERE week_start_date = ? AND status = 'active'
    ");
    $resetStmt->execute([$weekStartDate]);

    $stmt = $pdo->prepare("
        SELECT
            ct.team_id,
            ct.score,
            COUNT(CASE WHEN ctm.membership_status = 'active' THEN 1 END) AS active_member_count
        FROM challenge_teams ct
        LEFT JOIN challenge_team_members ctm
          ON ctm.team_id = ct.team_id
        WHERE ct.week_start_date = ?
          AND ct.status = 'active'
        GROUP BY ct.team_id, ct.score
        HAVING ct.score > 0
        ORDER BY ct.score DESC, active_member_count DESC, ct.created_at ASC, ct.team_id ASC
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

    if ($lastResetWeek === $currentWeekStart) {
        challenge_refresh_daily_ranks($pdo, $currentWeekStart);
        return $cycle;
    }

    $pdo->beginTransaction();

    try {
        $teamStmt = $pdo->prepare("
            SELECT
                ct.team_id,
                ct.team_name,
                u.user_id,
                u.role
            FROM challenge_teams ct
            JOIN challenge_team_members ctm
              ON ctm.team_id = ct.team_id
             AND ctm.membership_status = 'active'
            JOIN users u
              ON u.user_id = ctm.user_id
            WHERE ct.status = 'active'
              AND ct.week_start_date < ?
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
                    'Your team "%s" was cleared for the new weekly cycle. Re-form your squad for %s.',
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
            SET
                ctm.membership_status = 'removed',
                ctm.left_at = NOW(),
                ctm.updated_at = NOW()
            WHERE ct.week_start_date < ?
              AND ct.status = 'active'
              AND ctm.membership_status = 'active'
        ");
        $archiveMembersStmt->execute([$currentWeekStart]);

        $archiveTeamsStmt = $pdo->prepare("
            UPDATE challenge_teams
            SET status = 'archived', archived_at = NOW(), updated_at = NOW(), daily_rank = NULL
            WHERE week_start_date < ?
              AND status = 'active'
        ");
        $archiveTeamsStmt->execute([$currentWeekStart]);

        challenge_meta_set($pdo, 'challenge_last_reset_week', $currentWeekStart);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    challenge_refresh_daily_ranks($pdo, $currentWeekStart);
    forum_realtime_publish('challenge.updated', [
        'reason' => 'weekly_reset',
        'scope' => 'global',
        'weekStartDate' => $currentWeekStart,
    ]);
    return $cycle;
}

function challenge_find_team_for_user(PDO $pdo, int $userId, string $weekStartDate, bool $requireFullTeam = false): ?array {
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
          AND ct.status = 'active'
        ORDER BY CASE WHEN ctm.member_role = 'captain' THEN 1 ELSE 0 END, ctm.joined_at DESC, ctm.team_member_id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $weekStartDate]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }
    if ($requireFullTeam && (int)($row['member_count'] ?? 0) < 4) {
        return null;
    }
    return $row;
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

function challenge_close_team_side_effects(PDO $pdo, int $teamId, string $weekStartDate, string $inviteStatus = 'expired', string $requestStatus = 'expired'): void {
    $inviteStmt = $pdo->prepare("
        UPDATE challenge_team_invites
        SET status = ?, responded_at = NOW(), updated_at = NOW()
        WHERE team_id = ?
          AND week_start_date = ?
          AND status = 'pending'
    ");
    $inviteStmt->execute([$inviteStatus, $teamId, $weekStartDate]);

    $requestStmt = $pdo->prepare("
        UPDATE challenge_team_join_requests
        SET status = ?, responded_at = NOW(), updated_at = NOW()
        WHERE team_id = ?
          AND week_start_date = ?
          AND status = 'pending'
    ");
    $requestStmt->execute([$requestStatus, $teamId, $weekStartDate]);

    $listingStmt = $pdo->prepare("
        UPDATE challenge_team_public_listings
        SET status = 'closed', updated_at = NOW()
        WHERE team_id = ?
          AND week_start_date = ?
          AND status = 'active'
    ");
    $listingStmt->execute([$teamId, $weekStartDate]);
}

function challenge_release_user_from_provisional_teams(PDO $pdo, int $userId, string $weekStartDate, ?int $exceptTeamId = null): void {
    $stmt = $pdo->prepare("
        SELECT
            ct.team_id,
            ct.captain_user_id,
            ctm.team_member_id,
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
          AND ct.status = 'active'
        FOR UPDATE
    ");
    $stmt->execute([$userId, $weekStartDate]);

    foreach ($stmt->fetchAll() as $row) {
        $teamId = (int)($row['team_id'] ?? 0);
        if ($teamId <= 0 || ($exceptTeamId !== null && $teamId === $exceptTeamId)) {
            continue;
        }

        $memberCount = (int)($row['member_count'] ?? 0);
        if ($memberCount >= CHALLENGE_MAX_MEMBERS) {
            continue;
        }

        $isCaptain = (string)($row['member_role'] ?? 'member') === 'captain';
        if ($isCaptain) {
            $removeMembersStmt = $pdo->prepare("
                UPDATE challenge_team_members
                SET membership_status = 'removed', left_at = NOW(), updated_at = NOW()
                WHERE team_id = ?
                  AND membership_status = 'active'
            ");
            $removeMembersStmt->execute([$teamId]);

            $archiveTeamStmt = $pdo->prepare("
                UPDATE challenge_teams
                SET status = 'archived', archived_at = NOW(), updated_at = NOW(), daily_rank = NULL
                WHERE team_id = ?
                  AND status = 'active'
            ");
            $archiveTeamStmt->execute([$teamId]);

            challenge_close_team_side_effects($pdo, $teamId, $weekStartDate, 'cancelled', 'cancelled');
            continue;
        }

        $removeMemberStmt = $pdo->prepare("
            UPDATE challenge_team_members
            SET membership_status = 'removed', left_at = NOW(), updated_at = NOW()
            WHERE team_member_id = ?
        ");
        $removeMemberStmt->execute([(int)$row['team_member_id']]);
    }
}

function challenge_reconcile_user_memberships(PDO $pdo, int $userId, string $weekStartDate): void {
    $stmt = $pdo->prepare("
        SELECT
            ct.team_id,
            ct.captain_user_id,
            ctm.member_role,
            ctm.joined_at,
            ctm.team_member_id,
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
          AND ct.status = 'active'
        ORDER BY CASE WHEN ctm.member_role = 'captain' THEN 1 ELSE 0 END, ctm.joined_at DESC, ctm.team_member_id DESC
        FOR UPDATE
    ");
    $stmt->execute([$userId, $weekStartDate]);
    $rows = $stmt->fetchAll();
    if (count($rows) <= 1) {
        return;
    }

    $keepTeamId = (int)($rows[0]['team_id'] ?? 0);
    if ($keepTeamId <= 0) {
        return;
    }

    challenge_release_user_from_provisional_teams($pdo, $userId, $weekStartDate, $keepTeamId);
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
          AND ct.status = 'active'
        ORDER BY ct.created_at DESC, ct.team_id DESC
        LIMIT 1
    ");
    $stmt->execute([$captainUserId, $weekStartDate]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function challenge_find_active_team_for_user(PDO $pdo, int $userId, string $weekStartDate): ?array {
    return challenge_find_team_for_user($pdo, $userId, $weekStartDate, true);
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

    return [
        'id' => (int)$teamRow['team_id'],
        'name' => (string)($teamRow['team_name'] ?? ''),
        'score' => (int)($teamRow['score'] ?? 0),
        'dailyRank' => isset($teamRow['daily_rank']) ? (int)$teamRow['daily_rank'] : null,
        'rankUpdatedOn' => (string)($teamRow['rank_updated_on'] ?? ''),
        'memberCount' => count($members),
        'maxMembers' => 4,
        'captainUserId' => $captainUserId,
        'isCaptain' => $captainUserId === $currentUserId,
        'members' => $members,
    ];
}

function challenge_user_has_active_team(PDO $pdo, int $userId, string $weekStartDate): bool {
    return challenge_find_team_for_user($pdo, $userId, $weekStartDate, true) !== null;
}

function challenge_team_has_capacity(PDO $pdo, int $teamId, int $maxMembers = 4): bool {
    return challenge_team_member_count($pdo, $teamId) < $maxMembers;
}
