<?php
declare(strict_types=1);

require_once __DIR__ . '/chat-draw-guess-lib.php';

$user = forum_require_user();
$pdo = forum_db();
draw_guess_ensure_schema($pdo);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$input = $method === 'GET' ? $_GET : forum_input();
$conversationId = (int)($input['conversationId'] ?? 0);

if ($conversationId <= 0) {
    forum_json([
        'ok' => false,
        'message' => 'conversationId is required.',
    ], 422);
}

$conversation = draw_guess_require_chat_member($pdo, $conversationId, (int)$user['user_id']);

try {
    if ($method === 'GET') {
        $game = draw_guess_active_game($pdo, $conversationId);
        if (!$game) {
            $latest = draw_guess_any_game($pdo, $conversationId);
            if ($latest && ($latest['status'] ?? '') === DRAW_GUESS_STATUS_GAME_END) {
                $game = $latest;
            }
        }
        if (!$game) {
            forum_json([
                'ok' => true,
                'game' => null,
            ]);
        }

        $game = draw_guess_sync_game($pdo, $game);
        if (in_array((string)($game['status'] ?? ''), [DRAW_GUESS_STATUS_ROUND_START, DRAW_GUESS_STATUS_PLAYING, DRAW_GUESS_STATUS_ROUND_END], true)) {
            $players = draw_guess_players($pdo, (int)$game['game_id']);
            $viewerPlayer = draw_guess_find_player($players, (int)$user['user_id']);
            if (!$viewerPlayer || ($viewerPlayer['player_status'] ?? 'active') !== 'active') {
                forum_json([
                    'ok' => true,
                    'game' => null,
                ]);
            }
        }
        forum_json([
            'ok' => true,
            'game' => draw_guess_public_state($pdo, $game, (int)$user['user_id']),
        ]);
    }

    if ($method !== 'POST') {
        forum_json([
            'ok' => false,
            'message' => 'Method not allowed.',
        ], 405);
    }

    $action = trim((string)($input['action'] ?? ''));
    if ($action === '') {
        forum_json([
            'ok' => false,
            'message' => 'action is required.',
        ], 422);
    }

    $game = draw_guess_active_game($pdo, $conversationId);
    if (!$game && !in_array($action, ['createLobby'], true)) {
        $latest = draw_guess_any_game($pdo, $conversationId);
        if ($action === 'tick' && $latest) {
            forum_json([
                'ok' => true,
                'game' => draw_guess_public_state($pdo, $latest, (int)$user['user_id']),
            ]);
        }
        forum_json([
            'ok' => false,
            'message' => 'No active game for this conversation.',
        ], 404);
    }
    if ($game) {
        $game = draw_guess_sync_game($pdo, $game);
    }

    $currentUserId = (int)$user['user_id'];
    $result = null;

    switch ($action) {
        case 'createLobby':
            $result = draw_guess_create_or_resume_lobby($pdo, $conversation, $user, (int)($input['minPlayers'] ?? DRAW_GUESS_MIN_PLAYERS));
            break;

        case 'toggleReady':
            $result = draw_guess_toggle_ready($pdo, $game, $currentUserId, (bool)($input['isReady'] ?? false));
            break;

        case 'startGame':
            $result = draw_guess_start_game($pdo, $game, $currentUserId);
            break;

        case 'pickWord':
            $result = draw_guess_pick_word($pdo, $game, $currentUserId, (int)($input['wordId'] ?? 0));
            break;

        case 'submitGuess':
            $result = draw_guess_submit_guess($pdo, $game, $currentUserId, (string)($input['guess'] ?? ''));
            break;

        case 'tick':
            $result = draw_guess_sync_game($pdo, $game);
            break;

        case 'leave':
            $result = draw_guess_leave($pdo, $game, $currentUserId);
            break;

        case 'stroke':
            $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
            $result = draw_guess_add_stroke($pdo, $game, $currentUserId, 'stroke', $payload);
            break;

        case 'clearCanvas':
            $result = draw_guess_add_stroke($pdo, $game, $currentUserId, 'clear', [
                'type' => 'clear',
            ]);
            break;

        default:
            forum_json([
                'ok' => false,
                'message' => 'Unsupported action.',
            ], 422);
    }

    $responseGame = $result ? draw_guess_public_state($pdo, $result, $currentUserId) : null;
    forum_json([
        'ok' => true,
        'game' => $responseGame,
    ]);
} catch (Throwable $error) {
    forum_json([
        'ok' => false,
        'message' => $error->getMessage() ?: 'Game request failed.',
    ], 500);
}
