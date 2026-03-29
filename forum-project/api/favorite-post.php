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
    SELECT favorite_id 
    FROM forum_post_favorites 
    WHERE post_id = ? AND user_id = ?
");
$checkStmt->execute([$postId, $user['user_id']]);
$existingFavorite = $checkStmt->fetch();

$pdo->beginTransaction();
try {
    if ($existingFavorite) {
        $deleteStmt = $pdo->prepare("DELETE FROM forum_post_favorites WHERE favorite_id = ?");
        $deleteStmt->execute([$existingFavorite['favorite_id']]);

        $updateStmt = $pdo->prepare("
            UPDATE forum_posts 
            SET favorite_count = GREATEST(favorite_count - 1, 0) 
            WHERE post_id = ?
        ");
        $updateStmt->execute([$postId]);

        $favorited = false;
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO forum_post_favorites (post_id, user_id) 
            VALUES (?, ?)
        ");
        $insertStmt->execute([$postId, $user['user_id']]);

        $updateStmt = $pdo->prepare("
            UPDATE forum_posts 
            SET favorite_count = favorite_count + 1 
            WHERE post_id = ?
        ");
        $updateStmt->execute([$postId]);

        $favorited = true;
    }

    $countStmt = $pdo->prepare("SELECT favorite_count FROM forum_posts WHERE post_id = ?");
    $countStmt->execute([$postId]);
    $countRow = $countStmt->fetch();
    $favoriteCount = (int)($countRow['favorite_count'] ?? 0);

    $pdo->commit();

    forum_json([
        'ok' => true,
        'favorited' => $favorited,
        'favoriteCount' => $favoriteCount,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    forum_json(['ok' => false, 'message' => 'Failed to update favorite status.'], 500);
}
