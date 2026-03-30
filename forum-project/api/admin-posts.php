<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = forum_db();
forum_require_admin();

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
    $stmt = $pdo->query("
        SELECT status, COUNT(*) AS total
        FROM forum_posts
        WHERE status IN ('Under review', 'active', 'Rejected')
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

    $conditions = ["fp.status IN ('Under review', 'active', 'Rejected')"];
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
