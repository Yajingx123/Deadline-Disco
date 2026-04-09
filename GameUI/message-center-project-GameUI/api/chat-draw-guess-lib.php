<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const DRAW_GUESS_STATUS_IDLE = 'IDLE';
const DRAW_GUESS_STATUS_LOBBY = 'LOBBY';
const DRAW_GUESS_STATUS_ROUND_START = 'ROUND_START';
const DRAW_GUESS_STATUS_PLAYING = 'PLAYING';
const DRAW_GUESS_STATUS_ROUND_END = 'ROUND_END';
const DRAW_GUESS_STATUS_GAME_END = 'GAME_END';

const DRAW_GUESS_MIN_PLAYERS = 2;
const DRAW_GUESS_MAX_PLAYERS = 6;
const DRAW_GUESS_WORD_PICK_SECONDS = 10;
const DRAW_GUESS_ROUND_SECONDS = 90;
const DRAW_GUESS_ROUND_END_BUFFER_SECONDS = 1;

function draw_guess_now(): DateTimeImmutable {
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
}

function draw_guess_now_sql(): string {
    return draw_guess_now()->format('Y-m-d H:i:s');
}

function draw_guess_datetime(?string $value): ?DateTimeImmutable {
    if (!$value) {
        return null;
    }
    try {
        return new DateTimeImmutable($value, new DateTimeZone('Asia/Shanghai'));
    } catch (Throwable $_error) {
        return null;
    }
}

function draw_guess_json_decode(?string $value, array $fallback = []): array {
    if (!is_string($value) || trim($value) === '') {
        return $fallback;
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function draw_guess_insert_invite_message(PDO $pdo, int $conversationId, int $currentUserId, int $requiredPlayers): void {
    $userStmt = $pdo->prepare("
        SELECT user_id, username, email
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");
    $userStmt->execute([$currentUserId]);
    $authorRow = $userStmt->fetch();
    if (!$authorRow) {
        return;
    }

    $content = sprintf(
        "Draw & Guess invite from @%s\nRequired players: %d\nOpen Game and click Ready to join.",
        (string)$authorRow['username'],
        $requiredPlayers
    );

    $insertMessage = $pdo->prepare("
        INSERT INTO chat_messages (conversation_id, user_id, content_text, status, created_at, updated_at)
        VALUES (?, ?, ?, 'active', NOW(), NOW())
    ");
    $insertMessage->execute([$conversationId, $currentUserId, $content]);
    $messageId = (int)$pdo->lastInsertId();

    $updateConversation = $pdo->prepare("
        UPDATE chat_conversations
        SET last_message_at = NOW(), updated_at = NOW()
        WHERE conversation_id = ?
    ");
    $updateConversation->execute([$conversationId]);

    $updateRead = $pdo->prepare("
        UPDATE chat_conversation_members
        SET last_read_at = NOW(), last_read_message_id = ?, updated_at = NOW()
        WHERE conversation_id = ?
          AND user_id = ?
    ");
    $updateRead->execute([$messageId, $conversationId, $currentUserId]);

    $messageRow = [
        'message_id' => $messageId,
        'conversation_id' => $conversationId,
        'user_id' => $currentUserId,
        'content_text' => $content,
        'created_at' => draw_guess_now_sql(),
        'author_name' => (string)$authorRow['username'],
        'email' => (string)$authorRow['email'],
    ];

    forum_realtime_publish('chat.message.created', [
        'conversationId' => $conversationId,
        'messageId' => $messageId,
        'message' => forum_chat_message_payload($messageRow, $currentUserId),
    ]);

    forum_realtime_publish('message-center.updated', [
        'conversationId' => $conversationId,
    ]);
}

function draw_guess_ensure_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("
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
            end_reason VARCHAR(32) NULL,
            started_at DATETIME NULL,
            ended_at DATETIME NULL,
            created_by_user_id BIGINT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_draw_guess_games_conversation
                FOREIGN KEY (conversation_id) REFERENCES chat_conversations(conversation_id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_draw_guess_games_creator
                FOREIGN KEY (created_by_user_id) REFERENCES users(user_id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    try {
        $pdo->exec("ALTER TABLE chat_draw_guess_games ADD COLUMN end_reason VARCHAR(32) NULL AFTER active_guessers_count");
    } catch (Throwable $_error) {
        // Column already exists.
    }

    $pdo->exec("
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
                FOREIGN KEY (game_id) REFERENCES chat_draw_guess_games(game_id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_draw_guess_players_user
                FOREIGN KEY (user_id) REFERENCES users(user_id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT uq_draw_guess_players UNIQUE (game_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_draw_guess_words (
            word_id BIGINT PRIMARY KEY AUTO_INCREMENT,
            word_text VARCHAR(96) NOT NULL,
            text_hint VARCHAR(255) NOT NULL,
            second_text_hint VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT uq_draw_guess_words UNIQUE (word_text)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
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
                FOREIGN KEY (game_id) REFERENCES chat_draw_guess_games(game_id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_draw_guess_rounds_drawer
                FOREIGN KEY (drawer_user_id) REFERENCES users(user_id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_draw_guess_strokes (
            stroke_id BIGINT PRIMARY KEY AUTO_INCREMENT,
            round_id BIGINT NOT NULL,
            created_by_user_id BIGINT NOT NULL,
            event_type VARCHAR(20) NOT NULL DEFAULT 'stroke',
            payload_json JSON NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_draw_guess_strokes_round
                FOREIGN KEY (round_id) REFERENCES chat_draw_guess_rounds(round_id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_draw_guess_strokes_user
                FOREIGN KEY (created_by_user_id) REFERENCES users(user_id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
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
                FOREIGN KEY (round_id) REFERENCES chat_draw_guess_rounds(round_id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_draw_guess_guesses_user
                FOREIGN KEY (user_id) REFERENCES users(user_id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM chat_draw_guess_words")->fetchColumn();
    if ($count === 0) {
        $seedWords = [
            ['apple', 'It is a fruit that is often red or green.', 'It is a common fruit people pack in a lunch box.'],
            ['guitar', 'It is a musical instrument with strings.', 'You usually hold it and play chords with your hands.'],
            ['mountain', 'It is a tall natural landform.', 'People often hike or climb to the top of it.'],
            ['pencil', 'It is used for writing and sketching.', 'You can erase what it makes on paper.'],
            ['butterfly', 'It is a colorful flying insect.', 'It starts life as a caterpillar.'],
            ['pizza', 'It is a popular baked food with toppings.', 'It is usually sliced into triangles.'],
            ['robot', 'It is a machine that can perform tasks.', 'People often imagine it with metal arms and sensors.'],
            ['castle', 'It is a large old fortified building.', 'Kings and queens are often associated with it.'],
            ['ocean', 'It is a very large body of salt water.', 'Waves, ships, and sea animals belong there.'],
            ['library', 'It is a quiet place full of books.', 'People visit it to borrow reading materials.'],
            ['rainbow', 'It appears in the sky after rain.', 'It has many colors in a curved band.'],
            ['basketball', 'It is a sport played with a hoop.', 'Players dribble and shoot an orange ball.'],
            ['sandwich', 'It is a meal made with bread and fillings.', 'You can pack it and eat it cold.'],
            ['camera', 'It is used to capture photos.', 'You point it at a subject and press a button.'],
            ['airport', 'It is where planes take off and land.', 'Travelers check in and go through security there.'],
        ];
        $stmt = $pdo->prepare("
            INSERT INTO chat_draw_guess_words (word_text, text_hint, second_text_hint)
            VALUES (:word, :textHint, :secondTextHint)
        ");
        foreach ($seedWords as [$word, $textHint, $secondTextHint]) {
            $stmt->execute([
                ':word' => $word,
                ':textHint' => $textHint,
                ':secondTextHint' => $secondTextHint,
            ]);
        }
    }

    $ensured = true;
}

function draw_guess_normalize_guess(string $value): string {
    $value = trim(mb_strtolower($value, 'UTF-8'));
    return preg_replace('/\s+/', ' ', $value) ?? $value;
}

function draw_guess_conversation(PDO $pdo, int $conversationId): ?array {
    $stmt = $pdo->prepare("
        SELECT conversation_id, conversation_type, title, status
        FROM chat_conversations
        WHERE conversation_id = :conversationId
        LIMIT 1
    ");
    $stmt->execute([':conversationId' => $conversationId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function draw_guess_conversation_members(PDO $pdo, int $conversationId): array {
    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('user_', u.user_id)) AS username,
            COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('User ', u.user_id)) AS display_name,
            ccm.member_role
        FROM chat_conversation_members ccm
        INNER JOIN users u ON u.user_id = ccm.user_id
        WHERE ccm.conversation_id = :conversationId
          AND u.role = 'user'
        ORDER BY ccm.joined_at ASC, ccm.conversation_member_id ASC
    ");
    $stmt->execute([':conversationId' => $conversationId]);
    return $stmt->fetchAll() ?: [];
}

function draw_guess_require_chat_member(PDO $pdo, int $conversationId, int $userId): array {
    $conversation = draw_guess_conversation($pdo, $conversationId);
    if (!$conversation || ($conversation['status'] ?? '') !== 'active') {
        forum_json(['ok' => false, 'message' => 'Conversation not found.'], 404);
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM chat_conversation_members
        WHERE conversation_id = :conversationId
          AND user_id = :userId
        LIMIT 1
    ");
    $stmt->execute([
        ':conversationId' => $conversationId,
        ':userId' => $userId,
    ]);
    if (!$stmt->fetchColumn()) {
        forum_json(['ok' => false, 'message' => 'You are not a member of this conversation.'], 403);
    }

    return $conversation;
}

function draw_guess_active_game(PDO $pdo, int $conversationId): ?array {
    $stmt = $pdo->prepare("
        SELECT *
        FROM chat_draw_guess_games
        WHERE conversation_id = :conversationId
          AND status IN ('LOBBY', 'ROUND_START', 'PLAYING', 'ROUND_END')
        ORDER BY game_id DESC
        LIMIT 1
    ");
    $stmt->execute([':conversationId' => $conversationId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function draw_guess_any_game(PDO $pdo, int $conversationId): ?array {
    $stmt = $pdo->prepare("
        SELECT *
        FROM chat_draw_guess_games
        WHERE conversation_id = :conversationId
        ORDER BY game_id DESC
        LIMIT 1
    ");
    $stmt->execute([':conversationId' => $conversationId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function draw_guess_players(PDO $pdo, int $gameId): array {
    $stmt = $pdo->prepare("
        SELECT
            gp.*,
            COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('user_', u.user_id)) AS username,
            COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('User ', u.user_id)) AS display_name
        FROM chat_draw_guess_players gp
        INNER JOIN users u ON u.user_id = gp.user_id
        WHERE gp.game_id = :gameId
        ORDER BY gp.drawer_order ASC, gp.join_order ASC, gp.game_player_id ASC
    ");
    $stmt->execute([':gameId' => $gameId]);
    return $stmt->fetchAll() ?: [];
}

function draw_guess_current_round(PDO $pdo, int $gameId): ?array {
    $stmt = $pdo->prepare("
        SELECT *
        FROM chat_draw_guess_rounds
        WHERE game_id = :gameId
        ORDER BY round_index DESC, round_id DESC
        LIMIT 1
    ");
    $stmt->execute([':gameId' => $gameId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function draw_guess_round_strokes(PDO $pdo, int $roundId): array {
    $stmt = $pdo->prepare("
        SELECT stroke_id, event_type, payload_json, created_at
        FROM chat_draw_guess_strokes
        WHERE round_id = :roundId
        ORDER BY stroke_id ASC
    ");
    $stmt->execute([':roundId' => $roundId]);
    $rows = $stmt->fetchAll() ?: [];
    return array_map(static function (array $row): array {
        return [
            'id' => (int)$row['stroke_id'],
            'eventType' => (string)$row['event_type'],
            'payload' => draw_guess_json_decode((string)$row['payload_json'], []),
            'createdAt' => (string)$row['created_at'],
        ];
    }, $rows);
}

function draw_guess_round_correct_user_ids(PDO $pdo, int $roundId): array {
    $stmt = $pdo->prepare("
        SELECT user_id
        FROM chat_draw_guess_guesses
        WHERE round_id = :roundId
          AND is_correct = 1
        GROUP BY user_id
        ORDER BY MIN(guess_id) ASC
    ");
    $stmt->execute([':roundId' => $roundId]);
    return array_map('intval', array_column($stmt->fetchAll() ?: [], 'user_id'));
}

function draw_guess_round_guess_feed(PDO $pdo, int $roundId, int $limit = 12): array {
    $stmt = $pdo->prepare("
        SELECT
            g.guess_id,
            g.user_id,
            g.guess_text,
            g.is_correct,
            g.score_awarded,
            g.created_at,
            COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('user_', u.user_id)) AS username,
            COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('User ', u.user_id)) AS display_name
        FROM chat_draw_guess_guesses g
        INNER JOIN users u ON u.user_id = g.user_id
        WHERE g.round_id = :roundId
        ORDER BY g.guess_id DESC
        LIMIT " . max(1, (int)$limit) . "
    ");
    $stmt->execute([':roundId' => $roundId]);
    $rows = array_reverse($stmt->fetchAll() ?: []);
    return array_map(static function (array $row): array {
        return [
            'guessId' => (int)$row['guess_id'],
            'userId' => (int)$row['user_id'],
            'username' => (string)$row['username'],
            'displayName' => (string)$row['display_name'],
            'guessText' => (string)$row['guess_text'],
            'isCorrect' => (int)$row['is_correct'] === 1,
            'scoreAwarded' => (int)$row['score_awarded'],
            'createdAt' => (string)$row['created_at'],
        ];
    }, $rows);
}

function draw_guess_find_player(array $players, int $userId): ?array {
    foreach ($players as $player) {
        if ((int)($player['user_id'] ?? 0) === $userId) {
            return $player;
        }
    }
    return null;
}

function draw_guess_active_players(array $players): array {
    return array_values(array_filter($players, static function (array $player): bool {
        return ($player['player_status'] ?? 'active') === 'active';
    }));
}

function draw_guess_sync_game(PDO $pdo, array $game): array {
    $now = draw_guess_now();
    $current = $game;

    if (($current['status'] ?? '') === DRAW_GUESS_STATUS_ROUND_START) {
        $round = draw_guess_current_round($pdo, (int)$current['game_id']);
        $deadline = draw_guess_datetime($round['selection_deadline_at'] ?? null);
        if ($round && $deadline && $now >= $deadline && empty($round['selected_word'])) {
            draw_guess_auto_pick_word($pdo, $current);
            $current = draw_guess_active_game($pdo, (int)$current['conversation_id']) ?? $current;
        }
    }

    if (($current['status'] ?? '') === DRAW_GUESS_STATUS_PLAYING) {
        $round = draw_guess_current_round($pdo, (int)$current['game_id']);
        if ($round) {
            $startedAt = draw_guess_datetime($round['started_at'] ?? null);
            $endsAt = draw_guess_datetime($round['ends_at'] ?? null);
            if ($startedAt) {
                $textHintAt = $startedAt->modify('+30 seconds');
                $secondTextHintAt = $startedAt->modify('+60 seconds');

                if (empty($round['text_hint_revealed_at']) && $now >= $textHintAt) {
                    $stmt = $pdo->prepare("
                        UPDATE chat_draw_guess_rounds
                        SET text_hint_revealed_at = :revealedAt
                        WHERE round_id = :roundId
                          AND text_hint_revealed_at IS NULL
                    ");
                    $stmt->execute([
                        ':revealedAt' => draw_guess_now_sql(),
                        ':roundId' => (int)$round['round_id'],
                    ]);
                    $round['text_hint_revealed_at'] = draw_guess_now_sql();
                    draw_guess_publish_state($pdo, $current, ['type' => 'game.hint.text']);
                }

                if (empty($round['second_text_hint_revealed_at']) && $now >= $secondTextHintAt) {
                    $stmt = $pdo->prepare("
                        UPDATE chat_draw_guess_rounds
                        SET second_text_hint_revealed_at = :revealedAt
                        WHERE round_id = :roundId
                          AND second_text_hint_revealed_at IS NULL
                    ");
                    $stmt->execute([
                        ':revealedAt' => draw_guess_now_sql(),
                        ':roundId' => (int)$round['round_id'],
                    ]);
                    $round['second_text_hint_revealed_at'] = draw_guess_now_sql();
                    draw_guess_publish_state($pdo, $current, ['type' => 'game.hint.text']);
                }
            }

            if ($endsAt && $now >= $endsAt) {
                draw_guess_finish_round($pdo, $current, 'timeout');
                $current = draw_guess_active_game($pdo, (int)$current['conversation_id']) ?? (draw_guess_any_game($pdo, (int)$current['conversation_id']) ?? $current);
            }
        }
    }

    if (($current['status'] ?? '') === DRAW_GUESS_STATUS_ROUND_END) {
        $round = draw_guess_current_round($pdo, (int)$current['game_id']);
        $endedAt = draw_guess_datetime($round['ended_at'] ?? null);
        if ($round && $endedAt && $now >= $endedAt->modify('+' . DRAW_GUESS_ROUND_END_BUFFER_SECONDS . ' seconds')) {
            draw_guess_begin_next_round($pdo, $current);
            $current = draw_guess_active_game($pdo, (int)$current['conversation_id']) ?? (draw_guess_any_game($pdo, (int)$current['conversation_id']) ?? $current);
        }
    }

    return $current;
}

function draw_guess_create_or_resume_lobby(PDO $pdo, array $conversation, array $user, int $minPlayers): array {
    $existing = draw_guess_active_game($pdo, (int)$conversation['conversation_id']);
    if ($existing) {
        if (($existing['status'] ?? '') !== DRAW_GUESS_STATUS_LOBBY) {
            $existingPlayers = draw_guess_players($pdo, (int)$existing['game_id']);
            $existingViewer = draw_guess_find_player($existingPlayers, (int)$user['user_id']);
            if (!$existingViewer || ($existingViewer['player_status'] ?? 'active') !== 'active') {
                forum_json(['ok' => false, 'message' => 'This game is already in progress. You cannot join it now.'], 403);
            }
        }
        return draw_guess_sync_game($pdo, $existing);
    }

    $members = draw_guess_conversation_members($pdo, (int)$conversation['conversation_id']);
    if (!$members) {
        forum_json(['ok' => false, 'message' => 'Conversation has no players.'], 422);
    }
    $memberCount = count($members);
    $minPlayers = max(DRAW_GUESS_MIN_PLAYERS, min($minPlayers, min(DRAW_GUESS_MAX_PLAYERS, $memberCount)));

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_draw_guess_games (
                conversation_id,
                status,
                min_players,
                created_by_user_id
            ) VALUES (
                :conversationId,
                'LOBBY',
                :minPlayers,
                :createdBy
            )
        ");
        $stmt->execute([
            ':conversationId' => (int)$conversation['conversation_id'],
            ':minPlayers' => $minPlayers,
            ':createdBy' => (int)$user['user_id'],
        ]);
        $gameId = (int)$pdo->lastInsertId();

        $playerStmt = $pdo->prepare("
            INSERT INTO chat_draw_guess_players (
                game_id,
                user_id,
                join_order,
                drawer_order,
                is_ready,
                player_status,
                connection_status,
                has_drawn
            ) VALUES (
                :gameId,
                :userId,
                :joinOrder,
                :drawerOrder,
                0,
                :playerStatus,
                'online',
                0
            )
        ");

        $order = 1;
        foreach ($members as $member) {
            $memberUserId = (int)$member['user_id'];
            $playerStmt->execute([
                ':gameId' => $gameId,
                ':userId' => $memberUserId,
                ':joinOrder' => $order,
                ':drawerOrder' => $order,
                ':playerStatus' => $memberUserId === (int)$user['user_id'] ? 'active' : 'spectator',
            ]);
            $order += 1;
        }

        draw_guess_insert_invite_message($pdo, (int)$conversation['conversation_id'], (int)$user['user_id'], $minPlayers);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    $game = draw_guess_active_game($pdo, (int)$conversation['conversation_id']);
    if (!$game) {
        forum_json(['ok' => false, 'message' => 'Failed to create game lobby.'], 500);
    }
    draw_guess_publish_state($pdo, $game, ['type' => 'game.lobby.updated']);
    return $game;
}

function draw_guess_toggle_ready(PDO $pdo, array $game, int $userId, bool $isReady): array {
    if (($game['status'] ?? '') !== DRAW_GUESS_STATUS_LOBBY) {
        forum_json(['ok' => false, 'message' => 'Ready state can only be updated in the lobby.'], 422);
    }

    $players = draw_guess_players($pdo, (int)$game['game_id']);
    $viewerPlayer = draw_guess_find_player($players, $userId);
    if (!$viewerPlayer || ($viewerPlayer['player_status'] ?? '') === 'left') {
        forum_json(['ok' => false, 'message' => 'You are not part of this game.'], 403);
    }

    $nextPlayerStatus = (string)($viewerPlayer['player_status'] ?? 'spectator');
    if ($isReady && $nextPlayerStatus !== 'active') {
        $activePlayers = draw_guess_active_players($players);
        if (count($activePlayers) >= (int)$game['min_players']) {
            forum_json(['ok' => false, 'message' => 'Player limit reached for this round.'], 422);
        }
        $nextPlayerStatus = 'active';
    }

    $stmt = $pdo->prepare("
        UPDATE chat_draw_guess_players
        SET is_ready = :isReady,
            player_status = :playerStatus,
            connection_status = 'online'
        WHERE game_id = :gameId
          AND user_id = :userId
        LIMIT 1
    ");
    $stmt->execute([
        ':isReady' => $isReady ? 1 : 0,
        ':playerStatus' => $nextPlayerStatus,
        ':gameId' => (int)$game['game_id'],
        ':userId' => $userId,
    ]);

    $fresh = draw_guess_active_game($pdo, (int)$game['conversation_id']) ?? $game;
    draw_guess_publish_state($pdo, $fresh, ['type' => 'game.lobby.updated']);
    return $fresh;
}

function draw_guess_start_game(PDO $pdo, array $game, int $userId): array {
    if (($game['status'] ?? '') !== DRAW_GUESS_STATUS_LOBBY) {
        forum_json(['ok' => false, 'message' => 'Game has already started.'], 422);
    }

    $players = draw_guess_active_players(draw_guess_players($pdo, (int)$game['game_id']));
    $readyPlayers = array_values(array_filter($players, static function (array $player): bool {
        return (int)($player['is_ready'] ?? 0) === 1;
    }));
    if (count($readyPlayers) < (int)$game['min_players']) {
        forum_json(['ok' => false, 'message' => 'Not enough ready players to start this game.'], 422);
    }

    $owner = draw_guess_find_player($players, $userId);
    if (!$owner) {
        forum_json(['ok' => false, 'message' => 'You are not part of this game.'], 403);
    }
    if ((int)($owner['is_ready'] ?? 0) !== 1) {
        forum_json(['ok' => false, 'message' => 'Please click Ready before starting the game.'], 422);
    }

    $statusStmt = $pdo->prepare("
        UPDATE chat_draw_guess_players
        SET player_status = CASE WHEN is_ready = 1 THEN 'active' ELSE 'spectator' END,
            connection_status = 'online'
        WHERE game_id = :gameId
    ");
    $statusStmt->execute([
        ':gameId' => (int)$game['game_id'],
    ]);

    draw_guess_begin_next_round($pdo, $game);
    $fresh = draw_guess_active_game($pdo, (int)$game['conversation_id']) ?? $game;
    draw_guess_publish_state($pdo, $fresh, ['type' => 'game.round.started']);
    return $fresh;
}

function draw_guess_begin_next_round(PDO $pdo, array $game): array {
    $players = draw_guess_active_players(draw_guess_players($pdo, (int)$game['game_id']));
    $undrawn = array_values(array_filter($players, static function (array $player): bool {
        return (int)($player['has_drawn'] ?? 0) !== 1;
    }));

    if (!$undrawn) {
        $stmt = $pdo->prepare("
            UPDATE chat_draw_guess_games
            SET status = 'GAME_END',
                end_reason = 'completed',
                ended_at = :endedAt
            WHERE game_id = :gameId
            LIMIT 1
        ");
        $stmt->execute([
            ':endedAt' => draw_guess_now_sql(),
            ':gameId' => (int)$game['game_id'],
        ]);
        $fresh = draw_guess_any_game($pdo, (int)$game['conversation_id']) ?? $game;
        draw_guess_publish_state($pdo, $fresh, ['type' => 'game.game.ended']);
        return $fresh;
    }

    usort($undrawn, static function (array $a, array $b): int {
        return ((int)$a['drawer_order']) <=> ((int)$b['drawer_order']);
    });
    $drawer = $undrawn[0];
    $roundIndex = ((int)$game['round_index']) + 1;
    $candidateWords = draw_guess_pick_candidate_words($pdo, 3);

    $pdo->beginTransaction();
    try {
        $roundStmt = $pdo->prepare("
            INSERT INTO chat_draw_guess_rounds (
                game_id,
                round_index,
                drawer_user_id,
                status,
                candidate_words_json,
                selection_deadline_at
            ) VALUES (
                :gameId,
                :roundIndex,
                :drawerUserId,
                'ROUND_START',
                :candidateWords,
                :selectionDeadline
            )
        ");
        $roundStmt->execute([
            ':gameId' => (int)$game['game_id'],
            ':roundIndex' => $roundIndex,
            ':drawerUserId' => (int)$drawer['user_id'],
            ':candidateWords' => json_encode($candidateWords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':selectionDeadline' => draw_guess_now()->modify('+' . DRAW_GUESS_WORD_PICK_SECONDS . ' seconds')->format('Y-m-d H:i:s'),
        ]);
        $roundId = (int)$pdo->lastInsertId();

        $gameStmt = $pdo->prepare("
            UPDATE chat_draw_guess_games
            SET status = 'ROUND_START',
                round_index = :roundIndex,
                current_round_id = :roundId,
                current_drawer_user_id = :drawerUserId,
                current_word_id = NULL,
                current_word = NULL,
                current_text_hint = NULL,
                current_second_text_hint = NULL,
                current_hint_stage = 'none',
                text_hint_revealed_at = NULL,
                second_text_hint_revealed_at = NULL,
                correct_guess_count = 0,
                active_guessers_count = :activeGuessersCount,
                end_reason = NULL,
                started_at = NULL,
                ended_at = NULL
            WHERE game_id = :gameId
            LIMIT 1
        ");
        $gameStmt->execute([
            ':roundIndex' => $roundIndex,
            ':roundId' => $roundId,
            ':drawerUserId' => (int)$drawer['user_id'],
            ':activeGuessersCount' => max(0, count($players) - 1),
            ':gameId' => (int)$game['game_id'],
        ]);

        $playerStmt = $pdo->prepare("
            UPDATE chat_draw_guess_players
            SET has_drawn = CASE WHEN user_id = :drawerUserId THEN 1 ELSE has_drawn END,
                connection_status = 'online'
            WHERE game_id = :gameId
        ");
        $playerStmt->execute([
            ':drawerUserId' => (int)$drawer['user_id'],
            ':gameId' => (int)$game['game_id'],
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    $fresh = draw_guess_active_game($pdo, (int)$game['conversation_id']) ?? $game;
    forum_realtime_publish('game.word.options', [
        'conversationId' => (int)$game['conversation_id'],
        'gameId' => (int)$game['game_id'],
        'targetUserId' => (int)$drawer['user_id'],
        'options' => $candidateWords,
    ]);
    draw_guess_publish_state($pdo, $fresh, ['type' => 'game.round.started']);
    return $fresh;
}

function draw_guess_pick_candidate_words(PDO $pdo, int $limit = 3): array {
    $stmt = $pdo->query("
        SELECT word_id, word_text, text_hint, second_text_hint
        FROM chat_draw_guess_words
        WHERE is_active = 1
        ORDER BY RAND()
        LIMIT " . max(3, $limit)
    );
    $rows = $stmt->fetchAll() ?: [];
    return array_map(static function (array $row): array {
        return [
            'id' => (int)$row['word_id'],
            'word' => (string)$row['word_text'],
            'textHint' => (string)$row['text_hint'],
            'secondTextHint' => (string)$row['second_text_hint'],
        ];
    }, $rows);
}

function draw_guess_pick_word(PDO $pdo, array $game, int $userId, int $wordId): array {
    if (($game['status'] ?? '') !== DRAW_GUESS_STATUS_ROUND_START) {
        forum_json(['ok' => false, 'message' => 'Word selection is not available right now.'], 422);
    }
    if ((int)($game['current_drawer_user_id'] ?? 0) !== $userId) {
        forum_json(['ok' => false, 'message' => 'Only the current drawer can choose a word.'], 403);
    }

    $round = draw_guess_current_round($pdo, (int)$game['game_id']);
    if (!$round) {
        forum_json(['ok' => false, 'message' => 'Round not found.'], 404);
    }
    $candidates = draw_guess_json_decode($round['candidate_words_json'] ?? null, []);
    $selected = null;
    foreach ($candidates as $candidate) {
        if ((int)($candidate['id'] ?? 0) === $wordId) {
            $selected = $candidate;
            break;
        }
    }
    if (!$selected) {
        forum_json(['ok' => false, 'message' => 'Selected word is not available.'], 422);
    }

    return draw_guess_start_playing_round($pdo, $game, $round, $selected);
}

function draw_guess_auto_pick_word(PDO $pdo, array $game): array {
    $round = draw_guess_current_round($pdo, (int)$game['game_id']);
    if (!$round || !empty($round['selected_word'])) {
        return $game;
    }
    $candidates = draw_guess_json_decode($round['candidate_words_json'] ?? null, []);
    if (!$candidates) {
        forum_json(['ok' => false, 'message' => 'No words available for this round.'], 500);
    }
    $selected = $candidates[array_rand($candidates)];
    return draw_guess_start_playing_round($pdo, $game, $round, $selected);
}

function draw_guess_start_playing_round(PDO $pdo, array $game, array $round, array $selected): array {
    $startedAt = draw_guess_now();
    $endsAt = $startedAt->modify('+' . DRAW_GUESS_ROUND_SECONDS . ' seconds');

    $pdo->beginTransaction();
    try {
        $roundStmt = $pdo->prepare("
            UPDATE chat_draw_guess_rounds
            SET status = 'PLAYING',
                selected_word_id = :wordId,
                selected_word = :word,
                text_hint = :textHint,
                second_text_hint = :secondTextHint,
                started_at = :startedAt,
                ends_at = :endsAt
            WHERE round_id = :roundId
            LIMIT 1
        ");
        $roundStmt->execute([
            ':wordId' => (int)$selected['id'],
            ':word' => (string)$selected['word'],
            ':textHint' => (string)$selected['textHint'],
            ':secondTextHint' => (string)$selected['secondTextHint'],
            ':startedAt' => $startedAt->format('Y-m-d H:i:s'),
            ':endsAt' => $endsAt->format('Y-m-d H:i:s'),
            ':roundId' => (int)$round['round_id'],
        ]);

        $gameStmt = $pdo->prepare("
            UPDATE chat_draw_guess_games
            SET status = 'PLAYING',
                current_word_id = :wordId,
                current_word = :word,
                current_text_hint = :textHint,
                current_second_text_hint = :secondTextHint,
                end_reason = NULL,
                started_at = :startedAt
            WHERE game_id = :gameId
            LIMIT 1
        ");
        $gameStmt->execute([
            ':wordId' => (int)$selected['id'],
            ':word' => (string)$selected['word'],
            ':textHint' => (string)$selected['textHint'],
            ':secondTextHint' => (string)$selected['secondTextHint'],
            ':startedAt' => $startedAt->format('Y-m-d H:i:s'),
            ':gameId' => (int)$game['game_id'],
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    $fresh = draw_guess_active_game($pdo, (int)$game['conversation_id']) ?? $game;
    draw_guess_publish_state($pdo, $fresh, ['type' => 'game.state.sync']);
    return $fresh;
}

function draw_guess_guess_score_for_rank(int $rank): int {
    return match ($rank) {
        1 => 30,
        2 => 20,
        3 => 10,
        default => 0,
    };
}

function draw_guess_drawer_score(int $correctGuessers): int {
    return match ($correctGuessers) {
        1 => 30,
        2 => 15,
        default => 0,
    };
}

function draw_guess_submit_guess(PDO $pdo, array $game, int $userId, string $guessText): array {
    if (($game['status'] ?? '') !== DRAW_GUESS_STATUS_PLAYING) {
        forum_json(['ok' => false, 'message' => 'Round is not accepting guesses right now.'], 422);
    }
    if ((int)($game['current_drawer_user_id'] ?? 0) === $userId) {
        forum_json(['ok' => false, 'message' => 'The drawer cannot submit guesses.'], 422);
    }
    $players = draw_guess_players($pdo, (int)$game['game_id']);
    $viewerPlayer = draw_guess_find_player($players, $userId);
    if (!$viewerPlayer || ($viewerPlayer['player_status'] ?? 'active') !== 'active') {
        forum_json(['ok' => false, 'message' => 'Only active players can submit guesses.'], 403);
    }

    $round = draw_guess_current_round($pdo, (int)$game['game_id']);
    if (!$round || empty($round['selected_word'])) {
        forum_json(['ok' => false, 'message' => 'Round has no active word.'], 422);
    }

    $normalizedGuess = draw_guess_normalize_guess($guessText);
    if ($normalizedGuess === '') {
        forum_json(['ok' => false, 'message' => 'Please enter a guess.'], 422);
    }

    $alreadyCorrectStmt = $pdo->prepare("
        SELECT 1
        FROM chat_draw_guess_guesses
        WHERE round_id = :roundId
          AND user_id = :userId
          AND is_correct = 1
        LIMIT 1
    ");
    $alreadyCorrectStmt->execute([
        ':roundId' => (int)$round['round_id'],
        ':userId' => $userId,
    ]);
    if ($alreadyCorrectStmt->fetchColumn()) {
        forum_json(['ok' => false, 'message' => 'You already guessed correctly in this round.'], 422);
    }

    $isCorrect = $normalizedGuess === draw_guess_normalize_guess((string)$round['selected_word']);
    $scoreAwarded = 0;

    $pdo->beginTransaction();
    try {
        if ($isCorrect) {
            $rankStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT user_id)
                FROM chat_draw_guess_guesses
                WHERE round_id = :roundId
                  AND is_correct = 1
            ");
            $rankStmt->execute([':roundId' => (int)$round['round_id']]);
            $rank = ((int)$rankStmt->fetchColumn()) + 1;
            $scoreAwarded = draw_guess_guess_score_for_rank($rank);
        }

        $guessStmt = $pdo->prepare("
            INSERT INTO chat_draw_guess_guesses (
                round_id,
                user_id,
                guess_text,
                normalized_guess,
                is_correct,
                score_awarded
            ) VALUES (
                :roundId,
                :userId,
                :guessText,
                :normalizedGuess,
                :isCorrect,
                :scoreAwarded
            )
        ");
        $guessStmt->execute([
            ':roundId' => (int)$round['round_id'],
            ':userId' => $userId,
            ':guessText' => trim($guessText),
            ':normalizedGuess' => $normalizedGuess,
            ':isCorrect' => $isCorrect ? 1 : 0,
            ':scoreAwarded' => $scoreAwarded,
        ]);

        if ($isCorrect && $scoreAwarded > 0) {
            $playerStmt = $pdo->prepare("
                UPDATE chat_draw_guess_players
                SET score = score + :scoreAwarded
                WHERE game_id = :gameId
                  AND user_id = :userId
                LIMIT 1
            ");
            $playerStmt->execute([
                ':scoreAwarded' => $scoreAwarded,
                ':gameId' => (int)$game['game_id'],
                ':userId' => $userId,
            ]);
        }

        if ($isCorrect) {
            $correctIds = draw_guess_round_correct_user_ids($pdo, (int)$round['round_id']);
            $activeGuessers = draw_guess_active_guesser_ids($pdo, (int)$game['game_id'], (int)$game['current_drawer_user_id']);
            $correctActiveCount = count(array_intersect($activeGuessers, $correctIds));

            $roundCountStmt = $pdo->prepare("
                UPDATE chat_draw_guess_rounds
                SET correct_guess_count = :correctGuessCount
                WHERE round_id = :roundId
                LIMIT 1
            ");
            $roundCountStmt->execute([
                ':correctGuessCount' => $correctActiveCount,
                ':roundId' => (int)$round['round_id'],
            ]);

            $gameCountStmt = $pdo->prepare("
                UPDATE chat_draw_guess_games
                SET correct_guess_count = :correctGuessCount
                WHERE game_id = :gameId
                LIMIT 1
            ");
            $gameCountStmt->execute([
                ':correctGuessCount' => $correctActiveCount,
                ':gameId' => (int)$game['game_id'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    $fresh = draw_guess_active_game($pdo, (int)$game['conversation_id']) ?? $game;
    draw_guess_publish_state($pdo, $fresh, ['type' => $isCorrect ? 'game.guess.correct' : 'game.guess.accepted']);

    if ($isCorrect) {
        $round = draw_guess_current_round($pdo, (int)$game['game_id']);
        $correctIds = draw_guess_round_correct_user_ids($pdo, (int)$round['round_id']);
        $activeGuessers = draw_guess_active_guesser_ids($pdo, (int)$game['game_id'], (int)$game['current_drawer_user_id']);
        if (count(array_intersect($activeGuessers, $correctIds)) >= count($activeGuessers)) {
            draw_guess_finish_round($pdo, $fresh, 'all_correct');
            $fresh = draw_guess_active_game($pdo, (int)$game['conversation_id']) ?? (draw_guess_any_game($pdo, (int)$game['conversation_id']) ?? $fresh);
        }
    }

    return $fresh;
}

function draw_guess_active_guesser_ids(PDO $pdo, int $gameId, int $drawerUserId): array {
    $players = draw_guess_players($pdo, $gameId);
    $ids = [];
    foreach ($players as $player) {
        if ((int)$player['user_id'] === $drawerUserId) {
            continue;
        }
        if (($player['player_status'] ?? 'active') !== 'active') {
            continue;
        }
        $ids[] = (int)$player['user_id'];
    }
    return $ids;
}

function draw_guess_finish_round(PDO $pdo, array $game, string $reason): array {
    if (($game['status'] ?? '') !== DRAW_GUESS_STATUS_PLAYING) {
        return $game;
    }

    $round = draw_guess_current_round($pdo, (int)$game['game_id']);
    if (!$round) {
        return $game;
    }

    $correctIds = draw_guess_round_correct_user_ids($pdo, (int)$round['round_id']);
    $activeGuessers = draw_guess_active_guesser_ids($pdo, (int)$game['game_id'], (int)$game['current_drawer_user_id']);
    $correctActiveCount = count(array_intersect($activeGuessers, $correctIds));
    $drawerScore = draw_guess_drawer_score($correctActiveCount);

    $pdo->beginTransaction();
    try {
        if ($correctActiveCount <= 0) {
            $roundScoresStmt = $pdo->prepare("
                SELECT user_id, SUM(score_awarded) AS total_score
                FROM chat_draw_guess_guesses
                WHERE round_id = :roundId
                GROUP BY user_id
                HAVING SUM(score_awarded) > 0
            ");
            $roundScoresStmt->execute([
                ':roundId' => (int)$round['round_id'],
            ]);
            $roundScores = $roundScoresStmt->fetchAll() ?: [];

            $revertPlayerScoreStmt = $pdo->prepare("
                UPDATE chat_draw_guess_players
                SET score = GREATEST(0, score - :scoreToRevert)
                WHERE game_id = :gameId
                  AND user_id = :userId
                LIMIT 1
            ");
            foreach ($roundScores as $scoreRow) {
                $revertPlayerScoreStmt->execute([
                    ':scoreToRevert' => (int)($scoreRow['total_score'] ?? 0),
                    ':gameId' => (int)$game['game_id'],
                    ':userId' => (int)$scoreRow['user_id'],
                ]);
            }

            $zeroRoundGuessScoresStmt = $pdo->prepare("
                UPDATE chat_draw_guess_guesses
                SET score_awarded = 0
                WHERE round_id = :roundId
            ");
            $zeroRoundGuessScoresStmt->execute([
                ':roundId' => (int)$round['round_id'],
            ]);

            $drawerScore = 0;
        }

        if ($drawerScore > 0) {
            $scoreStmt = $pdo->prepare("
                UPDATE chat_draw_guess_players
                SET score = score + :drawerScore
                WHERE game_id = :gameId
                  AND user_id = :drawerUserId
                LIMIT 1
            ");
            $scoreStmt->execute([
                ':drawerScore' => $drawerScore,
                ':gameId' => (int)$game['game_id'],
                ':drawerUserId' => (int)$game['current_drawer_user_id'],
            ]);
        }

        $roundStmt = $pdo->prepare("
            UPDATE chat_draw_guess_rounds
            SET status = 'ROUND_END',
                ended_at = :endedAt,
                correct_guess_count = :correctGuessCount,
                active_guessers_count = :activeGuessersCount
            WHERE round_id = :roundId
            LIMIT 1
        ");
        $roundStmt->execute([
            ':endedAt' => draw_guess_now_sql(),
            ':correctGuessCount' => $correctActiveCount,
            ':activeGuessersCount' => count($activeGuessers),
            ':roundId' => (int)$round['round_id'],
        ]);

        $gameStmt = $pdo->prepare("
            UPDATE chat_draw_guess_games
            SET status = 'ROUND_END',
                ended_at = :endedAt,
                correct_guess_count = :correctGuessCount,
                active_guessers_count = :activeGuessersCount,
                end_reason = NULL
            WHERE game_id = :gameId
            LIMIT 1
        ");
        $gameStmt->execute([
            ':endedAt' => draw_guess_now_sql(),
            ':correctGuessCount' => $correctActiveCount,
            ':activeGuessersCount' => count($activeGuessers),
            ':gameId' => (int)$game['game_id'],
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    $fresh = draw_guess_active_game($pdo, (int)$game['conversation_id']) ?? $game;
    draw_guess_publish_state($pdo, $fresh, [
        'type' => 'game.round.ended',
        'reason' => $reason,
        'answer' => (string)($round['selected_word'] ?? ''),
    ]);
    return $fresh;
}

function draw_guess_leave(PDO $pdo, array $game, int $userId): array {
    $playersBeforeLeave = draw_guess_players($pdo, (int)$game['game_id']);
    $leftPlayer = draw_guess_find_player($playersBeforeLeave, $userId);
    $leftByName = (string)($leftPlayer['display_name'] ?? $leftPlayer['username'] ?? ('User ' . $userId));

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
        UPDATE chat_draw_guess_players
        SET player_status = 'left',
            connection_status = 'offline',
            is_ready = 0
        WHERE game_id = :gameId
          AND user_id = :userId
        LIMIT 1
    ");
        $stmt->execute([
            ':gameId' => (int)$game['game_id'],
            ':userId' => $userId,
        ]);

        $resetPlayers = $pdo->prepare("
            UPDATE chat_draw_guess_players
            SET player_status = 'left',
                connection_status = 'offline',
                is_ready = 0
            WHERE game_id = :gameId
        ");
        $resetPlayers->execute([
            ':gameId' => (int)$game['game_id'],
        ]);

        $cancelStmt = $pdo->prepare("
            UPDATE chat_draw_guess_games
            SET status = 'GAME_END',
                end_reason = 'cancelled',
                ended_at = :endedAt
            WHERE game_id = :gameId
            LIMIT 1
        ");
        $cancelStmt->execute([
            ':endedAt' => draw_guess_now_sql(),
            ':gameId' => (int)$game['game_id'],
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    $ended = draw_guess_any_game($pdo, (int)$game['conversation_id']) ?? $game;
    draw_guess_publish_state($pdo, $ended, [
        'type' => 'game.cancelled',
        'reason' => 'player_left',
        'leftByUserId' => $userId,
        'leftByName' => $leftByName,
        'message' => $leftByName . ' left the game. This round is closed for everyone.',
    ]);
    return $ended;
}

function draw_guess_add_stroke(PDO $pdo, array $game, int $userId, string $eventType, array $payload): array {
    if (($game['status'] ?? '') !== DRAW_GUESS_STATUS_PLAYING) {
        forum_json(['ok' => false, 'message' => 'Canvas is not active right now.'], 422);
    }
    if ((int)($game['current_drawer_user_id'] ?? 0) !== $userId) {
        forum_json(['ok' => false, 'message' => 'Only the drawer can draw.'], 403);
    }
    $round = draw_guess_current_round($pdo, (int)$game['game_id']);
    if (!$round) {
        forum_json(['ok' => false, 'message' => 'Round not found.'], 404);
    }

    $stmt = $pdo->prepare("
        INSERT INTO chat_draw_guess_strokes (
            round_id,
            created_by_user_id,
            event_type,
            payload_json
        ) VALUES (
            :roundId,
            :userId,
            :eventType,
            :payloadJson
        )
    ");
    $stmt->execute([
        ':roundId' => (int)$round['round_id'],
        ':userId' => $userId,
        ':eventType' => $eventType,
        ':payloadJson' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    forum_realtime_publish($eventType === 'clear' ? 'game.canvas.cleared' : 'game.stroke.broadcast', [
        'conversationId' => (int)$game['conversation_id'],
        'gameId' => (int)$game['game_id'],
        'roundId' => (int)$round['round_id'],
        'stroke' => [
            'id' => (int)$pdo->lastInsertId(),
            'eventType' => $eventType,
            'payload' => $payload,
            'createdAt' => draw_guess_now()->format(DATE_ATOM),
        ],
    ]);

    return $game;
}

function draw_guess_public_state(PDO $pdo, array $game, int $viewerUserId): array {
    $players = draw_guess_players($pdo, (int)$game['game_id']);
    $activePlayers = draw_guess_active_players($players);
    $round = draw_guess_current_round($pdo, (int)$game['game_id']);
    $correctIds = $round ? draw_guess_round_correct_user_ids($pdo, (int)$round['round_id']) : [];
    $viewerPlayer = draw_guess_find_player($players, $viewerUserId);
    $drawerId = (int)($game['current_drawer_user_id'] ?? 0);
    $candidateWords = $round ? draw_guess_json_decode($round['candidate_words_json'] ?? null, []) : [];

    $hints = [];
    if ($round && !empty($round['text_hint_revealed_at'])) {
        $hints[] = [
            'level' => 1,
            'text' => (string)($round['text_hint'] ?? ''),
        ];
    }
    if ($round && !empty($round['second_text_hint_revealed_at'])) {
        $hints[] = [
            'level' => 2,
            'text' => (string)($round['second_text_hint'] ?? ''),
        ];
    }

    $roundEndsAt = $round['ends_at'] ?? null;
    $selectionDeadline = $round['selection_deadline_at'] ?? null;

    $playersForView = $activePlayers;

    return [
        'roomId' => (int)$game['game_id'],
        'chatId' => (int)$game['conversation_id'],
        'status' => (string)$game['status'],
        'minPlayers' => (int)$game['min_players'],
        'readyCount' => count(array_filter($playersForView, static fn(array $player): bool => (int)($player['is_ready'] ?? 0) === 1)),
        'players' => array_map(static function (array $player) use ($drawerId, $correctIds): array {
            $playerUserId = (int)$player['user_id'];
            return [
                'userId' => $playerUserId,
                'username' => (string)$player['username'],
                'displayName' => (string)$player['display_name'],
                'score' => (int)$player['score'],
                'isReady' => (int)$player['is_ready'] === 1,
                'isDrawer' => $playerUserId === $drawerId,
                'hasGuessedCorrectly' => in_array($playerUserId, $correctIds, true),
                'connectionStatus' => (string)$player['connection_status'],
                'playerStatus' => (string)$player['player_status'],
                'hasDrawn' => (int)$player['has_drawn'] === 1,
            ];
        }, $playersForView),
        'roundIndex' => (int)$game['round_index'],
        'drawerOrder' => array_map(static fn(array $player): int => (int)$player['user_id'], $playersForView),
        'currentDrawerId' => $drawerId,
        'roundStartTime' => $round['started_at'] ?? null,
        'roundEndsAt' => $roundEndsAt,
        'roundEndedAt' => $round['ended_at'] ?? null,
        'selectionDeadlineAt' => $selectionDeadline,
        'correctGuessCount' => (int)$game['correct_guess_count'],
        'activeGuessersCount' => (int)$game['active_guessers_count'],
        'hints' => $hints,
        'revealedAnswer' => in_array((string)$game['status'], [DRAW_GUESS_STATUS_ROUND_END, DRAW_GUESS_STATUS_GAME_END], true)
            ? (string)($round['selected_word'] ?? '')
            : null,
        'endReason' => $game['end_reason'] ?? null,
        'viewer' => [
            'userId' => $viewerUserId,
            'isReady' => (int)($viewerPlayer['is_ready'] ?? 0) === 1,
            'isDrawer' => $drawerId === $viewerUserId,
            'hasGuessedCorrectly' => in_array($viewerUserId, $correctIds, true),
            'canGuess' => ($drawerId !== $viewerUserId)
                && (($viewerPlayer['player_status'] ?? 'spectator') === 'active')
                && (($game['status'] ?? '') === DRAW_GUESS_STATUS_PLAYING)
                && !in_array($viewerUserId, $correctIds, true),
        ],
        'drawerWordOptions' => ($drawerId === $viewerUserId && ($game['status'] ?? '') === DRAW_GUESS_STATUS_ROUND_START)
            ? $candidateWords
            : [],
        'drawerWord' => ($drawerId === $viewerUserId && in_array((string)$game['status'], [DRAW_GUESS_STATUS_PLAYING, DRAW_GUESS_STATUS_ROUND_END, DRAW_GUESS_STATUS_GAME_END], true))
            ? (string)($round['selected_word'] ?? '')
            : null,
        'strokes' => ($round && in_array((string)$game['status'], [DRAW_GUESS_STATUS_PLAYING, DRAW_GUESS_STATUS_ROUND_END], true))
            ? draw_guess_round_strokes($pdo, (int)$round['round_id'])
            : [],
        'recentGuesses' => $round ? draw_guess_round_guess_feed($pdo, (int)$round['round_id']) : [],
    ];
}

function draw_guess_publish_state(PDO $pdo, array $game, array $extra = []): void {
    $players = draw_guess_players($pdo, (int)$game['game_id']);
    $stateByUser = [];
    foreach ($players as $player) {
        $stateByUser[(int)$player['user_id']] = draw_guess_public_state($pdo, $game, (int)$player['user_id']);
    }
    $publishedAtMs = (int)floor(microtime(true) * 1000);

    forum_realtime_publish((string)($extra['type'] ?? 'game.state.sync'), [
        'conversationId' => (int)$game['conversation_id'],
        'gameId' => (int)$game['game_id'],
        'publishedAtMs' => $publishedAtMs,
        'states' => $stateByUser,
        'reason' => $extra['reason'] ?? null,
        'answer' => $extra['answer'] ?? null,
        'message' => $extra['message'] ?? null,
        'leftByUserId' => $extra['leftByUserId'] ?? null,
        'leftByName' => $extra['leftByName'] ?? null,
    ]);
}
