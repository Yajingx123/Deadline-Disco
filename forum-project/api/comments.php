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
        SELECT post_id
        FROM forum_posts
        WHERE post_id = ? AND status = 'active'
        LIMIT 1
    ");
    $postCheck->execute([$postId]);
    if (!$postCheck->fetch()) {
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
