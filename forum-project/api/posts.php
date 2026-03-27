<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = forum_db();

if ($method === 'GET') {
    forum_require_user();

    $search = trim((string)($_GET['q'] ?? ''));
    $labelsParam = trim((string)($_GET['labels'] ?? ''));
    $sort = trim((string)($_GET['sort'] ?? 'latest_reply'));
    $labels = array_values(array_filter(array_map('trim', explode(',', $labelsParam))));

    $conditions = ["fp.status = 'active'"];
    $params = [];

    if ($search !== '') {
        $conditions[] = '(fp.title LIKE ? OR fp.content_text LIKE ? OR u.username LIKE ? OR CAST(fp.post_id AS CHAR) = ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $search);
    }
    if ($labels) {
        $placeholders = implode(',', array_fill(0, count($labels), '?'));
        $conditions[] = "EXISTS (
            SELECT 1
            FROM forum_post_labels fpl2
            JOIN forum_labels fl2 ON fl2.label_id = fpl2.label_id
            WHERE fpl2.post_id = fp.post_id
              AND fl2.name IN ({$placeholders})
        )";
        array_push($params, ...$labels);
    }

    $orderBy = match ($sort) {
        'latest_post' => 'fp.created_at DESC',
        'most_viewed' => 'fp.view_count DESC, fp.last_commented_at DESC, fp.created_at DESC',
        default => 'fp.last_commented_at DESC, fp.created_at DESC',
    };

    $sql = "
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
        WHERE " . implode(' AND ', $conditions) . "
        GROUP BY fp.post_id, fp.user_id, fp.title, fp.content_text, fp.view_count, fp.comment_count, fp.created_at, u.username
        ORDER BY {$orderBy}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = array_map('forum_post_row_to_payload', $stmt->fetchAll());

    forum_json([
        'ok' => true,
        'posts' => $posts,
    ]);
}

if ($method !== 'POST') {
    forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = forum_require_user();
$input = forum_input();
$title = trim((string)($input['title'] ?? ''));
$content = trim((string)($input['content'] ?? ''));
$labelNames = array_values(array_unique(array_filter(array_map('trim', (array)($input['labels'] ?? [])))));

if ($title === '' || $content === '') {
    forum_json(['ok' => false, 'message' => 'Title and content are required.'], 422);
}

$pdo->beginTransaction();
try {
    $insertPost = $pdo->prepare("
        INSERT INTO forum_posts (user_id, title, content_text, view_count, comment_count, status, created_at, updated_at)
        VALUES (?, ?, ?, 0, 0, 'active', NOW(), NOW())
    ");
    $insertPost->execute([(int)$user['user_id'], $title, $content]);
    $postId = (int)$pdo->lastInsertId();

    if ($labelNames) {
        $placeholders = implode(',', array_fill(0, count($labelNames), '?'));
        $labelStmt = $pdo->prepare("SELECT label_id, name FROM forum_labels WHERE name IN ({$placeholders})");
        $labelStmt->execute($labelNames);
        $labelRows = $labelStmt->fetchAll();
        $linkStmt = $pdo->prepare("INSERT INTO forum_post_labels (post_id, label_id) VALUES (?, ?)");
        foreach ($labelRows as $labelRow) {
            $linkStmt->execute([$postId, (int)$labelRow['label_id']]);
        }
    }

    $mediaRows = forum_extract_media_rows($content);
    if ($mediaRows) {
        $mediaStmt = $pdo->prepare("
            INSERT INTO forum_post_media (post_id, media_type, media_url, order_index)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($mediaRows as $mediaRow) {
            $mediaStmt->execute([$postId, $mediaRow['media_type'], $mediaRow['media_url'], $mediaRow['order_index']]);
        }
    }

    $pdo->commit();

    $stmt = $pdo->prepare("
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
        WHERE fp.post_id = ?
        GROUP BY fp.post_id, fp.user_id, fp.title, fp.content_text, fp.view_count, fp.comment_count, fp.created_at, u.username
    ");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    forum_realtime_publish('forum.post.created', [
        'postId' => $postId,
    ]);

    forum_json([
        'ok' => true,
        'post' => $post ? forum_post_row_to_payload($post) : null,
    ], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    forum_json(['ok' => false, 'message' => 'Failed to create post.'], 500);
}
