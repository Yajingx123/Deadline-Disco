<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = forum_db();
forum_require_admin();

function admin_posts_ensure_notification_type(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM message_center_notifications LIKE 'notification_type'");
    $column = $stmt->fetch();
    $typeDefinition = strtolower((string)($column['Type'] ?? ''));
    if (!str_contains($typeDefinition, "'system'")) {
        $pdo->exec("
            ALTER TABLE message_center_notifications
            MODIFY notification_type ENUM('reply', 'like', 'favorite', 'challenge_reset', 'system') NOT NULL
        ");
    }

    $ensured = true;
}

function admin_posts_action_label(string $action): string {
    return match ($action) {
        'approve' => 'approved',
        'reject' => 'declined',
        'delete' => 'deleted',
        'reset' => 'returned to review',
        default => 'updated',
    };
}

function admin_posts_notification_copy(string $action, string $postTitle): array {
    $safeTitle = trim($postTitle) !== '' ? $postTitle : 'your post';

    return match ($action) {
        'approve' => [
            'title' => 'Your post was approved',
            'body' => sprintf('Your post "%s" has been approved and is now visible in the forum.', $safeTitle),
            'ctaLabel' => 'View post',
        ],
        'reject' => [
            'title' => 'Your post was declined',
            'body' => sprintf('Your post "%s" did not pass review. You can revise it and submit again later.', $safeTitle),
            'ctaLabel' => 'Open forum',
        ],
        'delete' => [
            'title' => 'Your post was deleted',
            'body' => sprintf('Your post "%s" was removed by admin and is no longer public.', $safeTitle),
            'ctaLabel' => 'Open forum',
        ],
        'reset' => [
            'title' => 'Your post is back under review',
            'body' => sprintf('Your post "%s" has been moved back into review status.', $safeTitle),
            'ctaLabel' => 'Open forum',
        ],
        default => [
            'title' => 'Your post was updated',
            'body' => sprintf('There is a new moderation update for "%s".', $safeTitle),
            'ctaLabel' => 'Open forum',
        ],
    };
}

function admin_posts_create_system_notification(PDO $pdo, array $postRow, string $action): void {
    admin_posts_ensure_notification_type($pdo);

    $recipientUserId = (int)($postRow['author_user_id'] ?? 0);
    $postId = (int)($postRow['post_id'] ?? 0);
    if ($recipientUserId <= 0 || $postId <= 0) {
        return;
    }

    $copy = admin_posts_notification_copy($action, (string)($postRow['title'] ?? ''));
    $ctaUrl = $action === 'approve'
        ? sprintf('http://127.0.0.1:8001/forum-project/dist/index.html?view=forum&postId=%d', $postId)
        : 'http://127.0.0.1:8001/forum-project/dist/index.html?view=forum';

    $stmt = $pdo->prepare("
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
        VALUES (?, NULL, 'system', ?, ?, ?, ?, ?, 0, NOW(), NOW())
    ");
    $stmt->execute([
        $recipientUserId,
        $postId,
        $copy['title'],
        $copy['body'],
        $copy['ctaLabel'],
        $ctaUrl,
    ]);
}

function admin_post_status_from_filter(string $filter): ?string {
    return match ($filter) {
        'pending' => 'Under review',
        'approved' => 'active',
        'rejected' => 'Rejected',
        'all', '' => null,
        default => null,
    };
}

function admin_post_counts(PDO $pdo): array {
    $ann = FORUM_ANNOUNCEMENT_LABEL_NAME;
    $stmt = $pdo->query("
        SELECT status, COUNT(*) AS total
        FROM forum_posts
        WHERE status IN ('Under review', 'active', 'Rejected')
          AND NOT EXISTS (
              SELECT 1
              FROM forum_post_labels xfpl
              JOIN forum_labels xfl ON xfl.label_id = xfpl.label_id
              WHERE xfpl.post_id = forum_posts.post_id
                AND xfl.name = '{$ann}'
          )
        GROUP BY status
    ");

    $counts = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'all' => 0,
    ];

    foreach ($stmt->fetchAll() as $row) {
        $status = (string)($row['status'] ?? '');
        $total = (int)($row['total'] ?? 0);
        if ($status === 'Under review') {
            $counts['pending'] = $total;
        } elseif ($status === 'active') {
            $counts['approved'] = $total;
        } elseif ($status === 'Rejected') {
            $counts['rejected'] = $total;
        }
        $counts['all'] += $total;
    }

    return $counts;
}

if ($method === 'GET') {
    $filter = trim((string)($_GET['status'] ?? 'pending'));
    $search = trim((string)($_GET['q'] ?? ''));
    $status = admin_post_status_from_filter($filter);

    $ann = FORUM_ANNOUNCEMENT_LABEL_NAME;
    $conditions = [
        "fp.status IN ('Under review', 'active', 'Rejected')",
        "NOT EXISTS (
            SELECT 1
            FROM forum_post_labels xfpl
            JOIN forum_labels xfl ON xfl.label_id = xfpl.label_id
            WHERE xfpl.post_id = fp.post_id
              AND xfl.name = '{$ann}'
        )",
    ];
    $params = [];

    if ($status !== null) {
        $conditions[] = 'fp.status = ?';
        $params[] = $status;
    }

    if ($search !== '') {
        $conditions[] = '(fp.title LIKE ? OR fp.content_text LIKE ? OR u.username LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }

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
        WHERE " . implode(' AND ', $conditions) . "
        GROUP BY fp.post_id, fp.user_id, fp.title, fp.content_text, fp.view_count, fp.comment_count, fp.like_count, fp.favorite_count, fp.status, fp.created_at, u.username
        ORDER BY
            CASE fp.status
                WHEN 'Under review' THEN 0
                WHEN 'Rejected' THEN 1
                ELSE 2
            END,
            fp.created_at DESC
    ");
    $stmt->execute($params);

    $posts = array_map(static function(array $row): array {
        $payload = forum_post_row_to_payload($row);
        $payload['status'] = (string)($row['status'] ?? 'Under review');
        return $payload;
    }, $stmt->fetchAll());

    forum_json([
        'ok' => true,
        'posts' => $posts,
        'counts' => admin_post_counts($pdo),
    ]);
}

if ($method !== 'POST') {
    forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = forum_input();
$postId = (int)($input['postId'] ?? 0);
$action = trim((string)($input['action'] ?? ''));

if ($postId <= 0) {
    forum_json(['ok' => false, 'message' => 'Invalid post id.'], 422);
}

$targetStatus = match ($action) {
    'approve' => 'active',
    'reject' => 'Rejected',
    'reset' => 'Under review',
    'delete' => 'deleted',
    default => null,
};

if ($targetStatus === null) {
    forum_json(['ok' => false, 'message' => 'Invalid action.'], 422);
}

$announcementCheckStmt = $pdo->prepare("
    SELECT 1
    FROM forum_post_labels fpl
    JOIN forum_labels fl ON fl.label_id = fpl.label_id
    WHERE fpl.post_id = ?
      AND BINARY fl.name = ?
    LIMIT 1
");
$announcementCheckStmt->execute([$postId, FORUM_ANNOUNCEMENT_LABEL_NAME]);
if ($announcementCheckStmt->fetchColumn()) {
    forum_json(['ok' => false, 'message' => 'Announcement posts are managed in the announcement page.'], 409);
}

if ($action === 'delete') {
    $statusStmt = $pdo->prepare('SELECT status FROM forum_posts WHERE post_id = ? LIMIT 1');
    $statusStmt->execute([$postId]);
    $currentStatus = $statusStmt->fetchColumn();

    if ($currentStatus === false) {
        forum_json(['ok' => false, 'message' => 'Post not found.'], 404);
    }

    if ((string)$currentStatus !== 'active') {
        forum_json(['ok' => false, 'message' => 'Only published posts can be deleted.'], 409);
    }
}

$updateStmt = $pdo->prepare("
    UPDATE forum_posts
    SET status = ?, updated_at = NOW()
    WHERE post_id = ?
    LIMIT 1
");
$updateStmt->execute([$targetStatus, $postId]);

if ($updateStmt->rowCount() === 0) {
    $existsStmt = $pdo->prepare('SELECT post_id FROM forum_posts WHERE post_id = ? LIMIT 1');
    $existsStmt->execute([$postId]);
    if (!$existsStmt->fetch()) {
        forum_json(['ok' => false, 'message' => 'Post not found.'], 404);
    }
}

$postStmt = $pdo->prepare("
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
    LIMIT 1
");
$postStmt->execute([$postId]);
$postRow = $postStmt->fetch();

if ($postRow) {
    admin_posts_create_system_notification($pdo, $postRow, $action);
    forum_realtime_publish('message-center.updated', [
        'recipientUserId' => (int)($postRow['author_user_id'] ?? 0),
        'action' => admin_posts_action_label($action),
        'postId' => (int)($postRow['post_id'] ?? 0),
    ]);
}

$payload = $postRow ? forum_post_row_to_payload($postRow) : null;
if ($payload && isset($postRow['status'])) {
    $payload['status'] = (string)$postRow['status'];
}

forum_json([
    'ok' => true,
    'message' => 'Post status updated.',
    'post' => $payload,
    'counts' => admin_post_counts($pdo),
]);
