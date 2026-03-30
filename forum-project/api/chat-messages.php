<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function chat_load_message_rows(PDO $pdo, int $conversationId, int $currentUserId): array {
    $stmt = $pdo->prepare("
        SELECT
            m.message_id,
            m.conversation_id,
            m.user_id,
            m.content_text,
            m.created_at,
            u.username AS author_name,
            u.email
        FROM chat_messages m
        JOIN users u ON u.user_id = m.user_id
        WHERE m.conversation_id = ?
          AND m.status = 'active'
        ORDER BY m.created_at ASC, m.message_id ASC
    ");
    $stmt->execute([$conversationId]);
    return array_map(
        static fn(array $row): array => forum_chat_message_payload($row, $currentUserId),
        $stmt->fetchAll()
    );
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user = forum_require_user();
$currentUserId = (int)$user['user_id'];
$pdo = forum_db();

if ($method === 'GET') {
    $conversationId = (int)($_GET['conversationId'] ?? 0);
    if ($conversationId <= 0) {
        forum_json(['ok' => false, 'message' => 'Conversation id is required.'], 422);
    }

    $accessStmt = $pdo->prepare("
        SELECT c.conversation_id, c.conversation_type, c.title, c.last_message_at
        FROM chat_conversation_members cm
        JOIN chat_conversations c
            ON c.conversation_id = cm.conversation_id
           AND c.status = 'active'
        WHERE cm.conversation_id = ?
          AND cm.user_id = ?
        LIMIT 1
    ");
    $accessStmt->execute([$conversationId, $currentUserId]);
    $conversationRow = $accessStmt->fetch();
    if (!$conversationRow) {
        forum_json(['ok' => false, 'message' => 'Conversation not found.'], 404);
    }

    $markReadStmt = $pdo->prepare("
        UPDATE chat_conversation_members
        SET last_read_at = NOW(), updated_at = NOW()
        WHERE conversation_id = ?
          AND user_id = ?
    ");
    $markReadStmt->execute([$conversationId, $currentUserId]);

    $conversationRefreshStmt = $pdo->prepare("
        SELECT
            c.conversation_id,
            c.conversation_type,
            c.title,
            c.last_message_at,
            lm.content_text AS last_message_text,
            lu.username AS last_message_author,
            0 AS unread_count
        FROM chat_conversations c
        LEFT JOIN chat_messages lm ON lm.message_id = (
            SELECT m2.message_id
            FROM chat_messages m2
            WHERE m2.conversation_id = c.conversation_id
              AND m2.status = 'active'
            ORDER BY m2.created_at DESC, m2.message_id DESC
            LIMIT 1
        )
        LEFT JOIN users lu ON lu.user_id = lm.user_id
        WHERE c.conversation_id = ?
          AND c.status = 'active'
        LIMIT 1
    ");
    $conversationRefreshStmt->execute([$conversationId]);
    $conversationRow = $conversationRefreshStmt->fetch() ?: $conversationRow;

    $membersStmt = $pdo->prepare("
        SELECT cm.member_role, u.user_id, u.username, u.email
        FROM chat_conversation_members cm
        JOIN users u ON u.user_id = cm.user_id
        WHERE cm.conversation_id = ?
        ORDER BY cm.joined_at ASC, cm.conversation_member_id ASC
    ");
    $membersStmt->execute([$conversationId]);
    $members = array_map(
        static fn(array $row): array => forum_chat_member_payload($row, $currentUserId),
        $membersStmt->fetchAll()
    );

    $conversation = forum_chat_conversation_payload($conversationRow, $members, $currentUserId);
    $messages = chat_load_message_rows($pdo, $conversationId, $currentUserId);

    forum_json([
        'ok' => true,
        'conversation' => $conversation,
        'messages' => $messages,
    ]);
}

if ($method !== 'POST') {
    forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = forum_input();
$conversationId = (int)($input['conversationId'] ?? 0);
$content = trim((string)($input['content'] ?? ''));

if ($conversationId <= 0 || $content === '') {
    forum_json(['ok' => false, 'message' => 'Conversation and content are required.'], 422);
}

$accessStmt = $pdo->prepare("
    SELECT conversation_id
    FROM chat_conversation_members
    WHERE conversation_id = ?
      AND user_id = ?
    LIMIT 1
");
$accessStmt->execute([$conversationId, $currentUserId]);
if (!$accessStmt->fetch()) {
    forum_json(['ok' => false, 'message' => 'Conversation not found.'], 404);
}

$pdo->beginTransaction();
try {
    $insertMessage = $pdo->prepare("
        INSERT INTO chat_messages (conversation_id, user_id, content_text, status, created_at, updated_at)
        VALUES (?, ?, ?, 'active', NOW(), NOW())
    ");
    $insertMessage->execute([$conversationId, $currentUserId, $content]);
    $messageId = (int)$pdo->lastInsertId();

    $mediaRows = forum_extract_media_rows($content);
    if ($mediaRows) {
        $insertMedia = $pdo->prepare("
            INSERT INTO chat_message_media (message_id, media_type, media_url, order_index, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        foreach ($mediaRows as $mediaRow) {
            $insertMedia->execute([$messageId, $mediaRow['media_type'], $mediaRow['media_url'], $mediaRow['order_index']]);
        }
    }

    $updateConversation = $pdo->prepare("
        UPDATE chat_conversations
        SET last_message_at = NOW(), updated_at = NOW()
        WHERE conversation_id = ?
    ");
    $updateConversation->execute([$conversationId]);

    $updateRead = $pdo->prepare("
        UPDATE chat_conversation_members
        SET last_read_at = NOW(), updated_at = NOW()
        WHERE conversation_id = ?
          AND user_id = ?
    ");
    $updateRead->execute([$conversationId, $currentUserId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    forum_json(['ok' => false, 'message' => 'Failed to send message.'], 500);
}

$messageStmt = $pdo->prepare("
    SELECT
        m.message_id,
        m.conversation_id,
        m.user_id,
        m.content_text,
        m.created_at,
        u.username AS author_name,
        u.email
    FROM chat_messages m
    JOIN users u ON u.user_id = m.user_id
    WHERE m.message_id = ?
    LIMIT 1
");
$messageStmt->execute([$messageId]);
$row = $messageStmt->fetch();

forum_realtime_publish('chat.message.created', [
    'conversationId' => $conversationId,
    'messageId' => $messageId,
]);

forum_json([
    'ok' => true,
    'message' => $row ? forum_chat_message_payload($row, $currentUserId) : null,
], 201);
