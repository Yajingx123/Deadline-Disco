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
        'roomPageUrl' => './zego-call.php?roomID=' . rawurlencode((string) $session['roomId']),
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
