<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function chat_load_members(PDO $pdo, array $conversationIds, int $currentUserId): array {
    if (!$conversationIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($conversationIds), '?'));
    $stmt = $pdo->prepare("
        SELECT
            cm.conversation_id,
            cm.member_role,
            u.user_id,
            u.username,
            u.email
        FROM chat_conversation_members cm
        JOIN users u ON u.user_id = cm.user_id
        WHERE cm.conversation_id IN ({$placeholders})
        ORDER BY cm.joined_at ASC, cm.conversation_member_id ASC
    ");
    $stmt->execute($conversationIds);

    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $conversationId = (int)$row['conversation_id'];
        $grouped[$conversationId] ??= [];
        $grouped[$conversationId][] = forum_chat_member_payload($row, $currentUserId);
    }

    return $grouped;
}

function chat_load_conversation(PDO $pdo, int $conversationId, int $currentUserId): ?array {
    $stmt = $pdo->prepare("
        SELECT
            c.conversation_id,
            c.conversation_type,
            c.title,
            c.last_message_at,
            lm.content_text AS last_message_text,
            lu.username AS last_message_author,
            (
                SELECT COUNT(*)
                FROM chat_messages um
                WHERE um.conversation_id = c.conversation_id
                  AND um.status = 'active'
                  AND um.user_id <> ?
                  AND (
                    (mine.last_read_message_id IS NOT NULL AND um.message_id > mine.last_read_message_id)
                    OR (
                        mine.last_read_message_id IS NULL
                        AND (
                            mine.last_read_at IS NULL
                            OR um.created_at > mine.last_read_at
                        )
                    )
                  )
            ) AS unread_count
        FROM chat_conversations c
        JOIN chat_conversation_members mine
            ON mine.conversation_id = c.conversation_id
           AND mine.user_id = ?
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
    $stmt->execute([$currentUserId, $currentUserId, $conversationId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $members = chat_load_members($pdo, [$conversationId], $currentUserId);
    return forum_chat_conversation_payload($row, $members[$conversationId] ?? [], $currentUserId);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user = forum_require_user();
$currentUserId = (int)$user['user_id'];
$pdo = forum_db();

if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT
            c.conversation_id,
            c.conversation_type,
            c.title,
            c.last_message_at,
            lm.content_text AS last_message_text,
            lu.username AS last_message_author,
            (
                SELECT COUNT(*)
                FROM chat_messages um
                WHERE um.conversation_id = c.conversation_id
                  AND um.status = 'active'
                  AND um.user_id <> mine.user_id
                  AND (
                    (mine.last_read_message_id IS NOT NULL AND um.message_id > mine.last_read_message_id)
                    OR (
                        mine.last_read_message_id IS NULL
                        AND (
                            mine.last_read_at IS NULL
                            OR um.created_at > mine.last_read_at
                        )
                    )
                  )
            ) AS unread_count
        FROM chat_conversation_members mine
        JOIN chat_conversations c
            ON c.conversation_id = mine.conversation_id
           AND c.status = 'active'
        LEFT JOIN chat_messages lm ON lm.message_id = (
            SELECT m2.message_id
            FROM chat_messages m2
            WHERE m2.conversation_id = c.conversation_id
              AND m2.status = 'active'
            ORDER BY m2.created_at DESC, m2.message_id DESC
            LIMIT 1
        )
        LEFT JOIN users lu ON lu.user_id = lm.user_id
        WHERE mine.user_id = ?
        ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.conversation_id DESC
    ");
    $stmt->execute([$currentUserId]);
    $rows = $stmt->fetchAll();
    $conversationIds = array_values(array_map(static fn(array $row): int => (int)$row['conversation_id'], $rows));
    $membersByConversation = chat_load_members($pdo, $conversationIds, $currentUserId);

    $conversations = array_map(
        static fn(array $row): array => forum_chat_conversation_payload(
            $row,
            $membersByConversation[(int)$row['conversation_id']] ?? [],
            $currentUserId
        ),
        $rows
    );

    forum_json([
        'ok' => true,
        'currentUser' => $user,
        'conversations' => $conversations,
    ]);
}

if ($method !== 'POST') {
    forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = forum_input();
$action = trim((string)($input['action'] ?? ''));

if ($action === 'direct') {
    $targetUserId = (int)($input['targetUserId'] ?? 0);
    if ($targetUserId <= 0 || $targetUserId === $currentUserId) {
        forum_json(['ok' => false, 'message' => 'Choose a valid user.'], 422);
    }

    $targetStmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
    $targetStmt->execute([$targetUserId]);
    if (!$targetStmt->fetch()) {
        forum_json(['ok' => false, 'message' => 'User not found.'], 404);
    }

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
    $existingId = (int)($existingStmt->fetchColumn() ?: 0);
    if ($existingId > 0) {
        $conversation = chat_load_conversation($pdo, $existingId, $currentUserId);
        forum_json([
            'ok' => true,
            'created' => false,
            'conversation' => $conversation,
        ]);
    }

    $pdo->beginTransaction();
    try {
        $insertConversation = $pdo->prepare("
            INSERT INTO chat_conversations (conversation_type, title, created_by_user_id, last_message_at, status, created_at, updated_at)
            VALUES ('direct', NULL, ?, NULL, 'active', NOW(), NOW())
        ");
        $insertConversation->execute([$currentUserId]);
        $conversationId = (int)$pdo->lastInsertId();

        $insertMember = $pdo->prepare("
            INSERT INTO chat_conversation_members (conversation_id, user_id, member_role, joined_at, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW(), NOW())
        ");
        $insertMember->execute([$conversationId, $currentUserId, 'owner']);
        $insertMember->execute([$conversationId, $targetUserId, 'member']);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        forum_json(['ok' => false, 'message' => 'Failed to start direct chat.'], 500);
    }

    $conversation = chat_load_conversation($pdo, $conversationId, $currentUserId);
    forum_realtime_publish('chat.conversation.created', [
        'conversationId' => $conversationId,
        'conversationType' => 'direct',
    ]);
    forum_json([
        'ok' => true,
        'created' => true,
        'conversation' => $conversation,
    ], 201);
}

if ($action === 'group') {
    $memberIds = array_values(array_unique(array_map('intval', (array)($input['memberIds'] ?? []))));
    $sourceConversationId = (int)($input['conversationId'] ?? 0);
    $title = trim((string)($input['title'] ?? ''));

    if ($sourceConversationId > 0) {
        $sourceAccessStmt = $pdo->prepare("
            SELECT conversation_id
            FROM chat_conversation_members
            WHERE conversation_id = ?
              AND user_id = ?
            LIMIT 1
        ");
        $sourceAccessStmt->execute([$sourceConversationId, $currentUserId]);
        if (!$sourceAccessStmt->fetch()) {
            forum_json(['ok' => false, 'message' => 'Source conversation not found.'], 404);
        }

        $sourceMembersStmt = $pdo->prepare("
            SELECT user_id
            FROM chat_conversation_members
            WHERE conversation_id = ?
        ");
        $sourceMembersStmt->execute([$sourceConversationId]);
        foreach ($sourceMembersStmt->fetchAll(PDO::FETCH_COLUMN) as $memberId) {
            $memberIds[] = (int)$memberId;
        }
    }

    $memberIds[] = $currentUserId;
    $memberIds = array_values(array_unique(array_filter($memberIds, static fn(int $id): bool => $id > 0)));

    if (count($memberIds) < 3) {
        forum_json(['ok' => false, 'message' => 'Group chat needs at least three members.'], 422);
    }

    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $usersStmt = $pdo->prepare("
        SELECT user_id
        FROM users
        WHERE user_id IN ({$placeholders})
    ");
    $usersStmt->execute($memberIds);
    $validUserIds = array_map('intval', $usersStmt->fetchAll(PDO::FETCH_COLUMN));
    sort($validUserIds);
    $expectedUserIds = $memberIds;
    sort($expectedUserIds);
    if ($validUserIds !== $expectedUserIds) {
        forum_json(['ok' => false, 'message' => 'One or more members do not exist.'], 404);
    }

    $pdo->beginTransaction();
    try {
        $insertConversation = $pdo->prepare("
            INSERT INTO chat_conversations (conversation_type, title, created_by_user_id, last_message_at, status, created_at, updated_at)
            VALUES ('group', ?, ?, NULL, 'active', NOW(), NOW())
        ");
        $insertConversation->execute([$title !== '' ? $title : null, $currentUserId]);
        $conversationId = (int)$pdo->lastInsertId();

        $insertMember = $pdo->prepare("
            INSERT INTO chat_conversation_members (conversation_id, user_id, member_role, joined_at, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW(), NOW())
        ");
        foreach ($memberIds as $memberId) {
            $insertMember->execute([$conversationId, $memberId, $memberId === $currentUserId ? 'owner' : 'member']);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        forum_json(['ok' => false, 'message' => 'Failed to create group chat.'], 500);
    }

    $conversation = chat_load_conversation($pdo, $conversationId, $currentUserId);
    forum_realtime_publish('chat.conversation.created', [
        'conversationId' => $conversationId,
        'conversationType' => 'group',
    ]);
    forum_json([
        'ok' => true,
        'created' => true,
        'conversation' => $conversation,
    ], 201);
}

if ($action === 'rename') {
    $conversationId = (int)($input['conversationId'] ?? 0);
    $title = trim((string)($input['title'] ?? ''));

    if ($conversationId <= 0) {
        forum_json(['ok' => false, 'message' => 'Conversation id is required.'], 422);
    }

    $accessStmt = $pdo->prepare("
        SELECT c.conversation_id, c.conversation_type
        FROM chat_conversation_members cm
        JOIN chat_conversations c ON c.conversation_id = cm.conversation_id
        WHERE cm.conversation_id = ?
          AND cm.user_id = ?
          AND c.status = 'active'
        LIMIT 1
    ");
    $accessStmt->execute([$conversationId, $currentUserId]);
    $conversationRow = $accessStmt->fetch();
    if (!$conversationRow) {
        forum_json(['ok' => false, 'message' => 'Conversation not found.'], 404);
    }
    if ((string)$conversationRow['conversation_type'] !== 'group') {
        forum_json(['ok' => false, 'message' => 'Only group chats can be renamed.'], 422);
    }

    $updateStmt = $pdo->prepare("
        UPDATE chat_conversations
        SET title = ?, updated_at = NOW()
        WHERE conversation_id = ?
    ");
    $updateStmt->execute([$title !== '' ? $title : null, $conversationId]);

    $conversation = chat_load_conversation($pdo, $conversationId, $currentUserId);
    forum_realtime_publish('chat.conversation.updated', [
        'conversationId' => $conversationId,
        'conversationType' => 'group',
    ]);
    forum_json([
        'ok' => true,
        'conversation' => $conversation,
    ]);
}

if ($action === 'delete') {
    $conversationId = (int)($input['conversationId'] ?? 0);
    if ($conversationId <= 0) {
        forum_json(['ok' => false, 'message' => 'Conversation id is required.'], 422);
    }

    $metaStmt = $pdo->prepare("
        SELECT c.conversation_id, c.conversation_type
        FROM chat_conversation_members cm
        JOIN chat_conversations c ON c.conversation_id = cm.conversation_id
        WHERE cm.conversation_id = ?
          AND cm.user_id = ?
          AND c.status = 'active'
        LIMIT 1
    ");
    $metaStmt->execute([$conversationId, $currentUserId]);
    $conversationRow = $metaStmt->fetch();
    if (!$conversationRow) {
        forum_json(['ok' => false, 'message' => 'Conversation not found.'], 404);
    }

    $pdo->beginTransaction();
    try {
        $deleteMemberStmt = $pdo->prepare("
            DELETE FROM chat_conversation_members
            WHERE conversation_id = ?
              AND user_id = ?
        ");
        $deleteMemberStmt->execute([$conversationId, $currentUserId]);

        $remainingStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM chat_conversation_members
            WHERE conversation_id = ?
        ");
        $remainingStmt->execute([$conversationId]);
        $remainingCount = (int)$remainingStmt->fetchColumn();

        $conversationType = (string)$conversationRow['conversation_type'];
        if ($remainingCount === 0 || ($conversationType === 'direct' && $remainingCount < 2)) {
            $archiveStmt = $pdo->prepare("
                UPDATE chat_conversations
                SET status = 'archived', updated_at = NOW()
                WHERE conversation_id = ?
            ");
            $archiveStmt->execute([$conversationId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        forum_json(['ok' => false, 'message' => 'Failed to delete conversation.'], 500);
    }

    forum_realtime_publish('chat.conversation.deleted', [
        'conversationId' => $conversationId,
        'conversationType' => (string)$conversationRow['conversation_type'],
        'deletedByUserId' => $currentUserId,
    ]);
    forum_json([
        'ok' => true,
        'conversationId' => $conversationId,
    ]);
}

forum_json(['ok' => false, 'message' => 'Unsupported action.'], 422);
