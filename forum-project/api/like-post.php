<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = forum_require_user();
$input = forum_input();
$postId = (int)($input['postId'] ?? 0);

if ($postId <= 0) {
    forum_json(['ok' => false, 'message' => 'Post id is required.'], 422);
}

$pdo = forum_db();

$checkStmt = $pdo->prepare("
    SELECT like_id 
    FROM forum_post_likes 
    WHERE post_id = ? AND user_id = ?
");
$checkStmt->execute([$postId, $user['user_id']]);
$existingLike = $checkStmt->fetch();

$pdo->beginTransaction();
try {
    if ($existingLike) {
        $deleteStmt = $pdo->prepare("DELETE FROM forum_post_likes WHERE like_id = ?");
        $deleteStmt->execute([$existingLike['like_id']]);

        $updateStmt = $pdo->prepare("
            UPDATE forum_posts 
            SET like_count = GREATEST(like_count - 1, 0) 
            WHERE post_id = ?
        ");
        $updateStmt->execute([$postId]);

        $liked = false;
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO forum_post_likes (post_id, user_id) 
            VALUES (?, ?)
        ");
        $insertStmt->execute([$postId, $user['user_id']]);

        $updateStmt = $pdo->prepare("
            UPDATE forum_posts 
            SET like_count = like_count + 1 
            WHERE post_id = ?
        ");
        $updateStmt->execute([$postId]);

        $liked = true;
    }

    $countStmt = $pdo->prepare("SELECT like_count FROM forum_posts WHERE post_id = ?");
    $countStmt->execute([$postId]);
    $countRow = $countStmt->fetch();
    $likeCount = (int)($countRow['like_count'] ?? 0);

    $pdo->commit();

    forum_json([
        'ok' => true,
        'liked' => $liked,
        'likeCount' => $likeCount,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    forum_json(['ok' => false, 'message' => 'Failed to update like status.'], 500);
}
