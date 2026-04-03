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
$postOwnerStmt = $pdo->prepare("SELECT user_id, title FROM forum_posts WHERE post_id = ? LIMIT 1");
$postOwnerStmt->execute([$postId]);
$postOwner = $postOwnerStmt->fetch();
if (!$postOwner) {
    forum_json(['ok' => false, 'message' => 'Post not found.'], 404);
}
$postOwnerId = (int)($postOwner['user_id'] ?? 0);

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

        try {
            $deleteNotificationStmt = $pdo->prepare("
                DELETE FROM message_center_notifications
                WHERE recipient_user_id = ?
                  AND actor_user_id = ?
                  AND post_id = ?
                  AND notification_type = 'favorite'
            ");
            $deleteNotificationStmt->execute([$postOwnerId, (int)$user['user_id'], $postId]);
        } catch (Throwable $notificationError) {
            error_log('[forum favorite notification delete] ' . $notificationError->getMessage());
        }

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

        if ($postOwnerId > 0 && $postOwnerId !== (int)$user['user_id']) {
            try {
                $notificationStmt = $pdo->prepare("
                    INSERT INTO message_center_notifications (
                        recipient_user_id,
                        actor_user_id,
                        notification_type,
                        post_id,
                        title,
                        body_text,
                        cta_label,
                        cta_url,
                        is_read,
                        created_at,
                        updated_at
                    )
                    VALUES (?, ?, 'favorite', ?, ?, ?, 'View post', ?, 0, NOW(), NOW())
                ");
                $notificationStmt->execute([
                    $postOwnerId,
                    (int)$user['user_id'],
                    $postId,
                    sprintf('%s favorited your post', (string)($user['username'] ?? 'Someone')),
                    (string)($postOwner['title'] ?? ''),
                    sprintf('http://127.0.0.1:8001/forum-project/dist/index.html?view=forum&postId=%d', $postId),
                ]);
            } catch (Throwable $notificationError) {
                error_log('[forum favorite notification insert] ' . $notificationError->getMessage());
            }
        }

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

    if ($postOwnerId > 0 && $postOwnerId !== (int)$user['user_id']) {
        forum_realtime_publish('message-center.updated', [
            'recipientUserId' => $postOwnerId,
            'category' => 'reactions',
        ]);
    }

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
