<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function video_zego_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $loaded = require __DIR__ . '/zego-config.php';
    $config = is_array($loaded) ? $loaded : [];
    return $config;
}

function video_json_encode(?array $value): ?string
{
    if ($value === null) {
        return null;
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function video_json_decode_array(?string $value): ?array
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : null;
}

function video_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function video_room_prefix(): string
{
    $config = video_zego_config();
    $prefix = preg_replace('/[^A-Za-z0-9_]/', '_', (string) ($config['room_prefix'] ?? 'acadbeat_match_'));
    $prefix = is_string($prefix) && $prefix !== '' ? $prefix : 'acadbeat_match_';
    return rtrim($prefix, '_') . '_';
}

function video_room_visibility_values(): array
{
    return ['public', 'private'];
}

function video_room_status_values(): array
{
    return ['open', 'ended', 'cancelled', 'expired'];
}

function video_room_membership_status_values(): array
{
    return ['active', 'left', 'removed'];
}

function video_room_presence_status_values(): array
{
    return ['offline', 'joining', 'in_room'];
}

function video_room_duration_seconds(): int
{
    return 3600;
}

function video_normalize_topic_label(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s+/u', ' ', $value);
    if (function_exists('mb_substr')) {
        return mb_substr((string) $value, 0, 160);
    }

    return substr((string) $value, 0, 160);
}

function video_normalize_topic_key(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $value = preg_replace('/[^A-Za-z0-9_-]/', '_', $value);
    $value = preg_replace('/_+/', '_', (string) $value);
    $value = trim((string) $value, '_');

    if ($value === '') {
        return null;
    }

    return substr((string) $value, 0, 50);
}

function video_normalize_room_visibility(?string $value): string
{
    $value = strtolower(trim((string) $value));
    return in_array($value, video_room_visibility_values(), true) ? $value : '';
}

function video_generate_room_public_id(): string
{
    return 'vr_' . strtolower(bin2hex(random_bytes(10)));
}

function video_generate_zego_room_id_for_public_id(string $roomPublicId): string
{
    $base = video_room_prefix() . preg_replace('/[^A-Za-z0-9_]/', '_', $roomPublicId);
    return substr((string) $base, 0, 128);
}

function video_room_page_url(string $roomPublicId): string
{
    return './zego-call.php?room=' . rawurlencode($roomPublicId);
}

function video_cleanup_room_lifecycle(PDO $pdo): void
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $expiredRoomsStmt = $pdo->query("
            SELECT r.room_id, r.host_user_id, r.peak_member_count
            FROM peer_video_rooms r
            WHERE r.status = 'open'
              AND r.expires_at <= NOW()
            FOR UPDATE
        ");
        $expiredRooms = $expiredRoomsStmt ? ($expiredRoomsStmt->fetchAll() ?: []) : [];

        $updateRoomStmt = $pdo->prepare("
            UPDATE peer_video_rooms
            SET status = ?,
                ended_at = COALESCE(ended_at, NOW()),
                ended_reason = ?,
                updated_at = NOW()
            WHERE room_id = ?
        ");
        $expireInviteStmt = $pdo->prepare("
            UPDATE peer_video_room_invites
            SET status = 'expired',
                updated_at = NOW()
            WHERE room_id = ?
              AND status = 'active'
        ");
        $offlineMembersStmt = $pdo->prepare("
            UPDATE peer_video_room_members
            SET presence_status = 'offline',
                last_seen_at = NOW(),
                last_left_room_at = COALESCE(last_left_room_at, NOW()),
                updated_at = NOW()
            WHERE room_id = ?
              AND membership_status = 'active'
              AND presence_status <> 'offline'
        ");

        foreach ($expiredRooms as $roomRow) {
            $roomId = (int) $roomRow['room_id'];
            $peakMemberCount = (int) ($roomRow['peak_member_count'] ?? 1);
            $nextStatus = $peakMemberCount > 1 ? 'expired' : 'cancelled';
            $endedReason = $peakMemberCount > 1 ? 'expired' : 'single_member_timeout';
            $eventType = $peakMemberCount > 1 ? 'room_expired' : 'room_cancelled';

            $updateRoomStmt->execute([$nextStatus, $endedReason, $roomId]);
            $expireInviteStmt->execute([$roomId]);
            $offlineMembersStmt->execute([$roomId]);
            video_log_room_event($pdo, $roomId, null, null, $eventType, [
                'endedReason' => $endedReason,
                'peakMemberCount' => $peakMemberCount,
            ]);
        }

        $expireDetachedInvitesStmt = $pdo->prepare("
            UPDATE peer_video_room_invites i
            JOIN peer_video_rooms r ON r.room_id = i.room_id
            SET i.status = 'expired',
                i.updated_at = NOW()
            WHERE i.status = 'active'
              AND (i.expires_at <= NOW() OR r.status <> 'open')
        ");
        $expireDetachedInvitesStmt->execute();

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function video_find_open_room_hosted_by_user(PDO $pdo, int $userId, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT r.*, u.username AS host_username
        FROM peer_video_rooms r
        JOIN users u ON u.user_id = r.host_user_id
        WHERE r.host_user_id = ?
          AND r.status = 'open'
          AND r.expires_at > NOW()
        ORDER BY r.room_id DESC
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function video_log_room_event(PDO $pdo, int $roomId, ?int $actorUserId, ?int $targetUserId, string $eventType, ?array $payload = null): void
{
    $stmt = $pdo->prepare("
        INSERT INTO peer_video_room_events (
            room_id,
            actor_user_id,
            target_user_id,
            event_type,
            payload_json,
            created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $roomId,
        $actorUserId,
        $targetUserId,
        $eventType,
        video_json_encode($payload),
    ]);
}

function video_build_room_host_payload(array $roomRow): array
{
    $hostUsername = (string) ($roomRow['host_username'] ?? '');

    return [
        'userId' => (int) $roomRow['host_user_id'],
        'username' => $hostUsername,
        'displayName' => video_format_display_name($hostUsername),
        'initials' => video_initials($hostUsername),
    ];
}

function video_build_room_event_user_payload(?int $userId, ?string $username): ?array
{
    if (($userId ?? 0) <= 0) {
        return null;
    }

    $resolvedUsername = trim((string) ($username ?? ''));

    return [
        'userId' => (int) $userId,
        'username' => $resolvedUsername,
        'displayName' => video_format_display_name($resolvedUsername),
        'initials' => video_initials($resolvedUsername),
    ];
}

function video_build_room_event_payload(array $eventRow): array
{
    return [
        'eventId' => (int) $eventRow['event_id'],
        'type' => (string) $eventRow['event_type'],
        'createdAt' => (string) $eventRow['created_at'],
        'actor' => video_build_room_event_user_payload(
            $eventRow['actor_user_id'] !== null ? (int) $eventRow['actor_user_id'] : null,
            $eventRow['actor_username'] ?? null
        ),
        'target' => video_build_room_event_user_payload(
            $eventRow['target_user_id'] !== null ? (int) $eventRow['target_user_id'] : null,
            $eventRow['target_username'] ?? null
        ),
        'payload' => video_json_decode_array($eventRow['payload_json'] ?? null),
    ];
}

function video_create_room(PDO $pdo, array $user, array $input): array
{
    $userId = (int) ($user['user_id'] ?? 0);
    if ($userId <= 0) {
        video_json_response(['ok' => false, 'message' => 'Login required.'], 401);
    }

    video_cleanup_room_lifecycle($pdo);

    $topicLabel = video_normalize_topic_label(
        (string) ($input['topicLabel'] ?? $input['topic'] ?? '')
    );
    if ($topicLabel === '') {
        video_json_response(['ok' => false, 'message' => 'Topic is required.'], 422);
    }

    $visibility = video_normalize_room_visibility((string) ($input['visibility'] ?? ''));
    if ($visibility === '') {
        video_json_response(['ok' => false, 'message' => 'Visibility must be public or private.'], 422);
    }

    $topicKey = video_normalize_topic_key((string) ($input['topicKey'] ?? ''));

    $pdo->beginTransaction();
    try {
        $existingRoom = video_find_open_room_hosted_by_user($pdo, $userId, true);
        if ($existingRoom) {
            $pdo->commit();
            video_json_response([
                'ok' => false,
                'message' => 'You already have an active room. End or expire it before creating another one.',
                'code' => 'active_room_exists',
                'room' => video_build_room_payload_from_row($existingRoom, $userId),
            ], 409);
        }

        $roomPublicId = video_generate_room_public_id();
        $zegoRoomId = video_generate_zego_room_id_for_public_id($roomPublicId);

        $insertRoom = $pdo->prepare("
            INSERT INTO peer_video_rooms (
                room_public_id,
                zego_room_id,
                host_user_id,
                topic_key,
                topic_label,
                visibility,
                status,
                active_member_count,
                peak_member_count,
                expires_at,
                ended_at,
                ended_reason,
                last_member_joined_at,
                metadata_json,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'open', 1, 1, DATE_ADD(NOW(), INTERVAL 1 HOUR), NULL, NULL, NULL, ?, NOW(), NOW())
        ");
        $insertRoom->execute([
            $roomPublicId,
            $zegoRoomId,
            $userId,
            $topicKey,
            $topicLabel,
            $visibility,
            video_json_encode([
                'source' => 'room_create_api',
            ]),
        ]);
        $roomId = (int) $pdo->lastInsertId();

        $insertMember = $pdo->prepare("
            INSERT INTO peer_video_room_members (
                room_id,
                user_id,
                role,
                membership_status,
                presence_status,
                joined_via,
                invite_id,
                joined_at,
                last_seen_at,
                last_entered_room_at,
                last_left_room_at,
                removed_by_user_id,
                removed_at,
                remove_reason,
                created_at,
                updated_at
            ) VALUES (?, ?, 'host', 'active', 'offline', 'host_create', NULL, NOW(), NOW(), NULL, NULL, NULL, NULL, NULL, NOW(), NOW())
        ");
        $insertMember->execute([$roomId, $userId]);

        video_log_room_event($pdo, $roomId, $userId, $userId, 'room_created', [
            'roomPublicId' => $roomPublicId,
            'visibility' => $visibility,
            'topicLabel' => $topicLabel,
        ]);

        $selectRoom = $pdo->prepare("
            SELECT r.*, u.username AS host_username
            FROM peer_video_rooms r
            JOIN users u ON u.user_id = r.host_user_id
            WHERE r.room_id = ?
            LIMIT 1
        ");
        $selectRoom->execute([$roomId]);
        $roomRow = $selectRoom->fetch();
        if (!$roomRow) {
            throw new RuntimeException('Created room could not be reloaded.');
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return video_build_room_payload_from_row($roomRow, $userId);
}

function video_parse_room_public_id(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    return substr((string) $value, 0, 40);
}

function video_parse_invite_token(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    return substr((string) $value, 0, 80);
}

function video_parse_zego_room_id(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^A-Za-z0-9_]/', '_', $value);
    $value = preg_replace('/_+/', '_', (string) $value);
    $value = trim((string) $value, '_');
    return substr((string) $value, 0, 128);
}

function video_room_effective_status(array $roomRow): string
{
    $storedStatus = (string) ($roomRow['status'] ?? '');
    if ($storedStatus !== 'open') {
        return $storedStatus;
    }

    $expiresAt = strtotime((string) ($roomRow['expires_at'] ?? ''));
    if ($expiresAt !== false && $expiresAt <= time()) {
        return ((int) ($roomRow['peak_member_count'] ?? 1) > 1) ? 'expired' : 'cancelled';
    }

    return 'open';
}

function video_find_room_membership(PDO $pdo, int $roomId, int $userId, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT m.*, u.username
        FROM peer_video_room_members m
        JOIN users u ON u.user_id = m.user_id
        WHERE m.room_id = ?
          AND m.user_id = ?
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$roomId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function video_find_room_by_public_id(PDO $pdo, string $roomPublicId, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT r.*, u.username AS host_username
        FROM peer_video_rooms r
        JOIN users u ON u.user_id = r.host_user_id
        WHERE r.room_public_id = ?
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$roomPublicId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function video_find_room_by_zego_room_id(PDO $pdo, string $zegoRoomId, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT r.*, u.username AS host_username
        FROM peer_video_rooms r
        JOIN users u ON u.user_id = r.host_user_id
        WHERE r.zego_room_id = ?
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$zegoRoomId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function video_fetch_active_room_members(PDO $pdo, int $roomId): array
{
    $stmt = $pdo->prepare("
        SELECT m.*, u.username
        FROM peer_video_room_members m
        JOIN users u ON u.user_id = m.user_id
        WHERE m.room_id = ?
          AND m.membership_status = 'active'
        ORDER BY CASE WHEN m.role = 'host' THEN 0 ELSE 1 END, m.joined_at ASC, m.room_member_id ASC
    ");
    $stmt->execute([$roomId]);
    return $stmt->fetchAll() ?: [];
}

function video_find_active_invite_for_room(PDO $pdo, int $roomId, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT *
        FROM peer_video_room_invites
        WHERE room_id = ?
          AND status = 'active'
          AND expires_at > NOW()
        ORDER BY invite_id DESC
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$roomId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function video_find_invite_by_token(PDO $pdo, int $roomId, string $inviteToken, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT *
        FROM peer_video_room_invites
        WHERE room_id = ?
          AND invite_token = ?
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$roomId, $inviteToken]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function video_refresh_room_member_counts(PDO $pdo, int $roomId): void
{
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS active_count
        FROM peer_video_room_members
        WHERE room_id = ?
          AND membership_status = 'active'
    ");
    $countStmt->execute([$roomId]);
    $activeCount = (int) ($countStmt->fetch()['active_count'] ?? 0);

    $updateStmt = $pdo->prepare("
        UPDATE peer_video_rooms
        SET active_member_count = ?,
            peak_member_count = GREATEST(peak_member_count, ?),
            updated_at = NOW()
        WHERE room_id = ?
    ");
    $updateStmt->execute([$activeCount, $activeCount, $roomId]);
}

function video_generate_invite_token(): string
{
    return 'inv_' . strtolower(bin2hex(random_bytes(16)));
}

function video_build_invite_url(string $roomPublicId, string $inviteToken): string
{
    return './zego-call.php?room=' . rawurlencode($roomPublicId) . '&invite=' . rawurlencode($inviteToken);
}

function video_build_invite_payload(array $roomRow, array $inviteRow): array
{
    $roomPublicId = (string) $roomRow['room_public_id'];
    $inviteToken = (string) $inviteRow['invite_token'];

    return [
        'inviteId' => (int) $inviteRow['invite_id'],
        'roomId' => (int) $roomRow['room_id'],
        'roomPublicId' => $roomPublicId,
        'inviteToken' => $inviteToken,
        'status' => (string) $inviteRow['status'],
        'expiresAt' => (string) $inviteRow['expires_at'],
        'createdAt' => (string) $inviteRow['created_at'],
        'inviteUrl' => video_build_invite_url($roomPublicId, $inviteToken),
    ];
}

function video_build_room_member_payload(array $memberRow, int $viewerUserId): array
{
    $userId = (int) $memberRow['user_id'];
    $username = (string) ($memberRow['username'] ?? '');

    return [
        'userId' => $userId,
        'username' => $username,
        'displayName' => video_format_display_name($username),
        'initials' => video_initials($username),
        'role' => (string) $memberRow['role'],
        'membershipStatus' => (string) $memberRow['membership_status'],
        'presenceStatus' => (string) $memberRow['presence_status'],
        'joinedAt' => (string) $memberRow['joined_at'],
        'lastSeenAt' => $memberRow['last_seen_at'] !== null ? (string) $memberRow['last_seen_at'] : null,
        'isCurrentUser' => $userId === $viewerUserId,
    ];
}

function video_build_room_access_payload(array $roomRow, int $viewerUserId, ?array $membershipRow = null): array
{
    $effectiveStatus = video_room_effective_status($roomRow);
    $isHost = (int) $roomRow['host_user_id'] === $viewerUserId;
    $membershipStatus = $membershipRow ? (string) ($membershipRow['membership_status'] ?? '') : null;
    $presenceStatus = $membershipRow ? (string) ($membershipRow['presence_status'] ?? '') : null;
    $memberRole = $isHost ? 'host' : ($membershipRow ? (string) ($membershipRow['role'] ?? '') : null);
    $isActiveMember = $isHost || $membershipStatus === 'active';
    $isRemoved = $membershipStatus === 'removed';
    $hasLeft = $membershipStatus === 'left';
    $canOpenRoomPage = false;
    $canJoinDirectly = false;
    $requiresInvite = false;
    $joinMode = 'closed';

    if ($effectiveStatus !== 'open') {
        $joinMode = 'closed';
    } elseif ($isRemoved) {
        $joinMode = 'blocked_removed';
    } elseif ($hasLeft) {
        $joinMode = 'blocked_left';
    } elseif ($isActiveMember) {
        $canOpenRoomPage = true;
        $joinMode = 'member_reentry';
    } elseif ((string) $roomRow['visibility'] === 'public') {
        $canJoinDirectly = true;
        $joinMode = 'public_join';
    } else {
        $requiresInvite = true;
        $joinMode = 'invite_required';
    }

    return [
        'effectiveStatus' => $effectiveStatus,
        'isHost' => $isHost,
        'isMember' => $isActiveMember,
        'memberRole' => $memberRole !== '' ? $memberRole : null,
        'membershipStatus' => $membershipStatus,
        'presenceStatus' => $presenceStatus,
        'canOpenRoomPage' => $canOpenRoomPage,
        'canJoinDirectly' => $canJoinDirectly,
        'requiresInvite' => $requiresInvite,
        'joinMode' => $joinMode,
    ];
}

function video_build_room_payload_from_row(
    array $roomRow,
    int $viewerUserId,
    ?array $membershipRow = null,
    array $memberRows = [],
    ?array $activeInvite = null
): array
{
    $hostUserId = (int) $roomRow['host_user_id'];
    $roomPublicId = (string) $roomRow['room_public_id'];
    $visibility = (string) $roomRow['visibility'];
    $access = video_build_room_access_payload($roomRow, $viewerUserId, $membershipRow);
    $canRevealRoomInternals = (bool) ($access['canOpenRoomPage'] ?? false);

    return [
        'roomId' => (int) $roomRow['room_id'],
        'roomPublicId' => $roomPublicId,
        'zegoRoomId' => $canRevealRoomInternals ? (string) $roomRow['zego_room_id'] : null,
        'topic' => [
            'key' => $roomRow['topic_key'] !== null ? (string) $roomRow['topic_key'] : null,
            'label' => (string) $roomRow['topic_label'],
        ],
        'visibility' => $visibility,
        'status' => (string) $roomRow['status'],
        'effectiveStatus' => (string) $access['effectiveStatus'],
        'host' => video_build_room_host_payload($roomRow),
        'memberCount' => (int) $roomRow['active_member_count'],
        'peakMemberCount' => (int) $roomRow['peak_member_count'],
        'expiresAt' => (string) $roomRow['expires_at'],
        'endedAt' => $roomRow['ended_at'] !== null ? (string) $roomRow['ended_at'] : null,
        'endedReason' => $roomRow['ended_reason'] !== null ? (string) $roomRow['ended_reason'] : null,
        'createdAt' => (string) $roomRow['created_at'],
        'roomPageUrl' => video_room_page_url($roomPublicId),
        'currentUserRole' => $access['memberRole'],
        'canManageMembers' => $viewerUserId === $hostUserId,
        'canCreateInvite' => $viewerUserId === $hostUserId && $visibility === 'private' && (string) $access['effectiveStatus'] === 'open',
        'activeInvite' => $activeInvite,
        'access' => $access,
        'members' => $canRevealRoomInternals
            ? array_map(
                static fn(array $memberRow): array => video_build_room_member_payload($memberRow, $viewerUserId),
                $memberRows
            )
            : [],
    ];
}

function video_list_rooms(PDO $pdo, int $viewerUserId): array
{
    video_cleanup_room_lifecycle($pdo);

    $stmt = $pdo->prepare("
        SELECT r.*, u.username AS host_username
        FROM peer_video_rooms r
        JOIN users u ON u.user_id = r.host_user_id
        WHERE r.status = 'open'
          AND r.expires_at > NOW()
        ORDER BY CASE WHEN r.host_user_id = ? THEN 0 ELSE 1 END, r.created_at DESC, r.room_id DESC
    ");
    $stmt->execute([$viewerUserId]);
    $rows = $stmt->fetchAll() ?: [];

    $rooms = [];
    foreach ($rows as $row) {
        $membershipRow = video_find_room_membership($pdo, (int) $row['room_id'], $viewerUserId);
        $rooms[] = video_build_room_payload_from_row($row, $viewerUserId, $membershipRow);
    }

    return $rooms;
}

function video_fetch_room_events(PDO $pdo, int $viewerUserId, string $roomPublicId, int $afterEventId = 0, int $limit = 20): array
{
    video_cleanup_room_lifecycle($pdo);

    $roomPublicId = video_parse_room_public_id($roomPublicId);
    if ($roomPublicId === '') {
        video_json_response(['ok' => false, 'message' => 'Room identifier is required.'], 422);
    }

    $afterEventId = max(0, $afterEventId);
    $limit = max(1, min(50, $limit));

    $roomRow = video_find_room_by_public_id($pdo, $roomPublicId);
    if (!$roomRow) {
        video_json_response(['ok' => false, 'message' => 'Room not found.'], 404);
    }

    $roomId = (int) $roomRow['room_id'];
    $membershipRow = video_find_room_membership($pdo, $roomId, $viewerUserId);
    $isHost = (int) $roomRow['host_user_id'] === $viewerUserId;
    $isActiveMember = $membershipRow && (string) ($membershipRow['membership_status'] ?? '') === 'active';

    if (!$isHost && !$isActiveMember) {
        video_json_response(['ok' => false, 'message' => 'You do not have access to this room event stream.'], 403);
    }

    $eventSql = "
        SELECT
            e.*,
            actor.username AS actor_username,
            target.username AS target_username
        FROM peer_video_room_events e
        LEFT JOIN users actor ON actor.user_id = e.actor_user_id
        LEFT JOIN users target ON target.user_id = e.target_user_id
        WHERE e.room_id = ?
          AND e.event_id > ?
        ORDER BY e.event_id ASC
        LIMIT $limit
    ";
    $eventStmt = $pdo->prepare($eventSql);
    $eventStmt->execute([$roomId, $afterEventId]);
    $eventRows = $eventStmt->fetchAll() ?: [];

    $latestEventId = $afterEventId;
    $events = [];
    foreach ($eventRows as $eventRow) {
        $events[] = video_build_room_event_payload($eventRow);
        $latestEventId = max($latestEventId, (int) $eventRow['event_id']);
    }

    return [
        'room' => video_build_room_payload_from_row($roomRow, $viewerUserId, $membershipRow),
        'events' => $events,
        'latestEventId' => $latestEventId,
    ];
}

function video_get_room_detail(PDO $pdo, int $viewerUserId, string $roomPublicId, string $inviteToken = ''): array
{
    video_cleanup_room_lifecycle($pdo);

    $roomPublicId = video_parse_room_public_id($roomPublicId);
    $inviteToken = video_parse_invite_token($inviteToken);

    if ($roomPublicId === '') {
        video_json_response(['ok' => false, 'message' => 'Room identifier is required.'], 422);
    }

    if ($inviteToken !== '') {
        $accessResult = video_grant_room_access($pdo, $viewerUserId, $roomPublicId, $inviteToken);
        return [
            'room' => $accessResult['room'],
            'resolvedBy' => $accessResult['resolvedBy'],
            'membershipCreated' => $accessResult['membershipCreated'],
            'activeInvite' => null,
        ];
    }

    $roomRow = video_find_room_by_public_id($pdo, $roomPublicId);
    if (!$roomRow) {
        video_json_response(['ok' => false, 'message' => 'Room not found.'], 404);
    }

    $membershipRow = video_find_room_membership($pdo, (int) $roomRow['room_id'], $viewerUserId);
    $access = video_build_room_access_payload($roomRow, $viewerUserId, $membershipRow);
    $memberRows = (bool) ($access['canOpenRoomPage'] ?? false)
        ? video_fetch_active_room_members($pdo, (int) $roomRow['room_id'])
        : [];
    $activeInvite = null;

    if (
        (int) $roomRow['host_user_id'] === $viewerUserId
        && (string) $roomRow['visibility'] === 'private'
        && (string) ($access['effectiveStatus'] ?? '') === 'open'
    ) {
        $inviteRow = video_find_active_invite_for_room($pdo, (int) $roomRow['room_id']);
        if ($inviteRow) {
            $activeInvite = video_build_invite_payload($roomRow, $inviteRow);
        }
    }

    return [
        'room' => video_build_room_payload_from_row($roomRow, $viewerUserId, $membershipRow, $memberRows, $activeInvite),
        'resolvedBy' => null,
        'membershipCreated' => false,
        'activeInvite' => $activeInvite,
    ];
}

function video_create_or_get_room_invite(PDO $pdo, int $userId, string $roomPublicId): array
{
    video_cleanup_room_lifecycle($pdo);

    $roomPublicId = video_parse_room_public_id($roomPublicId);
    if ($roomPublicId === '') {
        video_json_response(['ok' => false, 'message' => 'Room identifier is required.'], 422);
    }

    $pdo->beginTransaction();
    try {
        $roomRow = video_find_room_by_public_id($pdo, $roomPublicId, true);
        if (!$roomRow) {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Room not found.'], 404);
        }

        if ((int) $roomRow['host_user_id'] !== $userId) {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Only the host can create invite links.'], 403);
        }

        if ((string) $roomRow['visibility'] !== 'private') {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Invite links are only available for private rooms.'], 422);
        }

        if (video_room_effective_status($roomRow) !== 'open') {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Room is no longer open for invites.'], 409);
        }

        $inviteRow = video_find_active_invite_for_room($pdo, (int) $roomRow['room_id'], true);
        if (!$inviteRow) {
            $insertInvite = $pdo->prepare("
                INSERT INTO peer_video_room_invites (
                    room_id,
                    invite_token,
                    created_by_user_id,
                    target_user_id,
                    status,
                    expires_at,
                    consumed_by_user_id,
                    consumed_at,
                    revoked_at,
                    metadata_json,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, NULL, 'active', ?, NULL, NULL, NULL, ?, NOW(), NOW())
            ");
            $inviteToken = video_generate_invite_token();
            $insertInvite->execute([
                (int) $roomRow['room_id'],
                $inviteToken,
                $userId,
                (string) $roomRow['expires_at'],
                video_json_encode(['source' => 'host_create_invite']),
            ]);

            $inviteId = (int) $pdo->lastInsertId();
            $inviteSelect = $pdo->prepare("
                SELECT *
                FROM peer_video_room_invites
                WHERE invite_id = ?
                LIMIT 1
            ");
            $inviteSelect->execute([$inviteId]);
            $inviteRow = $inviteSelect->fetch();

            video_log_room_event($pdo, (int) $roomRow['room_id'], $userId, null, 'invite_created', [
                'inviteId' => $inviteId,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return video_build_invite_payload($roomRow, $inviteRow);
}

function video_end_room(PDO $pdo, int $hostUserId, string $roomPublicId): array
{
    video_cleanup_room_lifecycle($pdo);

    $roomPublicId = video_parse_room_public_id($roomPublicId);
    if ($roomPublicId === '') {
        video_json_response(['ok' => false, 'message' => 'Room identifier is required.'], 422);
    }

    $pdo->beginTransaction();
    try {
        $roomRow = video_find_room_by_public_id($pdo, $roomPublicId, true);
        if (!$roomRow) {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Room not found.'], 404);
        }

        if ((int) $roomRow['host_user_id'] !== $hostUserId) {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Only the host can end this room.'], 403);
        }

        if (video_room_effective_status($roomRow) !== 'open') {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Room is no longer open.'], 409);
        }

        $roomId = (int) $roomRow['room_id'];

        $endRoomStmt = $pdo->prepare("
            UPDATE peer_video_rooms
            SET status = 'ended',
                ended_at = COALESCE(ended_at, NOW()),
                ended_reason = 'host_ended',
                updated_at = NOW()
            WHERE room_id = ?
        ");
        $endRoomStmt->execute([$roomId]);

        $expireInvitesStmt = $pdo->prepare("
            UPDATE peer_video_room_invites
            SET status = 'expired',
                updated_at = NOW()
            WHERE room_id = ?
              AND status = 'active'
        ");
        $expireInvitesStmt->execute([$roomId]);

        $offlineMembersStmt = $pdo->prepare("
            UPDATE peer_video_room_members
            SET presence_status = 'offline',
                last_seen_at = NOW(),
                last_left_room_at = COALESCE(last_left_room_at, NOW()),
                updated_at = NOW()
            WHERE room_id = ?
              AND membership_status = 'active'
        ");
        $offlineMembersStmt->execute([$roomId]);

        video_log_room_event($pdo, $roomId, $hostUserId, null, 'room_ended', [
            'endedReason' => 'host_ended',
        ]);

        $roomRow = video_find_room_by_public_id($pdo, $roomPublicId, true);
        $membershipRow = video_find_room_membership($pdo, $roomId, $hostUserId);
        $roomPayload = video_build_room_payload_from_row($roomRow, $hostUserId, $membershipRow);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'room' => $roomPayload,
    ];
}

function video_remove_room_member(PDO $pdo, int $hostUserId, string $roomPublicId, int $targetUserId): array
{
    video_cleanup_room_lifecycle($pdo);

    $roomPublicId = video_parse_room_public_id($roomPublicId);
    if ($roomPublicId === '') {
        video_json_response(['ok' => false, 'message' => 'Room identifier is required.'], 422);
    }

    if ($targetUserId <= 0) {
        video_json_response(['ok' => false, 'message' => 'Target member is required.'], 422);
    }

    $pdo->beginTransaction();
    try {
        $roomRow = video_find_room_by_public_id($pdo, $roomPublicId, true);
        if (!$roomRow) {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Room not found.'], 404);
        }

        if ((int) $roomRow['host_user_id'] !== $hostUserId) {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Only the host can remove members.'], 403);
        }

        if (video_room_effective_status($roomRow) !== 'open') {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Room is no longer open for member management.'], 409);
        }

        if ($targetUserId === $hostUserId) {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'The host cannot remove themselves.'], 422);
        }

        $targetMembership = video_find_room_membership($pdo, (int) $roomRow['room_id'], $targetUserId, true);
        if (!$targetMembership) {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Member not found in this room.'], 404);
        }

        $targetStatus = (string) ($targetMembership['membership_status'] ?? '');
        if ($targetStatus === 'removed') {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'That member has already been removed.'], 409);
        }
        if ($targetStatus === 'left') {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'That member already left the room.'], 409);
        }

        $removeStmt = $pdo->prepare("
            UPDATE peer_video_room_members
            SET membership_status = 'removed',
                presence_status = 'offline',
                last_seen_at = NOW(),
                last_left_room_at = COALESCE(last_left_room_at, NOW()),
                removed_by_user_id = ?,
                removed_at = NOW(),
                remove_reason = 'kicked_by_host',
                updated_at = NOW()
            WHERE room_id = ?
              AND user_id = ?
        ");
        $removeStmt->execute([
            $hostUserId,
            (int) $roomRow['room_id'],
            $targetUserId,
        ]);

        video_refresh_room_member_counts($pdo, (int) $roomRow['room_id']);
        video_log_room_event($pdo, (int) $roomRow['room_id'], $hostUserId, $targetUserId, 'member_removed', [
            'removeReason' => 'kicked_by_host',
        ]);

        $roomRow = video_find_room_by_public_id($pdo, $roomPublicId, true);
        $hostMembership = video_find_room_membership($pdo, (int) $roomRow['room_id'], $hostUserId);
        $removedMembership = video_find_room_membership($pdo, (int) $roomRow['room_id'], $targetUserId);
        $memberRows = video_fetch_active_room_members($pdo, (int) $roomRow['room_id']);
        $roomPayload = video_build_room_payload_from_row($roomRow, $hostUserId, $hostMembership, $memberRows);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'room' => $roomPayload,
        'removedMember' => video_build_room_member_payload($removedMembership ?: $targetMembership, $hostUserId),
    ];
}

function video_grant_room_access(PDO $pdo, int $userId, string $roomPublicId, string $inviteToken = ''): array
{
    video_cleanup_room_lifecycle($pdo);

    $roomPublicId = video_parse_room_public_id($roomPublicId);
    $inviteToken = video_parse_invite_token($inviteToken);

    if ($roomPublicId === '') {
        video_json_response(['ok' => false, 'message' => 'Room identifier is required.'], 422);
    }

    $resolvedBy = 'unknown';

    $pdo->beginTransaction();
    try {
        $roomRow = video_find_room_by_public_id($pdo, $roomPublicId, true);
        if (!$roomRow) {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Room not found.'], 404);
        }

        $effectiveStatus = video_room_effective_status($roomRow);
        if ($effectiveStatus !== 'open') {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Room is no longer open.'], 409);
        }

        $roomId = (int) $roomRow['room_id'];
        $membershipRow = video_find_room_membership($pdo, $roomId, $userId);
        $isHost = (int) $roomRow['host_user_id'] === $userId;
        $createdMembership = false;

        if ($membershipRow) {
            $membershipStatus = (string) ($membershipRow['membership_status'] ?? '');
            if ($membershipStatus === 'removed') {
                $pdo->commit();
                video_json_response(['ok' => false, 'message' => 'You were removed from this room.'], 403);
            }
            if ($membershipStatus === 'left') {
                $pdo->commit();
                video_json_response(['ok' => false, 'message' => 'You already left this room.'], 403);
            }
            $resolvedBy = $isHost ? 'host' : 'member';
        } else {
            if ((string) $roomRow['visibility'] === 'public') {
                $resolvedBy = 'public';
            } else {
                if ($inviteToken === '') {
                    $pdo->commit();
                    video_json_response(['ok' => false, 'message' => 'Invite token is required for private rooms.'], 403);
                }

                $inviteRow = video_find_invite_by_token($pdo, $roomId, $inviteToken, true);
                if (!$inviteRow) {
                    $pdo->commit();
                    video_json_response(['ok' => false, 'message' => 'Invite link is invalid.'], 403);
                }

                if ((string) $inviteRow['status'] !== 'active') {
                    $pdo->commit();
                    video_json_response(['ok' => false, 'message' => 'Invite link is no longer active.'], 403);
                }

                if (strtotime((string) $inviteRow['expires_at']) <= time()) {
                    $expireInvite = $pdo->prepare("
                        UPDATE peer_video_room_invites
                        SET status = 'expired',
                            updated_at = NOW()
                        WHERE invite_id = ?
                    ");
                    $expireInvite->execute([(int) $inviteRow['invite_id']]);
                    $pdo->commit();
                    video_json_response(['ok' => false, 'message' => 'Invite link has expired.'], 403);
                }

                $targetUserId = $inviteRow['target_user_id'] !== null ? (int) $inviteRow['target_user_id'] : null;
                if ($targetUserId !== null && $targetUserId !== $userId) {
                    $pdo->commit();
                    video_json_response(['ok' => false, 'message' => 'Invite link does not belong to the current user.'], 403);
                }

                $touchInvite = $pdo->prepare("
                    UPDATE peer_video_room_invites
                    SET consumed_by_user_id = COALESCE(consumed_by_user_id, ?),
                        consumed_at = COALESCE(consumed_at, NOW()),
                        updated_at = NOW()
                    WHERE invite_id = ?
                ");
                $touchInvite->execute([$userId, (int) $inviteRow['invite_id']]);
                $resolvedBy = 'invite';
            }

            $insertMember = $pdo->prepare("
                INSERT INTO peer_video_room_members (
                    room_id,
                    user_id,
                    role,
                    membership_status,
                    presence_status,
                    joined_via,
                    invite_id,
                    joined_at,
                    last_seen_at,
                    last_entered_room_at,
                    last_left_room_at,
                    removed_by_user_id,
                    removed_at,
                    remove_reason,
                    created_at,
                    updated_at
                ) VALUES (?, ?, 'participant', 'active', 'offline', ?, ?, NOW(), NOW(), NULL, NULL, NULL, NULL, NULL, NOW(), NOW())
            ");
            $insertMember->execute([
                $roomId,
                $userId,
                $resolvedBy === 'invite' ? 'invite_link' : 'public_list',
                isset($inviteRow) ? (int) $inviteRow['invite_id'] : null,
            ]);
            $createdMembership = true;

            $touchRoom = $pdo->prepare("
                UPDATE peer_video_rooms
                SET last_member_joined_at = NOW(),
                    updated_at = NOW()
                WHERE room_id = ?
            ");
            $touchRoom->execute([$roomId]);

            video_refresh_room_member_counts($pdo, $roomId);
            if ($resolvedBy === 'invite' && isset($inviteRow)) {
                video_log_room_event($pdo, $roomId, $userId, $userId, 'invite_consumed', [
                    'inviteId' => (int) $inviteRow['invite_id'],
                ]);
            }
            video_log_room_event($pdo, $roomId, $userId, $userId, 'member_joined', [
                'joinedVia' => $resolvedBy,
            ]);
        }

        $roomRow = video_find_room_by_public_id($pdo, $roomPublicId, true);
        $membershipRow = video_find_room_membership($pdo, $roomId, $userId);
        $memberRows = video_fetch_active_room_members($pdo, $roomId);
        $roomPayload = video_build_room_payload_from_row($roomRow, $userId, $membershipRow, $memberRows);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'room' => $roomPayload,
        'resolvedBy' => $resolvedBy,
        'membershipCreated' => $createdMembership,
    ];
}

function video_update_room_presence(PDO $pdo, int $userId, string $roomPublicId, string $presenceStatus): array
{
    video_cleanup_room_lifecycle($pdo);

    $roomPublicId = video_parse_room_public_id($roomPublicId);
    $presenceStatus = strtolower(trim($presenceStatus));

    if ($roomPublicId === '') {
        video_json_response(['ok' => false, 'message' => 'Room identifier is required.'], 422);
    }

    if (!in_array($presenceStatus, video_room_presence_status_values(), true)) {
        video_json_response(['ok' => false, 'message' => 'Presence status is invalid.'], 422);
    }

    $pdo->beginTransaction();
    try {
        $roomRow = video_find_room_by_public_id($pdo, $roomPublicId, true);
        if (!$roomRow) {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Room not found.'], 404);
        }

        $effectiveStatus = video_room_effective_status($roomRow);
        if ($effectiveStatus !== 'open' && $presenceStatus !== 'offline') {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'Room is no longer open.'], 409);
        }

        $roomId = (int) $roomRow['room_id'];
        $membershipRow = video_find_room_membership($pdo, $roomId, $userId, true);
        if (!$membershipRow || (string) ($membershipRow['membership_status'] ?? '') !== 'active') {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'You do not have access to this room.'], 403);
        }

        $previousPresence = (string) ($membershipRow['presence_status'] ?? 'offline');
        $hasEnteredBefore = !empty($membershipRow['last_entered_room_at']);
        $joinedVia = (string) ($membershipRow['joined_via'] ?? '');
        $nextJoinedVia = $joinedVia;

        if (
            $presenceStatus !== 'offline'
            && $hasEnteredBefore
            && $previousPresence === 'offline'
            && $joinedVia !== 'host_create'
        ) {
            $nextJoinedVia = 'room_link_reentry';
        }

        $updatePresence = $pdo->prepare("
            UPDATE peer_video_room_members
            SET presence_status = ?,
                joined_via = ?,
                last_seen_at = NOW(),
                last_entered_room_at = CASE WHEN ? <> 'offline' THEN NOW() ELSE last_entered_room_at END,
                last_left_room_at = CASE WHEN ? = 'offline' THEN NOW() ELSE last_left_room_at END,
                updated_at = NOW()
            WHERE room_id = ?
              AND user_id = ?
        ");
        $updatePresence->execute([
            $presenceStatus,
            $nextJoinedVia,
            $presenceStatus,
            $presenceStatus,
            $roomId,
            $userId,
        ]);

        if ($previousPresence === 'offline' && $presenceStatus !== 'offline' && $hasEnteredBefore) {
            video_log_room_event($pdo, $roomId, $userId, $userId, 'member_reentered', [
                'previousPresence' => $previousPresence,
                'presenceStatus' => $presenceStatus,
            ]);
        } elseif ($previousPresence !== 'offline' && $presenceStatus === 'offline') {
            video_log_room_event($pdo, $roomId, $userId, $userId, 'member_presence_left', [
                'previousPresence' => $previousPresence,
                'presenceStatus' => $presenceStatus,
            ]);
        }

        $membershipRow = video_find_room_membership($pdo, $roomId, $userId);
        $memberRows = video_fetch_active_room_members($pdo, $roomId);
        $roomPayload = video_build_room_payload_from_row($roomRow, $userId, $membershipRow, $memberRows);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'room' => $roomPayload,
        'member' => video_build_room_member_payload($membershipRow, $userId),
        'presenceChanged' => $previousPresence !== $presenceStatus,
    ];
}

function video_assert_room_token_access(PDO $pdo, int $userId, string $zegoRoomId): array
{
    video_cleanup_room_lifecycle($pdo);

    $roomRow = video_find_room_by_zego_room_id($pdo, $zegoRoomId);
    if (!$roomRow) {
        video_json_response([
            'ok' => false,
            'message' => 'The requested ZEGO room is not registered in the application.',
        ], 404);
    }

    $effectiveStatus = video_room_effective_status($roomRow);
    if ($effectiveStatus !== 'open') {
        video_json_response([
            'ok' => false,
            'message' => 'The requested room is no longer open.',
        ], 403);
    }

    $membershipRow = video_find_room_membership($pdo, (int) $roomRow['room_id'], $userId);
    $isHost = (int) $roomRow['host_user_id'] === $userId;
    $isActiveMember = $membershipRow && (string) ($membershipRow['membership_status'] ?? '') === 'active';

    if (!$isHost && !$isActiveMember) {
        video_json_response([
            'ok' => false,
            'message' => 'You do not have access to this room.',
        ], 403);
    }

    return $roomRow;
}
