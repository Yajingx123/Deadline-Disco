<?php
declare(strict_types=1);

// Keep API responses machine-readable JSON in production even when warnings occur.
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$config = require __DIR__ . '/../../Auth/backend/config/config.php';

/** Canonical forum_labels.name for admin-managed announcements (must match sql/102_acadbeat_core_seed_data.sql). */
if (!defined('FORUM_ANNOUNCEMENT_LABEL_NAME')) {
    define('FORUM_ANNOUNCEMENT_LABEL_NAME', 'Announcement');
}

$allowedOrigins = array_values(array_unique(array_filter(array_map(
    'trim',
    (array)($config['allowed_origins'] ?? [])
))));
if (!$allowedOrigins) {
    $allowedOrigins = ['http://127.0.0.1:8001'];
}

function forum_app_url(): string {
    global $config;
    $base = trim((string)($config['app_url'] ?? ''));
    if ($base === '') {
        $base = 'http://127.0.0.1:8001';
    }
    return rtrim($base, '/');
}

function forum_admin_url(): string {
    return forum_app_url() . '/admin_page/dist/index.html';
}

function forum_forum_url(string $suffix = ''): string {
    return forum_app_url() . '/forum-project/dist/index.html' . $suffix;
}

function forum_message_center_url(): string {
    return forum_app_url() . '/message-center-project/dist/index.html';
}

function forum_normalize_local_url(string $url): string {
    $trimmed = trim($url);
    if ($trimmed === '') {
        return '';
    }
    // Vite dev server uses base /forum-project/dist/ — map to PHP-served dist index
    $normalized = str_replace(
        'http://127.0.0.1:5173/forum-project/dist/',
        forum_forum_url(''),
        $trimmed
    );
    $normalized = str_replace('http://127.0.0.1:5173/', forum_forum_url('?'), $normalized);
    $normalized = str_replace('http://127.0.0.1:5174/', forum_admin_url(), $normalized);
    $normalized = str_replace('http://127.0.0.1:5173?', forum_forum_url('?'), $normalized);
    $normalized = str_replace(forum_forum_url('?view=messages'), forum_message_center_url(), $normalized);
    $normalized = str_replace(forum_app_url() . '/forum-project/dist/message-center.html', forum_message_center_url(), $normalized);

    // Convert local absolute URLs to public app URL for production deployments.
    $parts = parse_url($normalized);
    if (is_array($parts)) {
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if (($scheme === 'http' || $scheme === 'https') && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $path = (string)($parts['path'] ?? '/');
            if ($path === '') {
                $path = '/';
            }
            $query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';
            $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? ('#' . $parts['fragment']) : '';
            return forum_app_url() . $path . $query . $fragment;
        }
    }

    if (str_starts_with($normalized, '/')) {
        return forum_app_url() . $normalized;
    }

    return $normalized;
}

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function forum_db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'],
        $config['db_name']
    );

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function forum_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function forum_input(): array {
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function forum_current_user(): ?array {
    $user = $_SESSION['auth_user'] ?? null;
    return is_array($user) ? $user : null;
}

function forum_require_user(): array {
    $user = forum_current_user();
    if (!$user || empty($user['user_id'])) {
        forum_json([
            'ok' => false,
            'message' => 'Login required.',
        ], 401);
    }
    return $user;
}

function forum_is_admin(?array $user): bool {
    return is_array($user) && (string)($user['role'] ?? 'user') === 'admin';
}

function forum_require_admin(): array {
    $user = forum_require_user();
    if (!forum_is_admin($user)) {
        forum_json([
            'ok' => false,
            'message' => 'Admin access required.',
        ], 403);
    }
    return $user;
}

function forum_format_datetime(?string $value): string {
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i', $ts) : $value;
}

function forum_avatar_letters(string $value): string {
    $trimmed = trim($value);
    if ($trimmed === '') {
        return 'U';
    }
    $parts = preg_split('/\s+/', $trimmed) ?: [];
    if (count($parts) >= 2) {
        $first = strtoupper(substr((string)$parts[0], 0, 1));
        $second = strtoupper(substr((string)$parts[1], 0, 1));
        return trim($first . $second) ?: 'U';
    }
    return strtoupper(substr($trimmed, 0, min(strlen($trimmed), 2)));
}

function forum_extract_media_rows(string $content): array {
    $rows = [];
    if (preg_match_all('/!\[(.*?)\]\((.*?)\)/', $content, $matches, PREG_SET_ORDER)) {
        $order = 1;
        foreach ($matches as $match) {
            $alt = (string)($match[1] ?? '');
            $src = (string)($match[2] ?? '');
            if ($src === '') {
                continue;
            }
            $type = 'image';
            if (str_starts_with($alt, 'audio:')) {
                $type = 'audio';
            } elseif (preg_match('/^https?:\/\//i', $src) === 1 && preg_match('/\.(mp4|mov|webm)(\?|$)/i', $src) === 1) {
                $type = 'video';
            } elseif (preg_match('/^https?:\/\//i', $src) === 1 && preg_match('/\.(png|jpe?g|gif|webp|svg)(\?|$)/i', $src) !== 1) {
                $type = 'link';
            }
            $rows[] = [
                'media_type' => $type,
                'media_url' => $src,
                'order_index' => $order++,
            ];
        }
    }
    return $rows;
}

function forum_ensure_forum_post_announcement_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $columnStmt = $pdo->query("SHOW COLUMNS FROM forum_posts LIKE 'is_pinned'");
    if (!$columnStmt->fetch()) {
        $pdo->exec("
            ALTER TABLE forum_posts
            ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER status
        ");
    }

    forum_consolidate_duplicate_announcement_labels($pdo);

    $canonical = FORUM_ANNOUNCEMENT_LABEL_NAME;
    $labelStmt = $pdo->prepare("
        INSERT INTO forum_labels (name, created_at)
        SELECT '{$canonical}', NOW()
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM forum_labels WHERE BINARY name = '{$canonical}'
        )
    ");
    $labelStmt->execute();

    $ensured = true;
}

/**
 * Merge mistaken `announcement` (all-lowercase) rows into canonical `Announcement`.
 * Uses BINARY comparisons because utf8mb4_unicode_ci treats those strings as equal for `=`.
 */
function forum_consolidate_duplicate_announcement_labels(PDO $pdo): void {
    try {
        $pdo->beginTransaction();

        $canonical = FORUM_ANNOUNCEMENT_LABEL_NAME;
        $legacy = 'announcement';

        // Case A: only a lowercase row exists — rename in place (same label_id, no orphaned links).
        $pdo->exec("
            UPDATE forum_labels
            SET name = '{$canonical}'
            WHERE name = BINARY '{$legacy}'
              AND NOT EXISTS (
                  SELECT 1
                  FROM (
                      SELECT 1 FROM forum_labels WHERE BINARY name = '{$canonical}'
                  ) AS announce_exists
              )
        ");

        // Case B: two rows (legacy + canonical) — merge into canonical, then drop duplicate name.
        $pdo->exec("
            INSERT INTO forum_labels (name, created_at)
            SELECT '{$canonical}', NOW()
            FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM forum_labels WHERE BINARY name = '{$canonical}')
        ");
        $pdo->exec("
            DELETE fpl
            FROM forum_post_labels fpl
            JOIN forum_labels fl ON fl.label_id = fpl.label_id AND fl.name = BINARY '{$legacy}'
            JOIN forum_post_labels fpl2 ON fpl2.post_id = fpl.post_id
            JOIN forum_labels fl2 ON fl2.label_id = fpl2.label_id AND BINARY fl2.name = BINARY '{$canonical}'
        ");
        $pdo->exec("
            UPDATE forum_post_labels fpl
            JOIN forum_labels fl ON fl.label_id = fpl.label_id AND fl.name = BINARY '{$legacy}'
            JOIN forum_labels fl2 ON BINARY fl2.name = BINARY '{$canonical}'
            SET fpl.label_id = fl2.label_id
        ");
        $pdo->exec("DELETE FROM forum_labels WHERE BINARY name = BINARY '{$legacy}'");

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('forum_consolidate_duplicate_announcement_labels: ' . $e->getMessage());
    }
}

function forum_announcement_label_id(PDO $pdo): int {
    forum_ensure_forum_post_announcement_schema($pdo);
    $stmt = $pdo->prepare('SELECT label_id FROM forum_labels WHERE BINARY name = ? LIMIT 1');
    $stmt->execute([FORUM_ANNOUNCEMENT_LABEL_NAME]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function forum_plain_text_preview(?string $content, int $maxLength = 120): string {
    $text = trim((string)$content);
    if ($text === '') {
        return '';
    }

    $text = preg_replace('/!\[audio:(.*?)\]\((.*?)\)/', '[Audio]', $text) ?? $text;
    $text = preg_replace('/!\[(.*?)\]\((.*?)\)/', '[Image]', $text) ?? $text;
    $text = preg_replace('/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/', '$1', $text) ?? $text;
    $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text) ?? $text;
    $text = preg_replace('/\*(.+?)\*/', '$1', $text) ?? $text;
    $text = preg_replace('/<u>(.*?)<\/u>/', '$1', $text) ?? $text;
    $text = preg_replace('/\s+/', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $text;
    $text = trim($text);

    $textLength = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    if ($textLength > $maxLength) {
        $slice = function_exists('mb_substr')
            ? mb_substr($text, 0, $maxLength - 1)
            : substr($text, 0, $maxLength - 1);
        return rtrim((string)$slice) . '…';
    }
    return $text;
}

function forum_post_row_to_payload(array $row): array {
    $labels = array_values(array_filter(array_map('trim', explode('||', (string)($row['labels'] ?? '')))));
    return [
        'id' => (int)$row['post_id'],
        'title' => (string)$row['title'],
        'content' => (string)($row['content_text'] ?? ''),
        'author' => (string)($row['author_name'] ?? 'Unknown'),
        'authorUserId' => (int)($row['author_user_id'] ?? 0),
        'authorInitial' => strtoupper(substr((string)($row['author_name'] ?? 'U'), 0, 1)),
        'views' => (int)($row['view_count'] ?? 0),
        'commentCount' => (int)($row['comment_count'] ?? 0),
        'likeCount' => (int)($row['like_count'] ?? 0),
        'favoriteCount' => (int)($row['favorite_count'] ?? 0),
        'status' => (string)($row['status'] ?? 'Under review'),
        'isPinned' => ((int)($row['is_pinned'] ?? 0) === 1),
        'is_pinned' => ((int)($row['is_pinned'] ?? 0) === 1),
        'publishTime' => forum_format_datetime((string)($row['created_at'] ?? '')),
        'mediaType' => (string)($row['media_type'] ?? 'text'),
        'labels' => $labels,
    ];
}

function forum_user_payload(array $row): array {
    $username = (string)($row['username'] ?? 'Unknown');
    return [
        'id' => (int)($row['user_id'] ?? 0),
        'username' => $username,
        'email' => (string)($row['email'] ?? ''),
        'avatar' => forum_avatar_letters($username),
    ];
}

function forum_chat_member_payload(array $row, int $currentUserId): array {
    $payload = forum_user_payload($row);
    $payload['role'] = (string)($row['member_role'] ?? 'member');
    $payload['isSelf'] = $payload['id'] === $currentUserId;
    return $payload;
}

function forum_chat_title(array $conversation, array $members, int $currentUserId): string {
    $conversationType = (string)($conversation['conversation_type'] ?? 'direct');
    $explicitTitle = trim((string)($conversation['title'] ?? ''));
    if ($conversationType === 'group') {
        if ($explicitTitle !== '') {
            return $explicitTitle;
        }
        $names = array_values(array_filter(array_map(
            static fn(array $member): string => trim((string)($member['username'] ?? '')),
            $members
        )));
        return $names ? implode(' ', array_slice($names, 0, 9)) : 'Group chat';
    }

    foreach ($members as $member) {
        if ((int)($member['id'] ?? 0) !== $currentUserId) {
            return (string)($member['username'] ?? 'Direct message');
        }
    }
    return 'Direct message';
}

function forum_chat_conversation_payload(array $conversation, array $members, int $currentUserId): array {
    $title = forum_chat_title($conversation, $members, $currentUserId);
    $otherMembers = array_values(array_filter($members, static fn(array $member): bool => !(bool)($member['isSelf'] ?? false)));
    $cover = $otherMembers[0] ?? ($members[0] ?? ['avatar' => 'U']);

    return [
        'id' => (int)($conversation['conversation_id'] ?? 0),
        'type' => (string)($conversation['conversation_type'] ?? 'direct'),
        'title' => $title,
        'customTitle' => trim((string)($conversation['title'] ?? '')),
        'avatar' => (string)($cover['avatar'] ?? 'U'),
        'memberCount' => count($members),
        'members' => $members,
        'lastMessageAt' => (string)($conversation['last_message_at'] ?? ''),
        'lastMessagePreview' => forum_plain_text_preview((string)($conversation['last_message_text'] ?? '')),
        'lastMessageAuthor' => (string)($conversation['last_message_author'] ?? ''),
        'unreadCount' => max(0, (int)($conversation['unread_count'] ?? 0)),
    ];
}

function forum_chat_message_payload(array $row, int $currentUserId): array {
    $username = (string)($row['author_name'] ?? $row['username'] ?? 'Unknown');
    $userId = (int)($row['user_id'] ?? $row['author_user_id'] ?? 0);
    return [
        'id' => (int)($row['message_id'] ?? 0),
        'conversationId' => (int)($row['conversation_id'] ?? 0),
        'content' => (string)($row['content_text'] ?? ''),
        'createdAt' => (string)($row['created_at'] ?? ''),
        'displayTime' => forum_format_datetime((string)($row['created_at'] ?? '')),
        'author' => [
            'id' => $userId,
            'username' => $username,
            'email' => (string)($row['email'] ?? ''),
            'avatar' => forum_avatar_letters($username),
            'isSelf' => $userId === $currentUserId,
        ],
    ];
}

function forum_sync_message_center_notifications(PDO $pdo, int $recipientUserId): void {
    if ($recipientUserId <= 0) {
        return;
    }
    $forumPostUrlPrefix = forum_forum_url('?view=forum&postId=');

    $replyPostStmt = $pdo->prepare("
        INSERT INTO message_center_notifications (
            recipient_user_id,
            actor_user_id,
            notification_type,
            post_id,
            comment_id,
            title,
            body_text,
            cta_label,
            cta_url,
            is_read,
            created_at,
            updated_at
        )
        SELECT
            ? AS recipient_user_id,
            fc.user_id AS actor_user_id,
            'reply' AS notification_type,
            fc.post_id,
            fc.comment_id,
            CONCAT(u.username, ' replied to your post') AS title,
            LEFT(TRIM(fc.content_text), 180) AS body_text,
            'Reply' AS cta_label,
            CONCAT('{$forumPostUrlPrefix}', fc.post_id) AS cta_url,
            0 AS is_read,
            fc.created_at,
            fc.updated_at
        FROM forum_comments fc
        JOIN forum_posts fp ON fp.post_id = fc.post_id
        JOIN users u ON u.user_id = fc.user_id
        LEFT JOIN message_center_notifications n
          ON n.recipient_user_id = ?
         AND n.notification_type = 'reply'
         AND n.comment_id = fc.comment_id
        WHERE fp.user_id = ?
          AND fc.user_id <> ?
          AND fc.status = 'active'
          AND n.notification_id IS NULL
    ");
    $replyPostStmt->execute([$recipientUserId, $recipientUserId, $recipientUserId, $recipientUserId]);

    $replyCommentStmt = $pdo->prepare("
        INSERT INTO message_center_notifications (
            recipient_user_id,
            actor_user_id,
            notification_type,
            post_id,
            comment_id,
            title,
            body_text,
            cta_label,
            cta_url,
            is_read,
            created_at,
            updated_at
        )
        SELECT
            ? AS recipient_user_id,
            child.user_id AS actor_user_id,
            'reply' AS notification_type,
            child.post_id,
            child.comment_id,
            CONCAT(u.username, ' replied to your comment') AS title,
            LEFT(TRIM(child.content_text), 180) AS body_text,
            'Reply' AS cta_label,
            CONCAT('{$forumPostUrlPrefix}', child.post_id) AS cta_url,
            0 AS is_read,
            child.created_at,
            child.updated_at
        FROM forum_comments child
        JOIN forum_comments parent ON parent.comment_id = child.parent_comment_id
        JOIN users u ON u.user_id = child.user_id
        LEFT JOIN message_center_notifications n
          ON n.recipient_user_id = ?
         AND n.notification_type = 'reply'
         AND n.comment_id = child.comment_id
        WHERE parent.user_id = ?
          AND child.user_id <> ?
          AND child.status = 'active'
          AND n.notification_id IS NULL
    ");
    $replyCommentStmt->execute([$recipientUserId, $recipientUserId, $recipientUserId, $recipientUserId]);

    $likeStmt = $pdo->prepare("
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
        SELECT
            ? AS recipient_user_id,
            l.user_id AS actor_user_id,
            'like' AS notification_type,
            l.post_id,
            CONCAT(u.username, ' liked your post') AS title,
            fp.title AS body_text,
            'View post' AS cta_label,
            CONCAT('{$forumPostUrlPrefix}', l.post_id) AS cta_url,
            0 AS is_read,
            l.created_at,
            l.created_at
        FROM forum_post_likes l
        JOIN forum_posts fp ON fp.post_id = l.post_id
        JOIN users u ON u.user_id = l.user_id
        LEFT JOIN message_center_notifications n
          ON n.recipient_user_id = ?
         AND n.notification_type = 'like'
         AND n.post_id = l.post_id
         AND n.actor_user_id = l.user_id
        WHERE fp.user_id = ?
          AND l.user_id <> ?
          AND n.notification_id IS NULL
    ");
    $likeStmt->execute([$recipientUserId, $recipientUserId, $recipientUserId, $recipientUserId]);

    $favoriteStmt = $pdo->prepare("
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
        SELECT
            ? AS recipient_user_id,
            f.user_id AS actor_user_id,
            'favorite' AS notification_type,
            f.post_id,
            CONCAT(u.username, ' favorited your post') AS title,
            fp.title AS body_text,
            'View post' AS cta_label,
            CONCAT('{$forumPostUrlPrefix}', f.post_id) AS cta_url,
            0 AS is_read,
            f.created_at,
            f.created_at
        FROM forum_post_favorites f
        JOIN forum_posts fp ON fp.post_id = f.post_id
        JOIN users u ON u.user_id = f.user_id
        LEFT JOIN message_center_notifications n
          ON n.recipient_user_id = ?
         AND n.notification_type = 'favorite'
         AND n.post_id = f.post_id
         AND n.actor_user_id = f.user_id
        WHERE fp.user_id = ?
          AND f.user_id <> ?
          AND n.notification_id IS NULL
    ");
    $favoriteStmt->execute([$recipientUserId, $recipientUserId, $recipientUserId, $recipientUserId]);

    $deleteStaleLikes = $pdo->prepare("
        DELETE n
        FROM message_center_notifications n
        LEFT JOIN forum_post_likes l
          ON l.post_id = n.post_id
         AND l.user_id = n.actor_user_id
        WHERE n.recipient_user_id = ?
          AND n.notification_type = 'like'
          AND l.like_id IS NULL
    ");
    $deleteStaleLikes->execute([$recipientUserId]);

    $deleteStaleFavorites = $pdo->prepare("
        DELETE n
        FROM message_center_notifications n
        LEFT JOIN forum_post_favorites f
          ON f.post_id = n.post_id
         AND f.user_id = n.actor_user_id
        WHERE n.recipient_user_id = ?
          AND n.notification_type = 'favorite'
          AND f.favorite_id IS NULL
    ");
    $deleteStaleFavorites->execute([$recipientUserId]);
}

function forum_public_base_url(): string {
    return forum_app_url();
}

function forum_uploaded_file_path_from_url(string $url): ?string {
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
    if ($path === '' || !str_starts_with($path, '/forum-project/uploads/')) {
        return null;
    }

    $relativePath = ltrim(substr($path, strlen('/forum-project/')), '/');
    if ($relativePath === '' || str_contains($relativePath, '..')) {
        return null;
    }

    return dirname(__DIR__) . '/' . $relativePath;
}

function forum_collect_unused_media_urls(PDO $pdo, array $urls): array {
    $urls = array_values(array_unique(array_filter(array_map('trim', $urls))));
    if (!$urls) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($urls), '?'));

    $postStmt = $pdo->prepare("
        SELECT media_url, COUNT(*) AS ref_count
        FROM forum_post_media
        WHERE media_url IN ($placeholders)
        GROUP BY media_url
    ");
    $postStmt->execute($urls);

    $commentStmt = $pdo->prepare("
        SELECT media_url, COUNT(*) AS ref_count
        FROM forum_comment_media
        WHERE media_url IN ($placeholders)
        GROUP BY media_url
    ");
    $commentStmt->execute($urls);

    $referenceCounts = array_fill_keys($urls, 0);
    foreach ($postStmt->fetchAll() as $row) {
        $referenceCounts[(string)$row['media_url']] = ($referenceCounts[(string)$row['media_url']] ?? 0) + (int)$row['ref_count'];
    }
    foreach ($commentStmt->fetchAll() as $row) {
        $referenceCounts[(string)$row['media_url']] = ($referenceCounts[(string)$row['media_url']] ?? 0) + (int)$row['ref_count'];
    }

    return array_values(array_filter($urls, static function(string $url) use ($referenceCounts): bool {
        return ($referenceCounts[$url] ?? 0) === 0;
    }));
}

function forum_delete_uploaded_files(array $urls): void {
    $paths = array_values(array_unique(array_filter(array_map('forum_uploaded_file_path_from_url', $urls))));
    foreach ($paths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function forum_realtime_publish(string $type, array $data = []): void {
    $payload = json_encode([
        'type' => $type,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        return;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nConnection: close\r\n",
            'content' => $payload,
            'timeout' => 0.6,
            'ignore_errors' => true,
        ],
    ]);

    try {
        @file_get_contents('http://127.0.0.1:3001/publish', false, $context);
    } catch (Throwable $_e) {
        // Ignore realtime relay failures. Primary requests must still succeed.
    }
}
