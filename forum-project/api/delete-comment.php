<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = forum_require_user();
$input = forum_input();
$commentId = (int)($input['commentId'] ?? 0);

if ($commentId <= 0) {
    forum_json(['ok' => false, 'message' => 'Invalid comment id.'], 422);
}

$pdo = forum_db();
$stmt = $pdo->prepare("SELECT comment_id, post_id, user_id FROM forum_comments WHERE comment_id = ? LIMIT 1");
$stmt->execute([$commentId]);
$row = $stmt->fetch();

if (!$row) {
    forum_json(['ok' => false, 'message' => 'Comment not found.'], 404);
}

if ((int)$row['user_id'] !== (int)$user['user_id']) {
    forum_json(['ok' => false, 'message' => 'Only the author can delete this comment.'], 403);
}

$pdo->beginTransaction();
$mediaUrls = [];
try {
    $countStmt = $pdo->prepare("
        WITH RECURSIVE comment_tree AS (
            SELECT comment_id
            FROM forum_comments
            WHERE comment_id = ?
            UNION ALL
            SELECT child.comment_id
            FROM forum_comments child
            JOIN comment_tree parent_tree ON child.parent_comment_id = parent_tree.comment_id
        )
        SELECT COUNT(*) AS total_comments
        FROM comment_tree
    ");
    $countStmt->execute([$commentId]);
    $deletedRow = $countStmt->fetch();
    $deletedCount = (int)($deletedRow['total_comments'] ?? 1);

    $idsStmt = $pdo->prepare("
        WITH RECURSIVE comment_tree AS (
            SELECT comment_id
            FROM forum_comments
            WHERE comment_id = ?
            UNION ALL
            SELECT child.comment_id
            FROM forum_comments child
            JOIN comment_tree parent_tree ON child.parent_comment_id = parent_tree.comment_id
        )
        SELECT comment_id
        FROM comment_tree
    ");
    $idsStmt->execute([$commentId]);
    $deletedIds = array_map('intval', array_column($idsStmt->fetchAll(), 'comment_id'));

    if ($deletedIds) {
        $mediaPlaceholders = implode(',', array_fill(0, count($deletedIds), '?'));
        $mediaStmt = $pdo->prepare("
            SELECT media_url
            FROM forum_comment_media
            WHERE comment_id IN ($mediaPlaceholders)
        ");
        $mediaStmt->execute($deletedIds);
        $mediaUrls = array_map('strval', array_column($mediaStmt->fetchAll(), 'media_url'));
    }

    $delete = $pdo->prepare("DELETE FROM forum_comments WHERE comment_id = ?");
    $delete->execute([$commentId]);

    $update = $pdo->prepare("
        UPDATE forum_posts
        SET comment_count = GREATEST(comment_count - ?, 0), updated_at = NOW()
        WHERE post_id = ?
    ");
    $update->execute([$deletedCount, (int)$row['post_id']]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    forum_json(['ok' => false, 'message' => 'Failed to delete comment.'], 500);
}

$unusedMediaUrls = forum_collect_unused_media_urls($pdo, $mediaUrls);
forum_delete_uploaded_files($unusedMediaUrls);
forum_realtime_publish('forum.comment.deleted', [
    'postId' => (int)$row['post_id'],
    'commentId' => $commentId,
    'deletedCommentIds' => $deletedIds,
]);

forum_json([
    'ok' => true,
    'commentId' => $commentId,
    'deletedCommentIds' => $deletedIds,
    'postId' => (int)$row['post_id'],
]);
