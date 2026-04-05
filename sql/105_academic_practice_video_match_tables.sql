USE acadbeat;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS peer_spaces (
    space_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    space_type VARCHAR(30) NOT NULL,
    created_by_user_id BIGINT NOT NULL,
    title VARCHAR(120) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    max_members INT NOT NULL DEFAULT 2,
    activated_at DATETIME NULL,
    ended_at DATETIME NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_spaces_creator
        FOREIGN KEY (created_by_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_peer_spaces_type
        CHECK (space_type IN ('voice_room')),

    CONSTRAINT chk_peer_spaces_status
        CHECK (status IN ('pending', 'active', 'cancelled', 'completed', 'expired')),

    CONSTRAINT chk_peer_spaces_max_members
        CHECK (max_members >= 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS peer_space_members (
    membership_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    space_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    member_role VARCHAR(20) NOT NULL DEFAULT 'member',
    membership_status VARCHAR(20) NOT NULL DEFAULT 'accepted',
    invited_by_user_id BIGINT NULL,
    responded_at DATETIME NULL,
    joined_at DATETIME NULL,
    left_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_space_members_space
        FOREIGN KEY (space_id)
        REFERENCES peer_spaces(space_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_space_members_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_space_members_inviter
        FOREIGN KEY (invited_by_user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT uq_peer_space_member_unique
        UNIQUE (space_id, user_id),

    CONSTRAINT chk_peer_space_members_role
        CHECK (member_role IN ('owner', 'member', 'guest')),

    CONSTRAINT chk_peer_space_members_status
        CHECK (membership_status IN ('pending', 'accepted', 'left', 'removed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS peer_video_match_settings (
    user_id BIGINT PRIMARY KEY,
    feature_enabled TINYINT(1) NOT NULL DEFAULT 1,
    auto_match_enabled TINYINT(1) NOT NULL DEFAULT 1,
    preferred_mode VARCHAR(30) NOT NULL DEFAULT 'random_1v1',
    camera_enabled TINYINT(1) NOT NULL DEFAULT 1,
    microphone_enabled TINYINT(1) NOT NULL DEFAULT 1,
    blocked_until DATETIME NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_video_match_settings_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT chk_peer_video_match_settings_mode
        CHECK (preferred_mode IN ('random_1v1')),

    CONSTRAINT chk_peer_video_match_settings_feature_enabled
        CHECK (feature_enabled IN (0, 1)),

    CONSTRAINT chk_peer_video_match_settings_auto_match_enabled
        CHECK (auto_match_enabled IN (0, 1)),

    CONSTRAINT chk_peer_video_match_settings_camera_enabled
        CHECK (camera_enabled IN (0, 1)),

    CONSTRAINT chk_peer_video_match_settings_microphone_enabled
        CHECK (microphone_enabled IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS peer_video_sessions (
    session_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    space_id BIGINT NOT NULL,
    room_id VARCHAR(128) NOT NULL,
    queue_mode VARCHAR(30) NOT NULL DEFAULT 'random_1v1',
    user_one_id BIGINT NOT NULL,
    user_two_id BIGINT NOT NULL,
    matched_by VARCHAR(20) NOT NULL DEFAULT 'system',
    status VARCHAR(20) NOT NULL DEFAULT 'matched',
    matched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    last_activity_at DATETIME NULL,
    ended_reason VARCHAR(30) NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_video_sessions_space
        FOREIGN KEY (space_id)
        REFERENCES peer_spaces(space_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_video_sessions_user_one
        FOREIGN KEY (user_one_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_video_sessions_user_two
        FOREIGN KEY (user_two_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_peer_video_sessions_space UNIQUE (space_id),
    CONSTRAINT uq_peer_video_sessions_room UNIQUE (room_id),

    CONSTRAINT chk_peer_video_sessions_mode
        CHECK (queue_mode IN ('random_1v1')),

    CONSTRAINT chk_peer_video_sessions_matched_by
        CHECK (matched_by IN ('system', 'manual')),

    CONSTRAINT chk_peer_video_sessions_status
        CHECK (status IN ('matched', 'connecting', 'active', 'ended', 'cancelled', 'expired')),

    CONSTRAINT chk_peer_video_sessions_end_reason
        CHECK (ended_reason IS NULL OR ended_reason IN ('user_left', 'cancelled', 'timeout', 'system'))

    -- NOTE:
    -- MySQL 8.0 can reject CHECK constraints on columns that are also used
    -- in FK referential actions in some server variants/configurations.
    -- Keep this rule enforced at application level for compatibility.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS peer_video_match_queue (
    queue_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    queue_mode VARCHAR(30) NOT NULL DEFAULT 'random_1v1',
    status VARCHAR(20) NOT NULL DEFAULT 'waiting',
    request_token CHAR(36) NOT NULL,
    current_session_id BIGINT NULL,
    joined_space_id BIGINT NULL,
    enqueued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_heartbeat_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    matched_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_video_match_queue_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_video_match_queue_session
        FOREIGN KEY (current_session_id)
        REFERENCES peer_video_sessions(session_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_video_match_queue_space
        FOREIGN KEY (joined_space_id)
        REFERENCES peer_spaces(space_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT uq_peer_video_match_queue_user UNIQUE (user_id),
    CONSTRAINT uq_peer_video_match_queue_request_token UNIQUE (request_token),

    CONSTRAINT chk_peer_video_match_queue_mode
        CHECK (queue_mode IN ('random_1v1')),

    CONSTRAINT chk_peer_video_match_queue_status
        CHECK (status IN ('waiting', 'matched', 'cancelled', 'expired', 'idle'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS peer_video_session_events (
    event_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    session_id BIGINT NOT NULL,
    actor_user_id BIGINT NULL,
    event_type VARCHAR(30) NOT NULL,
    payload_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_video_session_events_session
        FOREIGN KEY (session_id)
        REFERENCES peer_video_sessions(session_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_video_session_events_actor
        FOREIGN KEY (actor_user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT chk_peer_video_session_events_type
        CHECK (event_type IN (
            'queue_joined',
            'queue_cancelled',
            'matched',
            'room_opened',
            'joined',
            'left',
            'heartbeat',
            'ended',
            'expired'
        ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotent indexes: older MySQL rejects "CREATE INDEX IF NOT EXISTS" (1064). Use schema check + PREPARE.
SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'peer_spaces' AND index_name = 'idx_peer_spaces_status') = 0,
    'CREATE INDEX idx_peer_spaces_status ON peer_spaces (space_type, status)',
    'SELECT 1'
) INTO @__peer_idx_sql;
PREPARE __peer_idx_stmt FROM @__peer_idx_sql;
EXECUTE __peer_idx_stmt;
DEALLOCATE PREPARE __peer_idx_stmt;

SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'peer_space_members' AND index_name = 'idx_peer_space_members_user') = 0,
    'CREATE INDEX idx_peer_space_members_user ON peer_space_members (user_id, membership_status)',
    'SELECT 1'
) INTO @__peer_idx_sql;
PREPARE __peer_idx_stmt FROM @__peer_idx_sql;
EXECUTE __peer_idx_stmt;
DEALLOCATE PREPARE __peer_idx_stmt;

SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'peer_video_sessions' AND index_name = 'idx_peer_video_sessions_status') = 0,
    'CREATE INDEX idx_peer_video_sessions_status ON peer_video_sessions (status, matched_at)',
    'SELECT 1'
) INTO @__peer_idx_sql;
PREPARE __peer_idx_stmt FROM @__peer_idx_sql;
EXECUTE __peer_idx_stmt;
DEALLOCATE PREPARE __peer_idx_stmt;

SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'peer_video_sessions' AND index_name = 'idx_peer_video_sessions_users') = 0,
    'CREATE INDEX idx_peer_video_sessions_users ON peer_video_sessions (user_one_id, user_two_id)',
    'SELECT 1'
) INTO @__peer_idx_sql;
PREPARE __peer_idx_stmt FROM @__peer_idx_sql;
EXECUTE __peer_idx_stmt;
DEALLOCATE PREPARE __peer_idx_stmt;

SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'peer_video_match_queue' AND index_name = 'idx_peer_video_match_queue_status') = 0,
    'CREATE INDEX idx_peer_video_match_queue_status ON peer_video_match_queue (status, queue_mode, last_heartbeat_at)',
    'SELECT 1'
) INTO @__peer_idx_sql;
PREPARE __peer_idx_stmt FROM @__peer_idx_sql;
EXECUTE __peer_idx_stmt;
DEALLOCATE PREPARE __peer_idx_stmt;

SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'peer_video_match_queue' AND index_name = 'idx_peer_video_match_queue_session') = 0,
    'CREATE INDEX idx_peer_video_match_queue_session ON peer_video_match_queue (current_session_id)',
    'SELECT 1'
) INTO @__peer_idx_sql;
PREPARE __peer_idx_stmt FROM @__peer_idx_sql;
EXECUTE __peer_idx_stmt;
DEALLOCATE PREPARE __peer_idx_stmt;

SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'peer_video_session_events' AND index_name = 'idx_peer_video_session_events_session') = 0,
    'CREATE INDEX idx_peer_video_session_events_session ON peer_video_session_events (session_id, created_at)',
    'SELECT 1'
) INTO @__peer_idx_sql;
PREPARE __peer_idx_stmt FROM @__peer_idx_sql;
EXECUTE __peer_idx_stmt;
DEALLOCATE PREPARE __peer_idx_stmt;

SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'peer_video_session_events' AND index_name = 'idx_peer_video_session_events_actor') = 0,
    'CREATE INDEX idx_peer_video_session_events_actor ON peer_video_session_events (actor_user_id, created_at)',
    'SELECT 1'
) INTO @__peer_idx_sql;
PREPARE __peer_idx_stmt FROM @__peer_idx_sql;
EXECUTE __peer_idx_stmt;
DEALLOCATE PREPARE __peer_idx_stmt;
