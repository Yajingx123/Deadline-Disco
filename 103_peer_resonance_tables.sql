USE acadbeat;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS peer_spaces (
    space_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    space_type VARCHAR(30) NOT NULL,
    created_by_user_id BIGINT NOT NULL,
    title VARCHAR(120) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
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
        CHECK (space_type IN ('resonance', 'voice_room', 'study_room')),

    CONSTRAINT chk_peer_spaces_status
        CHECK (status IN ('pending', 'active', 'declined', 'cancelled', 'completed', 'expired')),

    CONSTRAINT chk_peer_spaces_max_members
        CHECK (max_members >= 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS peer_space_members (
    membership_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    space_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    member_role VARCHAR(20) NOT NULL DEFAULT 'member',
    membership_status VARCHAR(20) NOT NULL DEFAULT 'pending',
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
        CHECK (membership_status IN ('pending', 'accepted', 'declined', 'left', 'removed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS peer_space_invites (
    invite_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    space_id BIGINT NOT NULL,
    inviter_user_id BIGINT NOT NULL,
    invitee_user_id BIGINT NOT NULL,
    invite_type VARCHAR(30) NOT NULL DEFAULT 'resonance',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    invite_message VARCHAR(255) NULL,
    expires_at DATETIME NULL,
    responded_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_space_invites_space
        FOREIGN KEY (space_id)
        REFERENCES peer_spaces(space_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_space_invites_inviter
        FOREIGN KEY (inviter_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_space_invites_invitee
        FOREIGN KEY (invitee_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_peer_space_invites_target
        UNIQUE (space_id, invitee_user_id),

    CONSTRAINT chk_peer_space_invites_type
        CHECK (invite_type IN ('resonance', 'voice_room', 'study_room')),

    CONSTRAINT chk_peer_space_invites_status
        CHECK (status IN ('pending', 'accepted', 'declined', 'cancelled', 'expired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS peer_resonance_teams (
    team_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    space_id BIGINT NOT NULL,
    team_name VARCHAR(120) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    started_on DATE NOT NULL,
    last_mutual_checkin_on DATE NULL,
    current_streak_days INT NOT NULL DEFAULT 0,
    longest_streak_days INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_resonance_teams_space
        FOREIGN KEY (space_id)
        REFERENCES peer_spaces(space_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_peer_resonance_teams_space
        UNIQUE (space_id),

    CONSTRAINT chk_peer_resonance_teams_status
        CHECK (status IN ('active', 'paused', 'completed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS peer_resonance_daily_logs (
    log_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    team_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    checkin_date DATE NOT NULL,
    log_status VARCHAR(20) NOT NULL DEFAULT 'committed',
    source VARCHAR(20) NOT NULL DEFAULT 'owner',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_peer_resonance_daily_logs_team
        FOREIGN KEY (team_id)
        REFERENCES peer_resonance_teams(team_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_peer_resonance_daily_logs_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_peer_resonance_daily_unique
        UNIQUE (team_id, user_id, checkin_date),

    CONSTRAINT chk_peer_resonance_daily_status
        CHECK (log_status IN ('committed', 'missed', 'excused'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_peer_spaces_status
    ON peer_spaces(space_type, status);

CREATE INDEX idx_peer_space_members_user
    ON peer_space_members(user_id, membership_status);

CREATE INDEX idx_peer_space_invites_invitee
    ON peer_space_invites(invitee_user_id, status);

CREATE INDEX idx_peer_space_invites_inviter
    ON peer_space_invites(inviter_user_id, status);

CREATE INDEX idx_peer_resonance_daily_logs_team_date
    ON peer_resonance_daily_logs(team_id, checkin_date);
