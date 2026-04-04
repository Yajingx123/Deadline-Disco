<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pdo = forum_db();
forum_ensure_forum_post_announcement_schema($pdo);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = trim((string)($_SERVER['PATH_INFO'] ?? ''), '/');
$parts = $path === '' ? [] : explode('/', $path);
$announcementId = (!empty($parts[0]) && is_numeric($parts[0])) ? (int)$parts[0] : (int)($_GET['id'] ?? 0);

switch ($method) {
    case 'GET':
        if ($announcementId > 0) {
            getAnnouncement($announcementId);
            break;
        }
        getAnnouncements();
        break;
    case 'POST':
        createAnnouncement();
        break;
    case 'PUT':
        if ($announcementId <= 0) {
            forum_json(['ok' => false, 'message' => 'Announcement id is required.'], 422);
        }
        updateAnnouncement($announcementId);
        break;
    case 'DELETE':
        if ($announcementId <= 0) {
            forum_json(['ok' => false, 'message' => 'Announcement id is required.'], 422);
        }
        deleteAnnouncement($announcementId);
        break;
    default:
        forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

function forum_announcement_base_query(): string {
    $n = FORUM_ANNOUNCEMENT_LABEL_NAME;
    return "
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
            fp.created_at,
            fp.updated_at,
            u.username AS author_name,
            COALESCE(MIN(fpm.media_type), 'text') AS media_type,
            GROUP_CONCAT(DISTINCT fl.name ORDER BY fl.name SEPARATOR '||') AS labels
        FROM forum_posts fp
        JOIN users u ON u.user_id = fp.user_id
        JOIN forum_post_labels announcement_link ON announcement_link.post_id = fp.post_id
        JOIN forum_labels announcement_label
          ON announcement_label.label_id = announcement_link.label_id
         AND BINARY announcement_label.name = BINARY '{$n}'
        LEFT JOIN forum_post_media fpm ON fpm.post_id = fp.post_id
        LEFT JOIN forum_post_labels fpl ON fpl.post_id = fp.post_id
        LEFT JOIN forum_labels fl ON fl.label_id = fpl.label_id
    ";
}

function forum_announcement_notice_body(string $content): string {
    $body = forum_plain_text_preview($content, 280);
    return $body !== '' ? $body : 'New announcement published.';
}

/**
 * Best-effort: mirror new announcement into message center. Must not fail the forum post if this table is missing or mismatched.
 */
function forum_announcement_insert_system_notice(PDO $pdo, int $postId, string $title, string $content): void {
    try {
        $noticeStmt = $pdo->prepare("
            INSERT INTO message_center_system_notices (
                title, body_text, cta_label, cta_url, status, created_at, updated_at
            ) VALUES (?, ?, 'Open forum', ?, 'active', NOW(), NOW())
        ");
        $noticeStmt->execute([
            $title,
            forum_announcement_notice_body($content),
            forum_forum_url('?view=forum&postId=' . $postId),
        ]);
    } catch (Throwable $e) {
        error_log('forum_announcement_insert_system_notice: ' . $e->getMessage());
    }
}

function getAnnouncements(): void {
    // Public read: students must see admin announcements without extra auth edge cases.
    $pdo = forum_db();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    // Integer LIMIT/OFFSET in SQL: some PDO+MySQL native prepare combinations mishandle bound LIMIT.
    $sql = forum_announcement_base_query() . "
        WHERE fp.status = 'active'
        GROUP BY fp.post_id, fp.user_id, fp.title, fp.content_text, fp.view_count, fp.comment_count, fp.like_count, fp.favorite_count, fp.status, fp.is_pinned, fp.created_at, fp.updated_at, u.username
        ORDER BY fp.is_pinned DESC, fp.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    $stmt = $pdo->query($sql);

    $countStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT fp.post_id)
        FROM forum_posts fp
        JOIN forum_post_labels fpl ON fpl.post_id = fp.post_id
        JOIN forum_labels fl ON fl.label_id = fpl.label_id
        WHERE fp.status = 'active'
          AND BINARY fl.name = ?
    ");
    $countStmt->execute([FORUM_ANNOUNCEMENT_LABEL_NAME]);
    $total = (int)($countStmt->fetchColumn() ?: 0);

    $announcements = array_map(static function(array $row): array {
        $payload = forum_post_row_to_payload($row);
        $payload['contentPreview'] = forum_plain_text_preview((string)($row['content_text'] ?? ''), 140);
        return $payload;
    }, $stmt->fetchAll());

    forum_json([
        'ok' => true,
        'announcements' => $announcements,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int)ceil($total / $limit),
        ],
    ]);
}

function getAnnouncement(int $id): void {
    $pdo = forum_db();
    $stmt = $pdo->prepare(forum_announcement_base_query() . "
        WHERE fp.post_id = ?
          AND fp.status = 'active'
        GROUP BY fp.post_id, fp.user_id, fp.title, fp.content_text, fp.view_count, fp.comment_count, fp.like_count, fp.favorite_count, fp.status, fp.is_pinned, fp.created_at, fp.updated_at, u.username
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        forum_json(['ok' => false, 'message' => 'Announcement not found.'], 404);
    }

    $payload = forum_post_row_to_payload($row);
    $payload['contentPreview'] = forum_plain_text_preview((string)($row['content_text'] ?? ''), 140);

    forum_json([
        'ok' => true,
        'announcement' => $payload,
    ]);
}

function createAnnouncement(): void {
    $admin = forum_require_admin();
    $pdo = forum_db();
    $data = forum_input();

    $title = trim((string)($data['title'] ?? ''));
    $content = trim((string)($data['content'] ?? ''));
    $isPinned = !empty($data['is_pinned']) ? 1 : 0;

    if ($title === '' || $content === '') {
        forum_json(['ok' => false, 'message' => 'Title and content are required.'], 422);
    }

    $announcementLabelId = forum_announcement_label_id($pdo);
    if ($announcementLabelId <= 0) {
        forum_json(['ok' => false, 'message' => 'Announcement label is unavailable.'], 500);
    }

    $pdo->beginTransaction();
    try {
        $insertPost = $pdo->prepare("
            INSERT INTO forum_posts (
                user_id,
                title,
                content_text,
                view_count,
                comment_count,
                like_count,
                favorite_count,
                status,
                is_pinned,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, 0, 0, 0, 0, 'active', ?, NOW(), NOW())
        ");
        $insertPost->execute([(int)$admin['user_id'], $title, $content, $isPinned]);
        $postId = (int)$pdo->lastInsertId();

        $labelStmt = $pdo->prepare("INSERT INTO forum_post_labels (post_id, label_id) VALUES (?, ?)");
        $labelStmt->execute([$postId, $announcementLabelId]);

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

        forum_announcement_insert_system_notice($pdo, $postId, $title, $content);

        forum_realtime_publish('forum.post.created', ['postId' => $postId]);
        forum_realtime_publish('forum.announcement.updated', ['announcementId' => $postId, 'action' => 'created']);
        forum_realtime_publish('message-center.updated', ['scope' => 'system']);

        forum_json([
            'ok' => true,
            'message' => 'Announcement created successfully.',
            'announcement_id' => $postId,
        ], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('createAnnouncement: ' . $e->getMessage());
        forum_json(['ok' => false, 'message' => 'Failed to create announcement.'], 500);
    }
}

function updateAnnouncement(int $id): void {
    forum_require_admin();
    $pdo = forum_db();
    $data = forum_input();

    $title = trim((string)($data['title'] ?? ''));
    $content = trim((string)($data['content'] ?? ''));
    $isPinned = !empty($data['is_pinned']) ? 1 : 0;

    if ($title === '' || $content === '') {
        forum_json(['ok' => false, 'message' => 'Title and content are required.'], 422);
    }

    $checkStmt = $pdo->prepare("
        SELECT fp.post_id
        FROM forum_posts fp
        JOIN forum_post_labels fpl ON fpl.post_id = fp.post_id
        JOIN forum_labels fl ON fl.label_id = fpl.label_id
        WHERE fp.post_id = ?
          AND BINARY fl.name = ?
        LIMIT 1
    ");
    $checkStmt->execute([$id, FORUM_ANNOUNCEMENT_LABEL_NAME]);
    if (!$checkStmt->fetch()) {
        forum_json(['ok' => false, 'message' => 'Announcement not found.'], 404);
    }

    $pdo->beginTransaction();
    try {
        $oldMediaStmt = $pdo->prepare("SELECT media_url FROM forum_post_media WHERE post_id = ?");
        $oldMediaStmt->execute([$id]);
        $oldUrls = array_map(static fn(array $row): string => (string)$row['media_url'], $oldMediaStmt->fetchAll());

        $updateStmt = $pdo->prepare("
            UPDATE forum_posts
            SET title = ?, content_text = ?, is_pinned = ?, updated_at = NOW()
            WHERE post_id = ?
        ");
        $updateStmt->execute([$title, $content, $isPinned, $id]);

        $deleteMediaStmt = $pdo->prepare("DELETE FROM forum_post_media WHERE post_id = ?");
        $deleteMediaStmt->execute([$id]);

        $mediaRows = forum_extract_media_rows($content);
        if ($mediaRows) {
            $mediaStmt = $pdo->prepare("
                INSERT INTO forum_post_media (post_id, media_type, media_url, order_index)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($mediaRows as $mediaRow) {
                $mediaStmt->execute([$id, $mediaRow['media_type'], $mediaRow['media_url'], $mediaRow['order_index']]);
            }
        }

        $pdo->commit();

        forum_delete_uploaded_files(forum_collect_unused_media_urls($pdo, $oldUrls));
        forum_realtime_publish('forum.announcement.updated', ['announcementId' => $id, 'action' => 'updated']);

        forum_json(['ok' => true, 'message' => 'Announcement updated successfully.']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        forum_json(['ok' => false, 'message' => 'Failed to update announcement.'], 500);
    }
}

function deleteAnnouncement(int $id): void {
    forum_require_admin();
    $pdo = forum_db();

    $checkStmt = $pdo->prepare("
        SELECT fp.post_id
        FROM forum_posts fp
        JOIN forum_post_labels fpl ON fpl.post_id = fp.post_id
        JOIN forum_labels fl ON fl.label_id = fpl.label_id
        WHERE fp.post_id = ?
          AND BINARY fl.name = ?
        LIMIT 1
    ");
    $checkStmt->execute([$id, FORUM_ANNOUNCEMENT_LABEL_NAME]);
    if (!$checkStmt->fetch()) {
        forum_json(['ok' => false, 'message' => 'Announcement not found.'], 404);
    }

    $stmt = $pdo->prepare("
        UPDATE forum_posts
        SET status = 'deleted', is_pinned = 0, updated_at = NOW()
        WHERE post_id = ?
    ");
    $stmt->execute([$id]);

    forum_realtime_publish('forum.post.deleted', ['postId' => $id]);
    forum_realtime_publish('forum.announcement.updated', ['announcementId' => $id, 'action' => 'deleted']);

    forum_json(['ok' => true, 'message' => 'Announcement deleted successfully.']);
}
