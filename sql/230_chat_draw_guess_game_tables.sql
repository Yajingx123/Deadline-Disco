-- =========================================
-- Chat Draw & Guess game tables
-- =========================================

CREATE TABLE IF NOT EXISTS chat_draw_guess_games (
    game_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    conversation_id BIGINT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'LOBBY',
    min_players INT NOT NULL DEFAULT 3,
    round_index INT NOT NULL DEFAULT 0,
    current_round_id BIGINT NULL,
    current_drawer_user_id BIGINT NULL,
    current_word_id BIGINT NULL,
    current_word VARCHAR(96) NULL,
    current_text_hint VARCHAR(255) NULL,
    current_second_text_hint VARCHAR(255) NULL,
    current_hint_stage VARCHAR(20) NOT NULL DEFAULT 'none',
    text_hint_revealed_at DATETIME NULL,
    second_text_hint_revealed_at DATETIME NULL,
    correct_guess_count INT NOT NULL DEFAULT 0,
    active_guessers_count INT NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    created_by_user_id BIGINT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_draw_guess_games_conversation
        FOREIGN KEY (conversation_id)
        REFERENCES chat_conversations(conversation_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_draw_guess_games_creator
        FOREIGN KEY (created_by_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_draw_guess_players (
    game_player_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    game_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    join_order INT NOT NULL DEFAULT 1,
    drawer_order INT NOT NULL DEFAULT 1,
    score INT NOT NULL DEFAULT 0,
    is_ready TINYINT(1) NOT NULL DEFAULT 0,
    player_status VARCHAR(20) NOT NULL DEFAULT 'active',
    connection_status VARCHAR(20) NOT NULL DEFAULT 'online',
    has_drawn TINYINT(1) NOT NULL DEFAULT 0,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_draw_guess_players_game
        FOREIGN KEY (game_id)
        REFERENCES chat_draw_guess_games(game_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_draw_guess_players_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT uq_draw_guess_players UNIQUE (game_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_draw_guess_words (
    word_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    word_text VARCHAR(96) NOT NULL,
    text_hint VARCHAR(255) NOT NULL,
    second_text_hint VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_draw_guess_words UNIQUE (word_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_draw_guess_rounds (
    round_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    game_id BIGINT NOT NULL,
    round_index INT NOT NULL,
    drawer_user_id BIGINT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ROUND_START',
    candidate_words_json JSON NULL,
    selected_word_id BIGINT NULL,
    selected_word VARCHAR(96) NULL,
    text_hint VARCHAR(255) NULL,
    second_text_hint VARCHAR(255) NULL,
    selection_deadline_at DATETIME NULL,
    started_at DATETIME NULL,
    ends_at DATETIME NULL,
    text_hint_revealed_at DATETIME NULL,
    second_text_hint_revealed_at DATETIME NULL,
    ended_at DATETIME NULL,
    correct_guess_count INT NOT NULL DEFAULT 0,
    active_guessers_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_draw_guess_rounds_game
        FOREIGN KEY (game_id)
        REFERENCES chat_draw_guess_games(game_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_draw_guess_rounds_drawer
        FOREIGN KEY (drawer_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_draw_guess_strokes (
    stroke_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    round_id BIGINT NOT NULL,
    created_by_user_id BIGINT NOT NULL,
    event_type VARCHAR(20) NOT NULL DEFAULT 'stroke',
    payload_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_draw_guess_strokes_round
        FOREIGN KEY (round_id)
        REFERENCES chat_draw_guess_rounds(round_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_draw_guess_strokes_user
        FOREIGN KEY (created_by_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_draw_guess_guesses (
    guess_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    round_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    guess_text VARCHAR(255) NOT NULL,
    normalized_guess VARCHAR(255) NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    score_awarded INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_draw_guess_guesses_round
        FOREIGN KEY (round_id)
        REFERENCES chat_draw_guess_rounds(round_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_draw_guess_guesses_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
