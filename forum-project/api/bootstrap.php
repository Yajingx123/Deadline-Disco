<?php
declare(strict_types=1);

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

$allowedOrigins = [
    'http://127.0.0.1:5173',
    'http://127.0.0.1:8001',
];

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

function forum_format_datetime(?string $value): string {
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i', $ts) : $value;
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
        'publishTime' => forum_format_datetime((string)($row['created_at'] ?? '')),
        'mediaType' => (string)($row['media_type'] ?? 'text'),
        'labels' => $labels,
    ];
}

function forum_public_base_url(): string {
    return 'http://127.0.0.1:8001';
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
