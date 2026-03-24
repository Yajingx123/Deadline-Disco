<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
forum_require_user();

$postId = (int)($_GET['id'] ?? 0);
if ($postId <= 0) {
    forum_json(['ok' => false, 'message' => 'Invalid post id.'], 422);
}

$pdo = forum_db();

$postStmt = $pdo->prepare("
    SELECT
        fp.post_id,
        fp.user_id AS author_user_id,
        fp.title,
        fp.content_text,
        fp.view_count,
        fp.comment_count,
        fp.created_at,
        u.username AS author_name,
        COALESCE(MIN(fpm.media_type), 'text') AS media_type,
        GROUP_CONCAT(DISTINCT fl.name ORDER BY fl.name SEPARATOR '||') AS labels
    FROM forum_posts fp
    JOIN users u ON u.user_id = fp.user_id
    LEFT JOIN forum_post_media fpm ON fpm.post_id = fp.post_id
    LEFT JOIN forum_post_labels fpl ON fpl.post_id = fp.post_id
    LEFT JOIN forum_labels fl ON fl.label_id = fpl.label_id
    WHERE fp.post_id = ? AND fp.status = 'active'
    GROUP BY fp.post_id, fp.user_id, fp.title, fp.content_text, fp.view_count, fp.comment_count, fp.created_at, u.username
    LIMIT 1
");
$postStmt->execute([$postId]);
$postRow = $postStmt->fetch();
if (!$postRow) {
    forum_json(['ok' => false, 'message' => 'Post not found.'], 404);
}

$commentStmt = $pdo->prepare("
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
    WHERE fc.post_id = ? AND fc.status = 'active'
    ORDER BY fc.created_at ASC
");
$commentStmt->execute([$postId]);
$comments = array_map(static function(array $row): array {
    return [
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
    ];
}, $commentStmt->fetchAll());

$post = forum_post_row_to_payload($postRow);
$post['comments'] = $comments;

forum_json([
    'ok' => true,
    'post' => $post,
]);
