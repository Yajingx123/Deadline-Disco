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
            fp.status,
            fp.created_at,
            u.username AS author_name,
            COALESCE(MIN(fpm.media_type), 'text') AS media_type,
            GROUP_CONCAT(DISTINCT fl.name ORDER BY fl.name SEPARATOR '||') AS labels
        FROM forum_posts fp
        JOIN users u ON u.user_id = fp.user_id
        LEFT JOIN forum_post_media fpm ON fpm.post_id = fp.post_id
        LEFT JOIN forum_post_labels fpl ON fpl.post_id = fp.post_id
        LEFT JOIN forum_labels fl ON fl.label_id = fpl.label_id
        WHERE fp.user_id = ?
        GROUP BY fp.post_id, fp.user_id, fp.title, fp.content_text, fp.view_count, fp.comment_count, fp.like_count, fp.favorite_count, fp.status, fp.created_at, u.username
        ORDER BY fp.created_at DESC
    ");
    $stmt->execute([$user['user_id']]);
    $posts = array_map(function($row) {
        $payload = forum_post_row_to_payload($row);
        $payload['status'] = $row['status'] ?? 'Under review';
        return $payload;
    }, $stmt->fetchAll());

    forum_json([
        'ok' => true,
        'posts' => $posts,
    ]);
}

forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
