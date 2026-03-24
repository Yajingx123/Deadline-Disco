<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$peerConfig = require __DIR__ . '/../../Auth/backend/config/config.php';

header('Access-Control-Allow-Origin: http://127.0.0.1:8001');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function peer_db(): PDO {
    static $pdo = null;
    global $peerConfig;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $peerConfig['db_host'],
        $peerConfig['db_port'],
        $peerConfig['db_name']
    );

    $pdo = new PDO($dsn, $peerConfig['db_user'], $peerConfig['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function peer_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function peer_input(): array {
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function peer_current_user(): ?array {
    $user = $_SESSION['auth_user'] ?? null;
    return is_array($user) ? $user : null;
}

function peer_require_user(): array {
    $user = peer_current_user();
    if (!$user || empty($user['user_id'])) {
        peer_json([
            'ok' => false,
            'message' => 'Login required.',
        ], 401);
    }
    return $user;
}

function peer_now(): string {
    return date('Y-m-d H:i:s');
}

function peer_today(): string {
    return date('Y-m-d');
}

function peer_initials(string $username): string {
    $trimmed = trim($username);
    return strtoupper(substr($trimmed !== '' ? $trimmed : 'U', 0, 2));
}

function peer_format_display_name(string $username): string {
    $trimmed = trim($username);
    if ($trimmed === '') {
        return 'User';
    }

    $parts = preg_split('/[\s._-]+/', $trimmed) ?: [];
    $parts = array_filter($parts, static fn(string $part): bool => $part !== '');
    if (!$parts) {
        return ucfirst($trimmed);
    }

    return implode(' ', array_map(static function(string $part): string {
        return strtoupper(substr($part, 0, 1)) . substr($part, 1);
    }, $parts));
}

function peer_find_user_by_username(PDO $pdo, string $username): ?array {
    $stmt = $pdo->prepare("
        SELECT user_id, username
        FROM users
        WHERE LOWER(username) = LOWER(?)
        LIMIT 1
    ");
    $stmt->execute([trim($username)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function peer_find_active_team_for_user(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("
        SELECT
            prt.team_id,
            prt.space_id,
            prt.started_on,
            prt.current_streak_days,
            prt.longest_streak_days,
            prt.last_mutual_checkin_on
        FROM peer_resonance_teams prt
        JOIN peer_spaces ps ON ps.space_id = prt.space_id
        JOIN peer_space_members psm ON psm.space_id = prt.space_id
        WHERE psm.user_id = ?
          AND psm.membership_status = 'accepted'
          AND ps.space_type = 'resonance'
          AND ps.status = 'active'
          AND prt.status = 'active'
        ORDER BY prt.team_id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function peer_find_pending_invite_between(PDO $pdo, int $userA, int $userB): ?array {
    $stmt = $pdo->prepare("
        SELECT psi.invite_id, psi.inviter_user_id, psi.invitee_user_id, psi.status
        FROM peer_space_invites psi
        JOIN peer_spaces ps ON ps.space_id = psi.space_id
        WHERE ps.space_type = 'resonance'
          AND psi.status = 'pending'
          AND (
            (psi.inviter_user_id = ? AND psi.invitee_user_id = ?)
            OR
            (psi.inviter_user_id = ? AND psi.invitee_user_id = ?)
          )
        ORDER BY psi.invite_id DESC
        LIMIT 1
    ");
    $stmt->execute([$userA, $userB, $userB, $userA]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function peer_count_active_members(PDO $pdo, int $spaceId): int {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_members
        FROM peer_space_members
        WHERE space_id = ?
          AND membership_status = 'accepted'
    ");
    $stmt->execute([$spaceId]);
    $row = $stmt->fetch();
    return (int)($row['total_members'] ?? 0);
}

function peer_calculate_team_streak(PDO $pdo, int $teamId, int $memberCount): array {
    $stmt = $pdo->prepare("
        SELECT checkin_date, COUNT(DISTINCT user_id) AS committed_members
        FROM peer_resonance_daily_logs
        WHERE team_id = ?
        GROUP BY checkin_date
        HAVING committed_members >= ?
        ORDER BY checkin_date DESC
    ");
    $stmt->execute([$teamId, $memberCount]);
    $dates = array_map(static fn(array $row): string => (string)$row['checkin_date'], $stmt->fetchAll());

    $currentStreak = 0;
    $lastMutualDate = null;
    if ($dates) {
        $expectedTs = strtotime(peer_today());
        foreach ($dates as $index => $dateValue) {
            $dateTs = strtotime($dateValue);
            if ($dateTs === false) {
                continue;
            }
            if ($index === 0) {
                if ($dateTs !== $expectedTs) {
                    break;
                }
                $lastMutualDate = $dateValue;
            }
            if ($dateTs === $expectedTs) {
                $currentStreak += 1;
                $expectedTs = strtotime('-1 day', $expectedTs);
                continue;
            }
            break;
        }
    }

    $longestStmt = $pdo->prepare("
        SELECT checkin_date, COUNT(DISTINCT user_id) AS committed_members
        FROM peer_resonance_daily_logs
        WHERE team_id = ?
        GROUP BY checkin_date
        HAVING committed_members >= ?
        ORDER BY checkin_date ASC
    ");
    $longestStmt->execute([$teamId, $memberCount]);
    $allMutualDates = array_map(static fn(array $row): string => (string)$row['checkin_date'], $longestStmt->fetchAll());

    $longestStreak = 0;
    $runningStreak = 0;
    $previousTs = null;
    foreach ($allMutualDates as $dateValue) {
        $dateTs = strtotime($dateValue);
        if ($dateTs === false) {
            continue;
        }
        if ($previousTs !== null && $dateTs === strtotime('+1 day', $previousTs)) {
            $runningStreak += 1;
        } else {
            $runningStreak = 1;
        }
        $longestStreak = max($longestStreak, $runningStreak);
        $previousTs = $dateTs;
    }

    return [
        'current_streak_days' => $currentStreak,
        'longest_streak_days' => $longestStreak,
        'last_mutual_checkin_on' => $lastMutualDate,
    ];
}

function peer_update_team_streak(PDO $pdo, int $teamId): array {
    $teamStmt = $pdo->prepare("SELECT space_id FROM peer_resonance_teams WHERE team_id = ? LIMIT 1");
    $teamStmt->execute([$teamId]);
    $teamRow = $teamStmt->fetch();
    if (!$teamRow) {
        return [
            'current_streak_days' => 0,
            'longest_streak_days' => 0,
            'last_mutual_checkin_on' => null,
        ];
    }

    $memberCount = max(1, peer_count_active_members($pdo, (int)$teamRow['space_id']));
    $metrics = peer_calculate_team_streak($pdo, $teamId, $memberCount);

    $update = $pdo->prepare("
        UPDATE peer_resonance_teams
        SET current_streak_days = ?, longest_streak_days = ?, last_mutual_checkin_on = ?, updated_at = NOW()
        WHERE team_id = ?
    ");
    $update->execute([
        (int)$metrics['current_streak_days'],
        (int)$metrics['longest_streak_days'],
        $metrics['last_mutual_checkin_on'],
        $teamId,
    ]);

    return $metrics;
}

function peer_build_state(PDO $pdo, int $userId): array {
    $currentUser = peer_find_user_by_username($pdo, (string)(peer_current_user()['username'] ?? '')) ?: [
        'user_id' => $userId,
        'username' => (string)(peer_current_user()['username'] ?? 'user'),
    ];

    $incomingStmt = $pdo->prepare("
        SELECT
            psi.invite_id,
            psi.space_id,
            psi.created_at,
            psi.expires_at,
            inviter.user_id AS inviter_user_id,
            inviter.username AS inviter_username
        FROM peer_space_invites psi
        JOIN peer_spaces ps ON ps.space_id = psi.space_id
        JOIN users inviter ON inviter.user_id = psi.inviter_user_id
        WHERE psi.invitee_user_id = ?
          AND psi.status = 'pending'
          AND ps.space_type = 'resonance'
        ORDER BY psi.invite_id DESC
    ");
    $incomingStmt->execute([$userId]);
    $incomingInvites = array_map(static function(array $row): array {
        return [
            'inviteId' => (int)$row['invite_id'],
            'spaceId' => (int)$row['space_id'],
            'createdAt' => (string)$row['created_at'],
            'expiresAt' => (string)($row['expires_at'] ?? ''),
            'fromUserId' => (int)$row['inviter_user_id'],
            'fromUsername' => (string)$row['inviter_username'],
            'fromDisplayName' => peer_format_display_name((string)$row['inviter_username']),
            'fromInitials' => peer_initials((string)$row['inviter_username']),
        ];
    }, $incomingStmt->fetchAll());

    $outgoingStmt = $pdo->prepare("
        SELECT
            psi.invite_id,
            psi.space_id,
            psi.created_at,
            psi.expires_at,
            invitee.user_id AS invitee_user_id,
            invitee.username AS invitee_username
        FROM peer_space_invites psi
        JOIN peer_spaces ps ON ps.space_id = psi.space_id
        JOIN users invitee ON invitee.user_id = psi.invitee_user_id
        WHERE psi.inviter_user_id = ?
          AND psi.status = 'pending'
          AND ps.space_type = 'resonance'
        ORDER BY psi.invite_id DESC
    ");
    $outgoingStmt->execute([$userId]);
    $outgoingInvites = array_map(static function(array $row): array {
        return [
            'inviteId' => (int)$row['invite_id'],
            'spaceId' => (int)$row['space_id'],
            'createdAt' => (string)$row['created_at'],
            'expiresAt' => (string)($row['expires_at'] ?? ''),
            'toUserId' => (int)$row['invitee_user_id'],
            'toUsername' => (string)$row['invitee_username'],
            'toDisplayName' => peer_format_display_name((string)$row['invitee_username']),
            'toInitials' => peer_initials((string)$row['invitee_username']),
        ];
    }, $outgoingStmt->fetchAll());

    $activeTeamRow = peer_find_active_team_for_user($pdo, $userId);
    $activeTeam = null;
    $myCheckinDates = [];

    if ($activeTeamRow) {
        $membersStmt = $pdo->prepare("
            SELECT u.user_id, u.username, psm.membership_status
            FROM peer_space_members psm
            JOIN users u ON u.user_id = psm.user_id
            WHERE psm.space_id = ?
              AND psm.membership_status = 'accepted'
            ORDER BY psm.membership_id ASC
        ");
        $membersStmt->execute([(int)$activeTeamRow['space_id']]);
        $members = $membersStmt->fetchAll();

        $partner = null;
        foreach ($members as $member) {
            if ((int)$member['user_id'] !== $userId) {
                $partner = [
                    'userId' => (int)$member['user_id'],
                    'username' => (string)$member['username'],
                    'displayName' => peer_format_display_name((string)$member['username']),
                    'initials' => peer_initials((string)$member['username']),
                ];
                break;
            }
        }

        $today = peer_today();
        $myCommitStmt = $pdo->prepare("
            SELECT COUNT(*) AS committed
            FROM peer_resonance_daily_logs
            WHERE team_id = ?
              AND user_id = ?
              AND checkin_date = ?
        ");
        $myCommitStmt->execute([(int)$activeTeamRow['team_id'], $userId, $today]);
        $currentUserCommittedToday = ((int)($myCommitStmt->fetch()['committed'] ?? 0)) > 0;

        $partnerCommittedToday = false;
        if ($partner) {
            $partnerCommitStmt = $pdo->prepare("
                SELECT COUNT(*) AS committed
                FROM peer_resonance_daily_logs
                WHERE team_id = ?
                  AND user_id = ?
                  AND checkin_date = ?
            ");
            $partnerCommitStmt->execute([(int)$activeTeamRow['team_id'], (int)$partner['userId'], $today]);
            $partnerCommittedToday = ((int)($partnerCommitStmt->fetch()['committed'] ?? 0)) > 0;
        }

        $datesStmt = $pdo->prepare("
            SELECT checkin_date
            FROM peer_resonance_daily_logs
            WHERE team_id = ?
              AND user_id = ?
            ORDER BY checkin_date DESC
            LIMIT 90
        ");
        $datesStmt->execute([(int)$activeTeamRow['team_id'], $userId]);
        $myCheckinDates = array_map(static fn(array $row): string => (string)$row['checkin_date'], $datesStmt->fetchAll());

        $activeTeam = [
            'teamId' => (int)$activeTeamRow['team_id'],
            'spaceId' => (int)$activeTeamRow['space_id'],
            'startedOn' => (string)$activeTeamRow['started_on'],
            'currentStreakDays' => (int)$activeTeamRow['current_streak_days'],
            'longestStreakDays' => (int)$activeTeamRow['longest_streak_days'],
            'lastMutualCheckinOn' => (string)($activeTeamRow['last_mutual_checkin_on'] ?? ''),
            'partner' => $partner,
            'currentUserCommittedToday' => $currentUserCommittedToday,
            'partnerCommittedToday' => $partnerCommittedToday,
        ];
    }

    return [
        'currentUser' => [
            'userId' => (int)$currentUser['user_id'],
            'username' => (string)$currentUser['username'],
            'displayName' => peer_format_display_name((string)$currentUser['username']),
            'initials' => peer_initials((string)$currentUser['username']),
        ],
        'activeTeam' => $activeTeam,
        'incomingInvites' => $incomingInvites,
        'outgoingInvites' => $outgoingInvites,
        'myCheckinDates' => $myCheckinDates,
    ];
}
