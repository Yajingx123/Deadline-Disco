<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = forum_require_user();
$input = forum_input();
$postId = (int)($input['postId'] ?? 0);

if ($postId <= 0) {
    forum_json(['ok' => false, 'message' => 'Invalid post id.'], 422);
}

$pdo = forum_db();
$stmt = $pdo->prepare("SELECT user_id FROM forum_posts WHERE post_id = ? LIMIT 1");
$stmt->execute([$postId]);
$row = $stmt->fetch();

if (!$row) {
    forum_json(['ok' => false, 'message' => 'Post not found.'], 404);
}

if ((int)$row['user_id'] !== (int)$user['user_id']) {
    forum_json(['ok' => false, 'message' => 'Only the author can delete this post.'], 403);
}

$mediaUrls = [];

$pdo->beginTransaction();
try {
    $postMediaStmt = $pdo->prepare("
        SELECT media_url
        FROM forum_post_media
        WHERE post_id = ?
    ");
    $postMediaStmt->execute([$postId]);
    $mediaUrls = array_map('strval', array_column($postMediaStmt->fetchAll(), 'media_url'));

    $commentMediaStmt = $pdo->prepare("
        SELECT fcm.media_url
        FROM forum_comment_media fcm
        JOIN forum_comments fc ON fc.comment_id = fcm.comment_id
        WHERE fc.post_id = ?
    ");
    $commentMediaStmt->execute([$postId]);
    $mediaUrls = array_merge($mediaUrls, array_map('strval', array_column($commentMediaStmt->fetchAll(), 'media_url')));

    $delete = $pdo->prepare("DELETE FROM forum_posts WHERE post_id = ?");
    $delete->execute([$postId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    forum_json(['ok' => false, 'message' => 'Failed to delete post.'], 500);
}

$unusedMediaUrls = forum_collect_unused_media_urls($pdo, $mediaUrls);
forum_delete_uploaded_files($unusedMediaUrls);
forum_realtime_publish('forum.post.deleted', [
    'postId' => $postId,
]);

forum_json([
    'ok' => true,
    'postId' => $postId,
]);
