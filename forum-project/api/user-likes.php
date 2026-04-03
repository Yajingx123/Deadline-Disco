<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = forum_require_user();
$pdo = forum_db();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT
            fp.post_id,
            fp.user_id AS author_user_id,
            fp.title,
            fp.content_text,
            fp.view_count,
            fp.comment_count,
            fp.like_count,
            fp.favorite_count,
            fp.created_at,
            u.username AS author_name,
            COALESCE(MIN(fpm.media_type), 'text') AS media_type,
            GROUP_CONCAT(DISTINCT fl.name ORDER BY fl.name SEPARATOR '||') AS labels
        FROM forum_post_likes fpl
        JOIN forum_posts fp ON fp.post_id = fpl.post_id
        JOIN users u ON u.user_id = fp.user_id
        LEFT JOIN forum_post_media fpm ON fpm.post_id = fp.post_id
        LEFT JOIN forum_post_labels fpl2 ON fpl2.post_id = fp.post_id
        LEFT JOIN forum_labels fl ON fl.label_id = fpl2.label_id
        WHERE fpl.user_id = ? AND fp.status = 'active'
        GROUP BY fp.post_id, fp.user_id, fp.title, fp.content_text, fp.view_count, fp.comment_count, fp.like_count, fp.favorite_count, fp.created_at, u.username
        ORDER BY MAX(fpl.created_at) DESC
    ");
    $stmt->execute([$user['user_id']]);
    $posts = array_map('forum_post_row_to_payload', $stmt->fetchAll());

    forum_json([
        'ok' => true,
        'posts' => $posts,
    ]);
}

forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
