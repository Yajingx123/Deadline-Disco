<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = forum_require_user();
$input = forum_input();
$postId = (int)($input['postId'] ?? 0);
$content = trim((string)($input['content'] ?? ''));
$parentCommentId = (int)($input['parentCommentId'] ?? 0);

if ($postId <= 0 || $content === '') {
    forum_json(['ok' => false, 'message' => 'Post and content are required.'], 422);
}

$pdo = forum_db();
$pdo->beginTransaction();
try {
    $postCheck = $pdo->prepare("
        SELECT post_id, user_id, title
        FROM forum_posts
        WHERE post_id = ? AND status = 'active'
        LIMIT 1
    ");
    $postCheck->execute([$postId]);
    $postRow = $postCheck->fetch();
    if (!$postRow) {
        forum_json(['ok' => false, 'message' => 'Post not found.'], 404);
    }

    $insert = $pdo->prepare("
        INSERT INTO forum_comments (post_id, user_id, parent_comment_id, content_text, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
    ");
    $insert->execute([
        $postId,
        (int)$user['user_id'],
        $parentCommentId > 0 ? $parentCommentId : null,
        $content,
    ]);

    $updatePost = $pdo->prepare("
        UPDATE forum_posts
        SET comment_count = comment_count + 1, last_commented_at = NOW(), updated_at = NOW()
        WHERE post_id = ?
    ");
    $updatePost->execute([$postId]);

    $commentId = (int)$pdo->lastInsertId();

    $parentCommentRow = null;
    if ($parentCommentId > 0) {
        $parentStmt = $pdo->prepare("
            SELECT c.comment_id, c.user_id, c.content_text, u.username
            FROM forum_comments c
            JOIN users u ON u.user_id = c.user_id
            WHERE c.comment_id = ? AND c.post_id = ? AND c.status = 'active'
            LIMIT 1
        ");
        $parentStmt->execute([$parentCommentId, $postId]);
        $parentCommentRow = $parentStmt->fetch() ?: null;
    }

    $mediaRows = forum_extract_media_rows($content);
    if ($mediaRows) {
        try {
            $mediaStmt = $pdo->prepare("
                INSERT INTO forum_comment_media (comment_id, media_type, media_url, order_index)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($mediaRows as $mediaRow) {
                $mediaStmt->execute([$commentId, $mediaRow['media_type'], $mediaRow['media_url'], $mediaRow['order_index']]);
            }
        } catch (Throwable $mediaError) {
            error_log('[forum comments media] ' . $mediaError->getMessage());
        }
    }

    $postOwnerId = (int)($postRow['user_id'] ?? 0);
    if ($postOwnerId > 0 && $postOwnerId !== (int)$user['user_id']) {
        try {
            $notificationStmt = $pdo->prepare("
                INSERT INTO message_center_notifications (
                    recipient_user_id,
                    actor_user_id,
                    notification_type,
                    post_id,
                    comment_id,
                    title,
                    body_text,
                    cta_label,
                    cta_url,
                    is_read,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, 'reply', ?, ?, ?, ?, 'Reply', ?, 0, NOW(), NOW())
            ");
            $notificationStmt->execute([
                $postOwnerId,
                (int)$user['user_id'],
                $postId,
                $commentId,
                sprintf('%s replied to your post', (string)($user['username'] ?? 'Someone')),
                forum_plain_text_preview($content, 180),
                sprintf('http://127.0.0.1:8001/forum-project/dist/index.html?view=forum&postId=%d', $postId),
            ]);
        } catch (Throwable $notificationError) {
            error_log('[forum comment notification] ' . $notificationError->getMessage());
        }
    }

    $parentOwnerId = (int)($parentCommentRow['user_id'] ?? 0);
    if (
        $parentOwnerId > 0
        && $parentOwnerId !== (int)$user['user_id']
        && $parentOwnerId !== $postOwnerId
    ) {
        try {
            $notificationStmt = $pdo->prepare("
                INSERT INTO message_center_notifications (
                    recipient_user_id,
                    actor_user_id,
                    notification_type,
                    post_id,
                    comment_id,
                    title,
                    body_text,
                    cta_label,
                    cta_url,
                    is_read,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, 'reply', ?, ?, ?, ?, 'Reply', ?, 0, NOW(), NOW())
            ");
            $notificationStmt->execute([
                $parentOwnerId,
                (int)$user['user_id'],
                $postId,
                $commentId,
                sprintf('%s replied to your comment', (string)($user['username'] ?? 'Someone')),
                forum_plain_text_preview($content, 180),
                sprintf('http://127.0.0.1:8001/forum-project/dist/index.html?view=forum&postId=%d', $postId),
            ]);
        } catch (Throwable $notificationError) {
            error_log('[forum comment parent notification] ' . $notificationError->getMessage());
        }
    }

    $pdo->commit();

    $stmt = $pdo->prepare("
        SELECT
            fc.comment_id,
            fc.user_id AS author_user_id,
            fc.content_text,
            fc.created_at,
            u.username AS author_name,
            parent.comment_id AS parent_comment_id,
            parent.content_text AS parent_content,
            parent_user.username AS parent_author_name
        FROM forum_comments fc
        JOIN users u ON u.user_id = fc.user_id
        LEFT JOIN forum_comments parent ON parent.comment_id = fc.parent_comment_id
        LEFT JOIN users parent_user ON parent_user.user_id = parent.user_id
        WHERE fc.comment_id = ?
        LIMIT 1
    ");
    $stmt->execute([$commentId]);
    $row = $stmt->fetch();
    if (!$row) {
        forum_realtime_publish('forum.comment.created', [
            'postId' => $postId,
            'commentId' => $commentId,
        ]);
        forum_realtime_publish('message-center.updated', [
            'recipientUserId' => (int)($postRow['user_id'] ?? 0),
            'category' => 'replies',
        ]);
        forum_json([
            'ok' => true,
            'comment' => [
                'id' => $commentId,
                'author' => (string)($user['username'] ?? 'Unknown'),
                'authorUserId' => (int)($user['user_id'] ?? 0),
                'avatar' => strtoupper(substr((string)($user['username'] ?? 'U'), 0, 1)),
                'time' => forum_format_datetime(date('Y-m-d H:i:s')),
                'content' => $content,
                'replyTo' => null,
            ],
        ], 201);
    }

    forum_realtime_publish('forum.comment.created', [
        'postId' => $postId,
        'commentId' => $commentId,
    ]);
    if ((int)($postRow['user_id'] ?? 0) > 0 && (int)($postRow['user_id'] ?? 0) !== (int)$user['user_id']) {
        forum_realtime_publish('message-center.updated', [
            'recipientUserId' => (int)$postRow['user_id'],
            'category' => 'replies',
        ]);
    }
    if (
        (int)($parentCommentRow['user_id'] ?? 0) > 0
        && (int)($parentCommentRow['user_id'] ?? 0) !== (int)$user['user_id']
        && (int)($parentCommentRow['user_id'] ?? 0) !== (int)($postRow['user_id'] ?? 0)
    ) {
        forum_realtime_publish('message-center.updated', [
            'recipientUserId' => (int)$parentCommentRow['user_id'],
            'category' => 'replies',
        ]);
    }

    forum_json([
        'ok' => true,
        'comment' => [
            'id' => (int)$row['comment_id'],
            'author' => (string)$row['author_name'],
            'authorUserId' => (int)($row['author_user_id'] ?? 0),
            'avatar' => strtoupper(substr((string)$row['author_name'], 0, 1)),
            'time' => forum_format_datetime((string)($row['created_at'] ?? '')),
            'content' => (string)($row['content_text'] ?? ''),
            'replyTo' => $row['parent_comment_id'] ? [
                'id' => (int)$row['parent_comment_id'],
                'author' => (string)($row['parent_author_name'] ?? ''),
                'content' => (string)($row['parent_content'] ?? ''),
            ] : null,
        ],
    ], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[forum comments] ' . $e->getMessage());
    forum_json(['ok' => false, 'message' => 'Failed to save comment.'], 500);
}
