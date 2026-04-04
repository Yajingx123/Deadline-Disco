<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = forum_db();
forum_ensure_forum_post_announcement_schema($pdo);

if ($method === 'GET') {
    forum_require_user();

    $search = trim((string)($_GET['q'] ?? ''));
    $labelsParam = trim((string)($_GET['labels'] ?? ''));
    $sort = trim((string)($_GET['sort'] ?? 'latest_post'));
    $labels = array_values(array_filter(array_map('trim', explode(',', $labelsParam))));

    $ann = FORUM_ANNOUNCEMENT_LABEL_NAME;
    $hasLabels = $labels !== [];
    $announcementSelected = $hasLabels && in_array($ann, $labels, true);
    // 无 label 筛选时的语义：看所有消息（包含非置顶公告）。
    $showUnpinnedAnnouncement = !$hasLabels || $announcementSelected;

    $existsAnnouncement = "
        EXISTS (
            SELECT 1
            FROM forum_post_labels fpl_a
            JOIN forum_labels fl_a ON fl_a.label_id = fpl_a.label_id
            WHERE fpl_a.post_id = fp.post_id
              AND BINARY fl_a.name = ?
        )
    ";

    $conditions = ["fp.status = 'active'"];
    $params = [];

    if ($search !== '') {
        $conditions[] = '(fp.title LIKE ? OR fp.content_text LIKE ? OR u.username LIKE ? OR CAST(fp.post_id AS CHAR) = ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $search);
    }

    $labelFilterSql = '';
    if ($hasLabels) {
        $placeholders = implode(',', array_fill(0, count($labels), '?'));
        $labelFilterSql = "
            EXISTS (
                SELECT 1
                FROM forum_post_labels fpl2
                JOIN forum_labels fl2 ON fl2.label_id = fpl2.label_id
                WHERE fpl2.post_id = fp.post_id
                  AND fl2.name IN ({$placeholders})
            )
        ";
    }

    // 普通帖：非 Announcement；置顶公告：无论筛选如何都出现；非置顶公告：仅当筛选包含 Announcement 时出现。
    $branchNormal = '
        NOT (' . $existsAnnouncement . ')
        AND COALESCE(fp.is_pinned, 0) = 0
    ';
    $params[] = $ann;
    if ($hasLabels) {
        $branchNormal .= ' AND ' . $labelFilterSql;
        array_push($params, ...$labels);
    }

    $branchPinnedAnn = '
        (' . $existsAnnouncement . ')
        AND COALESCE(fp.is_pinned, 0) = 1
    ';
    $params[] = $ann;

    $branchUnpinnedAnn = '
        (' . $existsAnnouncement . ')
        AND COALESCE(fp.is_pinned, 0) = 0
        ' . ($showUnpinnedAnnouncement ? '' : 'AND 0 = 1') . '
    ';
    $params[] = $ann;

    $conditions[] = '((' . trim($branchNormal) . ') OR (' . trim($branchPinnedAnn) . ') OR (' . trim($branchUnpinnedAnn) . '))';

    $orderBy = match ($sort) {
        'latest_post' => 'fp.is_pinned DESC, fp.created_at DESC',
        'most_viewed' => 'fp.is_pinned DESC, fp.view_count DESC, fp.last_commented_at DESC, fp.created_at DESC',
        default => 'fp.is_pinned DESC, COALESCE(fp.last_commented_at, fp.created_at) DESC, fp.created_at DESC',
    };

    $sql = "
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
            fp.is_pinned,
            fp.last_commented_at,
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
        GROUP BY fp.post_id, fp.user_id, fp.title, fp.content_text, fp.view_count, fp.comment_count, fp.like_count, fp.favorite_count, fp.status, fp.is_pinned, fp.last_commented_at, fp.created_at, u.username
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

if (!forum_is_admin($user)) {
    $labelNames = array_values(array_filter(
        $labelNames,
        static fn(string $label): bool => $label !== FORUM_ANNOUNCEMENT_LABEL_NAME
    ));
} else {
    $labelNames = array_values(array_filter(
        $labelNames,
        static fn(string $label): bool => $label === FORUM_ANNOUNCEMENT_LABEL_NAME
    ));
}

if ($title === '' || $content === '') {
    forum_json(['ok' => false, 'message' => 'Title and content are required.'], 422);
}

$pdo->beginTransaction();
try {
    $insertPost = $pdo->prepare("
        INSERT INTO forum_posts (user_id, title, content_text, view_count, comment_count, like_count, favorite_count, status, created_at, updated_at)
        VALUES (?, ?, ?, 0, 0, 0, 0, 'Under review', NOW(), NOW())
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
        WHERE fp.post_id = ?
        GROUP BY fp.post_id, fp.user_id, fp.title, fp.content_text, fp.view_count, fp.comment_count, fp.like_count, fp.favorite_count, fp.status, fp.created_at, u.username
    ");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    forum_realtime_publish('forum.post.created', [
        'postId' => $postId,
    ]);

    $payload = $post ? forum_post_row_to_payload($post) : null;
    if ($payload && isset($post['status'])) {
        $payload['status'] = $post['status'];
    }

    forum_json([
        'ok' => true,
        'post' => $payload,
    ], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    forum_json(['ok' => false, 'message' => 'Failed to create post.'], 500);
}
