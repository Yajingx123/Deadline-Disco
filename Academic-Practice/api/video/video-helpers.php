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

function video_queue_mode(): string
{
    return 'random_1v1';
}

function video_queue_ttl_seconds(): int
{
    return 45;
}

function video_session_statuses_open(): array
{
    return ['matched', 'connecting', 'active'];
}

function video_json_encode(?array $value): ?string
{
    if ($value === null) {
        return null;
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

function video_generate_room_id(int $spaceId): string
{
    $suffix = sprintf('%d_%s', $spaceId, substr(bin2hex(random_bytes(6)), 0, 12));
    $prefix = video_room_prefix();
    $maxPrefixLength = max(0, 128 - strlen($suffix));

    if (strlen($prefix) > $maxPrefixLength) {
        $prefix = substr($prefix, 0, $maxPrefixLength);
    }

    return $prefix . $suffix;
}

function video_cleanup_stale_queue(PDO $pdo): void
{
    $ttlSeconds = video_queue_ttl_seconds();

    $expireWaiting = $pdo->prepare("
        UPDATE peer_video_match_queue
        SET status = 'expired',
            current_session_id = NULL,
            joined_space_id = NULL,
            expires_at = COALESCE(expires_at, NOW()),
            updated_at = NOW()
        WHERE status = 'waiting'
          AND (
            (expires_at IS NOT NULL AND expires_at < NOW())
            OR
            last_heartbeat_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
          )
    ");
    $expireWaiting->execute([$ttlSeconds]);

    $clearEndedMatches = $pdo->prepare("
        UPDATE peer_video_match_queue q
        JOIN peer_video_sessions s ON s.session_id = q.current_session_id
        SET q.status = 'idle',
            q.current_session_id = NULL,
            q.joined_space_id = NULL,
            q.updated_at = NOW()
        WHERE q.status = 'matched'
          AND s.status IN ('ended', 'cancelled', 'expired')
    ");
    $clearEndedMatches->execute();
}

function video_upsert_settings(PDO $pdo, int $userId, array $input): void
{
    $cameraEnabled = array_key_exists('cameraEnabled', $input) ? ((bool) $input['cameraEnabled'] ? 1 : 0) : 1;
    $microphoneEnabled = array_key_exists('microphoneEnabled', $input) ? ((bool) $input['microphoneEnabled'] ? 1 : 0) : 1;

    $stmt = $pdo->prepare("
        INSERT INTO peer_video_match_settings (
            user_id,
            feature_enabled,
            auto_match_enabled,
            preferred_mode,
            camera_enabled,
            microphone_enabled,
            created_at,
            updated_at
        ) VALUES (?, 1, 1, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            preferred_mode = VALUES(preferred_mode),
            camera_enabled = VALUES(camera_enabled),
            microphone_enabled = VALUES(microphone_enabled),
            updated_at = NOW()
    ");
    $stmt->execute([
        $userId,
        video_queue_mode(),
        $cameraEnabled,
        $microphoneEnabled,
    ]);
}

function video_touch_waiting_queue(PDO $pdo, int $userId): void
{
    $ttlSeconds = video_queue_ttl_seconds();
    $stmt = $pdo->prepare("
        UPDATE peer_video_match_queue
        SET last_heartbeat_at = NOW(),
            expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
            updated_at = NOW()
        WHERE user_id = ?
          AND status = 'waiting'
    ");
    $stmt->execute([$ttlSeconds, $userId]);
}

function video_find_queue_row(PDO $pdo, int $userId, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT *
        FROM peer_video_match_queue
        WHERE user_id = ?
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

function video_find_open_session_for_user(PDO $pdo, int $userId, bool $forUpdate = false): ?array
{
    $openStatuses = "'" . implode("','", video_session_statuses_open()) . "'";
    $sql = "
        SELECT
            s.*,
            u1.username AS user_one_username,
            u2.username AS user_two_username
        FROM peer_video_sessions s
        JOIN users u1 ON u1.user_id = s.user_one_id
        JOIN users u2 ON u2.user_id = s.user_two_id
        WHERE (s.user_one_id = ? OR s.user_two_id = ?)
          AND s.status IN ($openStatuses)
        ORDER BY s.session_id DESC
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function video_find_candidate_queue(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT q.*
        FROM peer_video_match_queue q
        LEFT JOIN peer_video_match_settings s ON s.user_id = q.user_id
        WHERE q.user_id <> ?
          AND q.status = 'waiting'
          AND q.queue_mode = ?
          AND (q.expires_at IS NULL OR q.expires_at >= NOW())
          AND COALESCE(s.feature_enabled, 1) = 1
          AND COALESCE(s.auto_match_enabled, 1) = 1
          AND (s.blocked_until IS NULL OR s.blocked_until < NOW())
        ORDER BY q.enqueued_at ASC, q.queue_id ASC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$userId, video_queue_mode()]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function video_log_event(PDO $pdo, int $sessionId, ?int $actorUserId, string $eventType, ?array $payload = null): void
{
    $stmt = $pdo->prepare("
        INSERT INTO peer_video_session_events (
            session_id,
            actor_user_id,
            event_type,
            payload_json,
            created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $sessionId,
        $actorUserId,
        $eventType,
        video_json_encode($payload),
    ]);
}

function video_build_session_payload(array $row, int $currentUserId): array
{
    $partnerId = (int) $row['user_one_id'] === $currentUserId ? (int) $row['user_two_id'] : (int) $row['user_one_id'];
    $partnerUsername = (int) $row['user_one_id'] === $currentUserId
        ? (string) $row['user_two_username']
        : (string) $row['user_one_username'];

    return [
        'sessionId' => (int) $row['session_id'],
        'spaceId' => (int) $row['space_id'],
        'roomId' => (string) $row['room_id'],
        'queueMode' => (string) $row['queue_mode'],
        'status' => (string) $row['status'],
        'matchedAt' => (string) $row['matched_at'],
        'startedAt' => (string) ($row['started_at'] ?? ''),
        'endedAt' => (string) ($row['ended_at'] ?? ''),
        'lastActivityAt' => (string) ($row['last_activity_at'] ?? ''),
        'partner' => [
            'userId' => $partnerId,
            'username' => $partnerUsername,
            'displayName' => video_format_display_name($partnerUsername),
            'initials' => video_initials($partnerUsername),
        ],
    ];
}

function video_build_queue_payload(array $row): array
{
    return [
        'queueId' => (int) $row['queue_id'],
        'status' => (string) $row['status'],
        'queueMode' => (string) $row['queue_mode'],
        'requestToken' => (string) $row['request_token'],
        'enqueuedAt' => (string) $row['enqueued_at'],
        'matchedAt' => (string) ($row['matched_at'] ?? ''),
        'lastHeartbeatAt' => (string) ($row['last_heartbeat_at'] ?? ''),
        'expiresAt' => (string) ($row['expires_at'] ?? ''),
    ];
}

function video_build_room_payload(?array $session): ?array
{
    if (!$session || empty($session['roomId'])) {
        return null;
    }

    return [
        'roomId' => (string) $session['roomId'],
        'spaceId' => (int) ($session['spaceId'] ?? 0),
        'queueMode' => (string) ($session['queueMode'] ?? video_queue_mode()),
        'sessionStatus' => (string) ($session['status'] ?? ''),
        'roomPageUrl' => './video_call/room.php?roomID=' . rawurlencode((string) $session['roomId']),
    ];
}

function video_build_response_payload(array $state, array $extra = []): array
{
    $session = is_array($state['session'] ?? null) ? $state['session'] : null;

    return array_merge([
        'state' => $state,
        'videoMatch' => $state,
        'mode' => (string) ($state['mode'] ?? 'idle'),
        'queue' => is_array($state['queue'] ?? null) ? $state['queue'] : null,
        'session' => $session,
        'room' => video_build_room_payload($session),
    ], $extra);
}

function video_fetch_state(PDO $pdo, int $userId, bool $touchHeartbeat = false): array
{
    video_cleanup_stale_queue($pdo);

    if ($touchHeartbeat) {
        video_touch_waiting_queue($pdo, $userId);
    }

    $queueRow = video_find_queue_row($pdo, $userId);
    $sessionRow = video_find_open_session_for_user($pdo, $userId);

    if ($queueRow && (string) $queueRow['status'] === 'matched' && !$sessionRow) {
        $reset = $pdo->prepare("
            UPDATE peer_video_match_queue
            SET status = 'idle',
                current_session_id = NULL,
                joined_space_id = NULL,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $reset->execute([$userId]);
        $queueRow = video_find_queue_row($pdo, $userId);
    }

    $mode = 'idle';
    if ($sessionRow) {
        $mode = 'matched';
    } elseif ($queueRow && (string) $queueRow['status'] === 'waiting') {
        $mode = 'waiting';
    }

    return [
        'mode' => $mode,
        'queue' => $queueRow ? video_build_queue_payload($queueRow) : null,
        'session' => $sessionRow ? video_build_session_payload($sessionRow, $userId) : null,
    ];
}

function video_join_queue(PDO $pdo, int $userId, array $input = []): array
{
    video_cleanup_stale_queue($pdo);

    $pdo->beginTransaction();
    try {
        video_upsert_settings($pdo, $userId, $input);

        $existingSession = video_find_open_session_for_user($pdo, $userId, true);
        if ($existingSession) {
            $touchSession = $pdo->prepare("
                UPDATE peer_video_sessions
                SET last_activity_at = NOW(), updated_at = NOW()
                WHERE session_id = ?
            ");
            $touchSession->execute([(int) $existingSession['session_id']]);
            $pdo->commit();
            return video_fetch_state($pdo, $userId, false);
        }

        $queueRow = video_find_queue_row($pdo, $userId, true);
        $requestToken = video_uuid();
        $ttlSeconds = video_queue_ttl_seconds();
        $queueMode = video_queue_mode();
        $queueMetadata = video_json_encode([
            'cameraEnabled' => array_key_exists('cameraEnabled', $input) ? (bool) $input['cameraEnabled'] : true,
            'microphoneEnabled' => array_key_exists('microphoneEnabled', $input) ? (bool) $input['microphoneEnabled'] : true,
        ]);

        if ($queueRow) {
            $updateQueue = $pdo->prepare("
                UPDATE peer_video_match_queue
                SET queue_mode = ?,
                    status = 'waiting',
                    request_token = ?,
                    current_session_id = NULL,
                    joined_space_id = NULL,
                    enqueued_at = NOW(),
                    last_heartbeat_at = NOW(),
                    expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                    matched_at = NULL,
                    cancelled_at = NULL,
                    metadata_json = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $updateQueue->execute([
                $queueMode,
                $requestToken,
                $ttlSeconds,
                $queueMetadata,
                $userId,
            ]);
        } else {
            $insertQueue = $pdo->prepare("
                INSERT INTO peer_video_match_queue (
                    user_id,
                    queue_mode,
                    status,
                    request_token,
                    current_session_id,
                    joined_space_id,
                    enqueued_at,
                    last_heartbeat_at,
                    expires_at,
                    matched_at,
                    cancelled_at,
                    metadata_json,
                    created_at,
                    updated_at
                ) VALUES (?, ?, 'waiting', ?, NULL, NULL, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), NULL, NULL, ?, NOW(), NOW())
            ");
            $insertQueue->execute([
                $userId,
                $queueMode,
                $requestToken,
                $ttlSeconds,
                $queueMetadata,
            ]);
        }

        $candidateQueue = video_find_candidate_queue($pdo, $userId);

        if ($candidateQueue) {
            $candidateSession = video_find_open_session_for_user($pdo, (int) $candidateQueue['user_id'], true);
            if (!$candidateSession) {
                $spaceCreate = $pdo->prepare("
                    INSERT INTO peer_spaces (
                        space_type,
                        created_by_user_id,
                        title,
                        status,
                        max_members,
                        activated_at,
                        metadata_json,
                        created_at,
                        updated_at
                    ) VALUES ('voice_room', ?, ?, 'active', 2, NOW(), ?, NOW(), NOW())
                ");
                $spaceCreate->execute([
                    $userId,
                    sprintf('Video Match %s', $requestToken),
                    video_json_encode([
                        'source' => 'random_match',
                        'queueMode' => $queueMode,
                    ]),
                ]);
                $spaceId = (int) $pdo->lastInsertId();

                $memberInsert = $pdo->prepare("
                    INSERT INTO peer_space_members (
                        space_id,
                        user_id,
                        member_role,
                        membership_status,
                        invited_by_user_id,
                        responded_at,
                        joined_at,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, 'accepted', ?, NOW(), NOW(), NOW(), NOW())
                ");
                $memberInsert->execute([$spaceId, $userId, 'owner', $userId]);
                $memberInsert->execute([$spaceId, (int) $candidateQueue['user_id'], 'member', $userId]);

                $roomId = video_generate_room_id($spaceId);
                $sessionInsert = $pdo->prepare("
                    INSERT INTO peer_video_sessions (
                        space_id,
                        room_id,
                        queue_mode,
                        user_one_id,
                        user_two_id,
                        matched_by,
                        status,
                        matched_at,
                        started_at,
                        ended_at,
                        last_activity_at,
                        ended_reason,
                        metadata_json,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, ?, ?, 'system', 'matched', NOW(), NULL, NULL, NOW(), NULL, ?, NOW(), NOW())
                ");
                $sessionInsert->execute([
                    $spaceId,
                    $roomId,
                    $queueMode,
                    $userId,
                    (int) $candidateQueue['user_id'],
                    video_json_encode([
                        'matchedFromQueue' => true,
                    ]),
                ]);
                $sessionId = (int) $pdo->lastInsertId();

                $queueMatchUpdate = $pdo->prepare("
                    UPDATE peer_video_match_queue
                    SET status = 'matched',
                        current_session_id = ?,
                        joined_space_id = ?,
                        matched_at = NOW(),
                        expires_at = NULL,
                        updated_at = NOW()
                    WHERE user_id IN (?, ?)
                ");
                $queueMatchUpdate->execute([
                    $sessionId,
                    $spaceId,
                    $userId,
                    (int) $candidateQueue['user_id'],
                ]);

                video_log_event($pdo, $sessionId, null, 'matched', [
                    'spaceId' => $spaceId,
                    'userIds' => [$userId, (int) $candidateQueue['user_id']],
                    'queueMode' => $queueMode,
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return video_fetch_state($pdo, $userId, false);
}

function video_cancel_queue(PDO $pdo, int $userId): array
{
    video_cleanup_stale_queue($pdo);

    $pdo->beginTransaction();
    try {
        $queueRow = video_find_queue_row($pdo, $userId, true);
        if (!$queueRow || (string) $queueRow['status'] !== 'waiting') {
            $pdo->commit();
            return video_fetch_state($pdo, $userId, false);
        }

        $stmt = $pdo->prepare("
            UPDATE peer_video_match_queue
            SET status = 'cancelled',
                current_session_id = NULL,
                joined_space_id = NULL,
                cancelled_at = NOW(),
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return video_fetch_state($pdo, $userId, false);
}

function video_get_room(PDO $pdo, int $userId): array
{
    video_cleanup_stale_queue($pdo);

    $pdo->beginTransaction();
    try {
        $session = video_find_open_session_for_user($pdo, $userId, true);
        if (!$session) {
            $pdo->commit();
            video_json_response(['ok' => false, 'message' => 'No active video session found.'], 404);
        }

        $update = $pdo->prepare("
            UPDATE peer_video_sessions
            SET status = CASE WHEN status = 'matched' THEN 'connecting' ELSE status END,
                started_at = COALESCE(started_at, NOW()),
                last_activity_at = NOW(),
                updated_at = NOW()
            WHERE session_id = ?
        ");
        $update->execute([(int) $session['session_id']]);

        video_log_event($pdo, (int) $session['session_id'], $userId, 'room_opened', [
            'roomId' => (string) $session['room_id'],
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return video_fetch_state($pdo, $userId, false);
}

function video_leave_session(PDO $pdo, int $userId, string $reason = 'user_left'): array
{
    video_cleanup_stale_queue($pdo);

    $pdo->beginTransaction();
    try {
        $session = video_find_open_session_for_user($pdo, $userId, true);
        if (!$session) {
            $pdo->commit();
            return video_fetch_state($pdo, $userId, false);
        }

        $spaceId = (int) $session['space_id'];
        $sessionId = (int) $session['session_id'];

        $sessionUpdate = $pdo->prepare("
            UPDATE peer_video_sessions
            SET status = 'ended',
                ended_at = NOW(),
                last_activity_at = NOW(),
                ended_reason = ?,
                updated_at = NOW()
            WHERE session_id = ?
        ");
        $sessionUpdate->execute([$reason, $sessionId]);

        $spaceUpdate = $pdo->prepare("
            UPDATE peer_spaces
            SET status = 'completed',
                ended_at = NOW(),
                updated_at = NOW()
            WHERE space_id = ?
        ");
        $spaceUpdate->execute([$spaceId]);

        $memberUpdate = $pdo->prepare("
            UPDATE peer_space_members
            SET membership_status = 'left',
                left_at = NOW(),
                updated_at = NOW()
            WHERE space_id = ?
              AND membership_status = 'accepted'
        ");
        $memberUpdate->execute([$spaceId]);

        $queueReset = $pdo->prepare("
            UPDATE peer_video_match_queue
            SET status = 'idle',
                current_session_id = NULL,
                joined_space_id = NULL,
                updated_at = NOW()
            WHERE user_id IN (?, ?)
        ");
        $queueReset->execute([
            (int) $session['user_one_id'],
            (int) $session['user_two_id'],
        ]);

        video_log_event($pdo, $sessionId, $userId, 'left', ['reason' => $reason]);
        video_log_event($pdo, $sessionId, $userId, 'ended', ['reason' => $reason]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return video_fetch_state($pdo, $userId, false);
}

function video_decode_json_array($value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function video_fail(string $message, int $status = 400): void
{
    throw new VideoCallException($message, $status);
}

function video_app_url(): string
{
    global $config;
    $base = trim((string) ($config['app_url'] ?? ''));
    if ($base !== '') {
        return rtrim($base, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8001');
    return $scheme . '://' . $host;
}

function video_call_room_page_url(string $roomId): string
{
    return video_app_url() . '/Academic-Practice/video_call/room.php?roomID=' . rawurlencode($roomId);
}

function video_call_visibility(array $metadata): string
{
    $visibility = strtolower(trim((string) ($metadata['visibility'] ?? 'public')));
    return $visibility === 'private' ? 'private' : 'public';
}

function video_call_default_capacity(): int
{
    return 6;
}

function video_call_find_space_by_room_id(PDO $pdo, string $roomId, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT
            s.*,
            owner.username AS owner_username
        FROM peer_spaces s
        JOIN users owner ON owner.user_id = s.created_by_user_id
        WHERE s.space_type = 'voice_room'
          AND JSON_UNQUOTE(JSON_EXTRACT(s.metadata_json, '$.source')) = 'video_call'
          AND JSON_UNQUOTE(JSON_EXTRACT(s.metadata_json, '$.roomId')) = ?
        ORDER BY s.space_id DESC
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

function video_call_load_members(PDO $pdo, int $spaceId, bool $forUpdate = false): array
{
    $sql = "
        SELECT
            m.*,
            u.username
        FROM peer_space_members m
        JOIN users u ON u.user_id = m.user_id
        WHERE m.space_id = ?
        ORDER BY
            CASE WHEN m.member_role = 'owner' THEN 0 ELSE 1 END,
            m.membership_id ASC
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$spaceId]);
    return $stmt->fetchAll() ?: [];
}

function video_call_count_active_members(array $members): int
{
    $count = 0;
    foreach ($members as $member) {
        if ((string) ($member['membership_status'] ?? '') === 'accepted' && empty($member['left_at'])) {
            $count += 1;
        }
    }
    return $count;
}

function video_call_find_member(array $members, int $userId): ?array
{
    foreach ($members as $member) {
        if ((int) ($member['user_id'] ?? 0) === $userId) {
            return $member;
        }
    }
    return null;
}

function video_call_find_user_by_username(PDO $pdo, string $username, int $excludeUserId = 0): ?array
{
    $trimmed = trim($username);
    if ($trimmed === '') {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT user_id, username
        FROM users
        WHERE LOWER(username) = LOWER(?)
          AND user_id <> ?
        LIMIT 1
    ");
    $stmt->execute([$trimmed, $excludeUserId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function video_call_ensure_direct_conversation(PDO $pdo, int $currentUserId, int $targetUserId): int
{
    $existingStmt = $pdo->prepare("
        SELECT c.conversation_id
        FROM chat_conversations c
        JOIN chat_conversation_members me
            ON me.conversation_id = c.conversation_id
           AND me.user_id = ?
        JOIN chat_conversation_members other
            ON other.conversation_id = c.conversation_id
           AND other.user_id = ?
        WHERE c.conversation_type = 'direct'
          AND c.status = 'active'
          AND (
            SELECT COUNT(*)
            FROM chat_conversation_members cm
            WHERE cm.conversation_id = c.conversation_id
          ) = 2
        LIMIT 1
    ");
    $existingStmt->execute([$currentUserId, $targetUserId]);
    $existingId = (int) ($existingStmt->fetchColumn() ?: 0);
    if ($existingId > 0) {
        return $existingId;
    }

    $insertConversation = $pdo->prepare("
        INSERT INTO chat_conversations (conversation_type, title, created_by_user_id, last_message_at, status, created_at, updated_at)
        VALUES ('direct', NULL, ?, NULL, 'active', NOW(), NOW())
    ");
    $insertConversation->execute([$currentUserId]);
    $conversationId = (int) $pdo->lastInsertId();

    $insertMember = $pdo->prepare("
        INSERT INTO chat_conversation_members (conversation_id, user_id, member_role, joined_at, created_at, updated_at)
        VALUES (?, ?, ?, NOW(), NOW(), NOW())
    ");
    $insertMember->execute([$conversationId, $currentUserId, 'owner']);
    $insertMember->execute([$conversationId, $targetUserId, 'member']);

    return $conversationId;
}

function video_call_send_invite_message(PDO $pdo, array $sender, array $invitee, array $room): int
{
    $conversationId = video_call_ensure_direct_conversation($pdo, (int) $sender['user_id'], (int) $invitee['user_id']);
    $visibilityLabel = video_call_visibility($room['metadata']) === 'private' ? 'Private' : 'Public';
    $shareUrl = trim((string) ($room['shareUrl'] ?? ''));
    if ($shareUrl === '') {
        $shareUrl = video_call_room_page_url((string) ($room['roomId'] ?? ''));
    }
    $message = implode("\n", [
        sprintf('Video call invite from @%s', (string) $sender['username']),
        'Topic: ' . (string) $room['topic'],
        'Type: ' . $visibilityLabel,
        'Room URL: ' . $shareUrl,
    ]);

    $insertMessage = $pdo->prepare("
        INSERT INTO chat_messages (conversation_id, user_id, content_text, status, created_at, updated_at)
        VALUES (?, ?, ?, 'active', NOW(), NOW())
    ");
    $insertMessage->execute([$conversationId, (int) $sender['user_id'], $message]);
    $messageId = (int) $pdo->lastInsertId();

    $updateConversation = $pdo->prepare("
        UPDATE chat_conversations
        SET last_message_at = NOW(), updated_at = NOW()
        WHERE conversation_id = ?
    ");
    $updateConversation->execute([$conversationId]);

    $updateRead = $pdo->prepare("
        UPDATE chat_conversation_members
        SET last_read_at = CASE WHEN user_id = ? THEN NOW() ELSE last_read_at END,
            last_read_message_id = CASE WHEN user_id = ? THEN ? ELSE last_read_message_id END,
            updated_at = NOW()
        WHERE conversation_id = ?
    ");
    $updateRead->execute([(int) $sender['user_id'], (int) $sender['user_id'], $messageId, $conversationId]);

    return $conversationId;
}

function video_call_build_room_payload(array $space, array $members, int $currentUserId): array
{
    $metadata = video_decode_json_array($space['metadata_json'] ?? null);
    $roomId = (string) ($metadata['roomId'] ?? '');
    $activeMembers = array_values(array_filter($members, static function (array $member): bool {
        return (string) ($member['membership_status'] ?? '') === 'accepted' && empty($member['left_at']);
    }));
    $pendingMembers = array_values(array_filter($members, static function (array $member): bool {
        return (string) ($member['membership_status'] ?? '') === 'pending' && empty($member['left_at']);
    }));
    $currentMember = video_call_find_member($members, $currentUserId);
    $ownerMember = null;
    foreach ($members as $member) {
        if ((string) ($member['member_role'] ?? '') === 'owner') {
            $ownerMember = $member;
            break;
        }
    }

    return [
        'spaceId' => (int) $space['space_id'],
        'roomId' => $roomId,
        'title' => (string) ($space['title'] ?? ''),
        'topic' => (string) ($metadata['topic'] ?? $space['title'] ?? 'Video Call'),
        'visibility' => video_call_visibility($metadata),
        'status' => (string) ($space['status'] ?? 'active'),
        'memberCount' => count($activeMembers),
        'capacity' => (int) ($space['max_members'] ?? video_call_default_capacity()),
        'isFull' => count($activeMembers) >= (int) ($space['max_members'] ?? video_call_default_capacity()),
        'roomPageUrl' => './room.php?roomID=' . rawurlencode($roomId),
        'shareUrl' => video_call_room_page_url($roomId),
        'invitedUsername' => (string) ($metadata['invitedUsername'] ?? ''),
        'owner' => [
            'userId' => (int) ($ownerMember['user_id'] ?? $space['created_by_user_id'] ?? 0),
            'username' => (string) ($ownerMember['username'] ?? $space['owner_username'] ?? ''),
            'displayName' => video_format_display_name((string) ($ownerMember['username'] ?? $space['owner_username'] ?? '')),
        ],
        'currentUser' => [
            'isOwner' => (int) ($space['created_by_user_id'] ?? 0) === $currentUserId,
            'membershipStatus' => (string) ($currentMember['membership_status'] ?? ''),
            'canManage' => (int) ($space['created_by_user_id'] ?? 0) === $currentUserId,
        ],
        'members' => array_map(static function (array $member): array {
            return [
                'userId' => (int) ($member['user_id'] ?? 0),
                'username' => (string) ($member['username'] ?? ''),
                'displayName' => video_format_display_name((string) ($member['username'] ?? '')),
                'role' => (string) ($member['member_role'] ?? 'member'),
                'status' => (string) ($member['membership_status'] ?? ''),
            ];
        }, $activeMembers),
        'pendingMembers' => array_map(static function (array $member): array {
            return [
                'userId' => (int) ($member['user_id'] ?? 0),
                'username' => (string) ($member['username'] ?? ''),
                'displayName' => video_format_display_name((string) ($member['username'] ?? '')),
                'role' => (string) ($member['member_role'] ?? 'member'),
                'status' => (string) ($member['membership_status'] ?? ''),
            ];
        }, $pendingMembers),
        'metadata' => $metadata,
    ];
}

function video_call_sync_session(PDO $pdo, array $space, array $members): void
{
    $activeMembers = array_values(array_filter($members, static function (array $member): bool {
        return (string) ($member['membership_status'] ?? '') === 'accepted' && empty($member['left_at']);
    }));

    $sessionStmt = $pdo->prepare("
        SELECT *
        FROM peer_video_sessions
        WHERE space_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $sessionStmt->execute([(int) $space['space_id']]);
    $sessionRow = $sessionStmt->fetch() ?: null;

    if (count($activeMembers) < 2) {
        if ($sessionRow && in_array((string) ($sessionRow['status'] ?? ''), video_session_statuses_open(), true)) {
            $endStmt = $pdo->prepare("
                UPDATE peer_video_sessions
                SET status = 'ended',
                    ended_at = NOW(),
                    ended_reason = 'user_left',
                    last_activity_at = NOW(),
                    updated_at = NOW()
                WHERE session_id = ?
            ");
            $endStmt->execute([(int) $sessionRow['session_id']]);
        }
        return;
    }

    $owner = $activeMembers[0];
    $partner = $activeMembers[1];
    $metadata = video_decode_json_array($space['metadata_json'] ?? null);
    $sessionMetadata = video_json_encode([
        'matchedFromQueue' => false,
        'source' => 'video_call',
        'topic' => (string) ($metadata['topic'] ?? $space['title'] ?? ''),
        'visibility' => video_call_visibility($metadata),
        'activeUserIds' => array_values(array_map(static fn(array $member): int => (int) $member['user_id'], $activeMembers)),
        'activeMemberCount' => count($activeMembers),
    ]);
    $roomId = (string) ($metadata['roomId'] ?? '');

    if ($sessionRow) {
        $updateStmt = $pdo->prepare("
            UPDATE peer_video_sessions
            SET room_id = ?,
                queue_mode = ?,
                user_one_id = ?,
                user_two_id = ?,
                matched_by = 'manual',
                status = 'connecting',
                matched_at = COALESCE(matched_at, NOW()),
                started_at = COALESCE(started_at, NOW()),
                ended_at = NULL,
                ended_reason = NULL,
                last_activity_at = NOW(),
                metadata_json = ?,
                updated_at = NOW()
            WHERE session_id = ?
        ");
        $updateStmt->execute([
            $roomId,
            video_queue_mode(),
            (int) $owner['user_id'],
            (int) $partner['user_id'],
            $sessionMetadata,
            (int) $sessionRow['session_id'],
        ]);
        return;
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO peer_video_sessions (
            space_id,
            room_id,
            queue_mode,
            user_one_id,
            user_two_id,
            matched_by,
            status,
            matched_at,
            started_at,
            ended_at,
            last_activity_at,
            ended_reason,
            metadata_json,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, 'manual', 'connecting', NOW(), NOW(), NULL, NOW(), NULL, ?, NOW(), NOW())
    ");
    $insertStmt->execute([
        (int) $space['space_id'],
        $roomId,
        video_queue_mode(),
        (int) $owner['user_id'],
        (int) $partner['user_id'],
        $sessionMetadata,
    ]);
}

function video_call_list_rooms(PDO $pdo, int $currentUserId): array
{
    $stmt = $pdo->prepare("
        SELECT
            s.*,
            owner.username AS owner_username
        FROM peer_spaces s
        JOIN users owner ON owner.user_id = s.created_by_user_id
        WHERE s.space_type = 'voice_room'
          AND s.status = 'active'
          AND JSON_UNQUOTE(JSON_EXTRACT(s.metadata_json, '$.source')) = 'video_call'
        ORDER BY s.created_at DESC, s.space_id DESC
        LIMIT 40
    ");
    $stmt->execute();
    $spaces = $stmt->fetchAll() ?: [];

    $rooms = [];
    foreach ($spaces as $space) {
        $members = video_call_load_members($pdo, (int) $space['space_id']);
        $ownerActive = false;
        foreach ($members as $member) {
            if (
                (string) ($member['member_role'] ?? '') === 'owner' &&
                (string) ($member['membership_status'] ?? '') === 'accepted' &&
                empty($member['left_at'])
            ) {
                $ownerActive = true;
                break;
            }
        }

        if (!$ownerActive) {
            $closeStmt = $pdo->prepare("
                UPDATE peer_spaces
                SET status = 'completed',
                    ended_at = COALESCE(ended_at, NOW()),
                    updated_at = NOW()
                WHERE space_id = ?
                  AND status = 'active'
            ");
            $closeStmt->execute([(int) $space['space_id']]);
            continue;
        }

        $payload = video_call_build_room_payload($space, $members, $currentUserId);
        if ($payload['memberCount'] >= $payload['capacity']) {
            continue;
        }
        $rooms[] = $payload;
    }

    return $rooms;
}

function video_call_create_room(PDO $pdo, int $userId, array $input): array
{
    $topic = trim((string) ($input['topic'] ?? ''));
    $visibility = strtolower(trim((string) ($input['visibility'] ?? 'public')));
    $inviteUsername = trim((string) ($input['inviteUsername'] ?? ''));
    $visibility = $visibility === 'private' ? 'private' : 'public';

    if ($topic === '') {
        video_fail('Topic is required.', 422);
    }

    $ownerStmt = $pdo->prepare("SELECT user_id, username FROM users WHERE user_id = ? LIMIT 1");
    $ownerStmt->execute([$userId]);
    $owner = $ownerStmt->fetch();
    if (!$owner) {
        video_fail('User not found.', 404);
    }

    $pdo->beginTransaction();
    try {
        $spaceInsert = $pdo->prepare("
            INSERT INTO peer_spaces (
                space_type,
                created_by_user_id,
                title,
                status,
                max_members,
                activated_at,
                metadata_json,
                created_at,
                updated_at
            ) VALUES ('voice_room', ?, ?, 'active', ?, NOW(), ?, NOW(), NOW())
        ");
        $spaceInsert->execute([
            $userId,
            $topic,
            video_call_default_capacity(),
            video_json_encode([
                'source' => 'video_call',
                'topic' => $topic,
                'visibility' => $visibility,
            ]),
        ]);
        $spaceId = (int) $pdo->lastInsertId();
        $roomId = video_generate_room_id($spaceId);

        $invitee = null;
        if ($inviteUsername !== '') {
            $invitee = video_call_find_user_by_username($pdo, $inviteUsername, $userId);
            if (!$invitee) {
                video_fail('Invite username not found.', 404);
            }
        }

        $metadata = [
            'source' => 'video_call',
            'topic' => $topic,
            'visibility' => $visibility,
            'roomId' => $roomId,
            'invitedUsername' => $invitee ? (string) $invitee['username'] : '',
        ];
        $updateSpace = $pdo->prepare("
            UPDATE peer_spaces
            SET metadata_json = ?, updated_at = NOW()
            WHERE space_id = ?
        ");
        $updateSpace->execute([video_json_encode($metadata), $spaceId]);

        $memberInsert = $pdo->prepare("
            INSERT INTO peer_space_members (
                space_id,
                user_id,
                member_role,
                membership_status,
                invited_by_user_id,
                responded_at,
                joined_at,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $memberInsert->execute([$spaceId, $userId, 'owner', 'accepted', $userId, video_now(), video_now()]);

        if ($invitee) {
            $memberInsert->execute([$spaceId, (int) $invitee['user_id'], 'member', 'pending', $userId, null, null]);
        }

        $space = video_call_find_space_by_room_id($pdo, $roomId, true);
        $members = video_call_load_members($pdo, $spaceId, true);
        $room = video_call_build_room_payload($space ?: [
            'space_id' => $spaceId,
            'created_by_user_id' => $userId,
            'title' => $topic,
            'status' => 'active',
            'max_members' => video_call_default_capacity(),
            'metadata_json' => video_json_encode($metadata),
            'owner_username' => (string) $owner['username'],
        ], $members, $userId);

        if ($invitee) {
            video_call_send_invite_message($pdo, $owner, $invitee, $room);
        }

        $pdo->commit();
        return $room;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function video_call_access_room(PDO $pdo, int $userId, string $roomId): array
{
    $roomId = sanitizeRoomValue($roomId);
    if ($roomId === '') {
        video_fail('Room ID is required.', 422);
    }

    $pdo->beginTransaction();
    try {
        $space = video_call_find_space_by_room_id($pdo, $roomId, true);
        if (!$space || (string) ($space['status'] ?? '') !== 'active') {
            video_fail('Room not found or already closed.', 404);
        }

        $members = video_call_load_members($pdo, (int) $space['space_id'], true);
        $metadata = video_decode_json_array($space['metadata_json'] ?? null);
        $visibility = video_call_visibility($metadata);
        $currentMember = video_call_find_member($members, $userId);
        $activeCount = video_call_count_active_members($members);
        $capacity = (int) ($space['max_members'] ?? video_call_default_capacity());

        if ($currentMember) {
            if ((string) $currentMember['membership_status'] !== 'accepted' || !empty($currentMember['left_at'])) {
                $acceptStmt = $pdo->prepare("
                    UPDATE peer_space_members
                    SET membership_status = 'accepted',
                        responded_at = NOW(),
                        joined_at = NOW(),
                        left_at = NULL,
                        updated_at = NOW()
                    WHERE membership_id = ?
                ");
                $acceptStmt->execute([(int) $currentMember['membership_id']]);
            }
        } else {
            if ($activeCount >= $capacity) {
                video_fail('This room is already full.', 409);
            }
            if ($visibility !== 'public') {
                video_fail('This private room requires an invite.', 403);
            }

            $insertMember = $pdo->prepare("
                INSERT INTO peer_space_members (
                    space_id,
                    user_id,
                    member_role,
                    membership_status,
                    invited_by_user_id,
                    responded_at,
                    joined_at,
                    created_at,
                    updated_at
                ) VALUES (?, ?, 'member', 'accepted', ?, NOW(), NOW(), NOW(), NOW())
            ");
            $insertMember->execute([(int) $space['space_id'], $userId, (int) $space['created_by_user_id']]);
        }

        $members = video_call_load_members($pdo, (int) $space['space_id'], true);
        video_call_sync_session($pdo, $space, $members);
        $payload = video_call_build_room_payload($space, $members, $userId);

        $pdo->commit();
        return $payload;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function video_call_leave_room(PDO $pdo, int $userId, string $roomId, string $reason = 'user_left'): array
{
    $roomId = sanitizeRoomValue($roomId);
    if ($roomId === '') {
        return ['closed' => true];
    }

    $pdo->beginTransaction();
    try {
        $space = video_call_find_space_by_room_id($pdo, $roomId, true);
        if (!$space) {
            $pdo->commit();
            return ['closed' => true];
        }

        $memberStmt = $pdo->prepare("
            SELECT *
            FROM peer_space_members
            WHERE space_id = ?
              AND user_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $memberStmt->execute([(int) $space['space_id'], $userId]);
        $member = $memberStmt->fetch() ?: null;

        if ($member) {
            $leaveStmt = $pdo->prepare("
                UPDATE peer_space_members
                SET membership_status = 'left',
                    left_at = NOW(),
                    updated_at = NOW()
                WHERE membership_id = ?
            ");
            $leaveStmt->execute([(int) $member['membership_id']]);
        }

        if ((int) $space['created_by_user_id'] === $userId) {
            $closeMembersStmt = $pdo->prepare("
                UPDATE peer_space_members
                SET membership_status = CASE
                        WHEN membership_status = 'left' THEN membership_status
                        ELSE 'left'
                    END,
                    left_at = COALESCE(left_at, NOW()),
                    updated_at = NOW()
                WHERE space_id = ?
            ");
            $closeMembersStmt->execute([(int) $space['space_id']]);

            $spaceStmt = $pdo->prepare("
                UPDATE peer_spaces
                SET status = 'completed',
                    ended_at = NOW(),
                    updated_at = NOW()
                WHERE space_id = ?
            ");
            $spaceStmt->execute([(int) $space['space_id']]);
        }

        $members = video_call_load_members($pdo, (int) $space['space_id'], true);
        video_call_sync_session($pdo, $space, $members);
        $payload = video_call_build_room_payload($space, $members, $userId);

        $pdo->commit();
        return [
            'closed' => (int) $space['created_by_user_id'] === $userId,
            'reason' => $reason,
            'room' => $payload,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function sanitizeRoomValue($value): string
{
    $sanitized = preg_replace('/[^A-Za-z0-9_]/', '_', (string) $value);
    $sanitized = preg_replace('/_+/', '_', (string) $sanitized);
    return trim((string) $sanitized, '_');
}

final class VideoCallException extends RuntimeException
{
    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message, $status);
    }
}

function video_call_manage_room(PDO $pdo, int $userId, string $roomId, array $input): array
{
    $roomId = sanitizeRoomValue($roomId);
    if ($roomId === '') {
        video_fail('Room ID is required.', 422);
    }

    $action = strtolower(trim((string) ($input['action'] ?? '')));
    if (!in_array($action, ['update_visibility', 'invite_member'], true)) {
        video_fail('Unsupported room action.', 422);
    }

    $pdo->beginTransaction();
    try {
        $space = video_call_find_space_by_room_id($pdo, $roomId, true);
        if (!$space || (string) ($space['status'] ?? '') !== 'active') {
            video_fail('Room not found or already closed.', 404);
        }
        if ((int) ($space['created_by_user_id'] ?? 0) !== $userId) {
            video_fail('Only the room owner can manage this room.', 403);
        }

        $members = video_call_load_members($pdo, (int) $space['space_id'], true);
        $metadata = video_decode_json_array($space['metadata_json'] ?? null);
        $ownerStmt = $pdo->prepare("SELECT user_id, username FROM users WHERE user_id = ? LIMIT 1");
        $ownerStmt->execute([$userId]);
        $owner = $ownerStmt->fetch();
        if (!$owner) {
            video_fail('User not found.', 404);
        }

        if ($action === 'update_visibility') {
            $visibility = strtolower(trim((string) ($input['visibility'] ?? 'public')));
            $visibility = $visibility === 'private' ? 'private' : 'public';
            $metadata['visibility'] = $visibility;

            $updateSpace = $pdo->prepare("
                UPDATE peer_spaces
                SET metadata_json = ?, updated_at = NOW()
                WHERE space_id = ?
            ");
            $updateSpace->execute([video_json_encode($metadata), (int) $space['space_id']]);
        }

        if ($action === 'invite_member') {
            $inviteUsername = trim((string) ($input['inviteUsername'] ?? ''));
            if ($inviteUsername === '') {
                video_fail('Invite username is required.', 422);
            }

            $invitee = video_call_find_user_by_username($pdo, $inviteUsername, $userId);
            if (!$invitee) {
                video_fail('Invite username not found.', 404);
            }

            $existingMember = video_call_find_member($members, (int) $invitee['user_id']);
            if ($existingMember && empty($existingMember['left_at']) && in_array((string) ($existingMember['membership_status'] ?? ''), ['accepted', 'pending'], true)) {
                video_fail('This user is already in the room or has a pending invite.', 409);
            }

            $activeCount = video_call_count_active_members($members);
            $capacity = max((int) ($space['max_members'] ?? video_call_default_capacity()), video_call_default_capacity());
            if ($activeCount >= $capacity) {
                $capacity = $activeCount + 1;
            }

            $updateCapacity = $pdo->prepare("
                UPDATE peer_spaces
                SET max_members = ?, updated_at = NOW()
                WHERE space_id = ?
            ");
            $updateCapacity->execute([$capacity, (int) $space['space_id']]);

            if ($existingMember) {
                $updateMember = $pdo->prepare("
                    UPDATE peer_space_members
                    SET membership_status = 'pending',
                        invited_by_user_id = ?,
                        responded_at = NULL,
                        joined_at = NULL,
                        left_at = NULL,
                        updated_at = NOW()
                    WHERE membership_id = ?
                ");
                $updateMember->execute([$userId, (int) $existingMember['membership_id']]);
            } else {
                $insertMember = $pdo->prepare("
                    INSERT INTO peer_space_members (
                        space_id,
                        user_id,
                        member_role,
                        membership_status,
                        invited_by_user_id,
                        responded_at,
                        joined_at,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, 'member', 'pending', ?, NULL, NULL, NOW(), NOW())
                ");
                $insertMember->execute([(int) $space['space_id'], (int) $invitee['user_id'], $userId]);
            }

            $metadata['invitedUsername'] = (string) $invitee['username'];
            $updateSpace = $pdo->prepare("
                UPDATE peer_spaces
                SET metadata_json = ?, updated_at = NOW()
                WHERE space_id = ?
            ");
            $updateSpace->execute([video_json_encode($metadata), (int) $space['space_id']]);
        }

        $space = video_call_find_space_by_room_id($pdo, $roomId, true);
        $members = video_call_load_members($pdo, (int) $space['space_id'], true);
        video_call_sync_session($pdo, $space, $members);
        $room = video_call_build_room_payload($space, $members, $userId);

        if ($action === 'invite_member') {
            $invitee = video_call_find_user_by_username($pdo, trim((string) ($input['inviteUsername'] ?? '')), $userId);
            if ($invitee) {
                video_call_send_invite_message($pdo, $owner, $invitee, $room);
            }
        }

        $pdo->commit();
        return $room;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
