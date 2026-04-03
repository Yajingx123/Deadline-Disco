<?php
/**
 * Vocabulary module – shared config (PHP 团队规定)
 * 未来可在此增加：数据库连接、Session、站点名等
 */
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

// 当前模块在站点中的基础路径（若部署在子目录可改为 '/vocab/'）
// 用于处理当页面被访问为 /backend/*.php 时，静态资源（frontend）需要回退一级目录。
$baseHref = '';
if (!empty($_SERVER['REQUEST_URI'] ?? '')) {
  $uri = (string)$_SERVER['REQUEST_URI'];
  if (strpos($uri, '/backend/') !== false) {
    $baseHref = '../';
  }
}

// 站点/模块名（用于 title、导航等）
$siteName = 'Vocabulary Practice';

// 数据库配置（MySQL Workbench 连接到的 MySQL Server）
// 当前统一使用 acadbeat
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'acadbeat');
define('DB_USER', 'root');
define('APP_HOME_URL', 'http://127.0.0.1:8001/home.html');
define('APP_OWNER_URL', 'http://127.0.0.1:8001/owner.html');
/** 主站静态资源根（shared-nav.js / shared-nav.css），与 APP_HOME_URL 同主机 */
define('ACADBEAT_MAIN_ORIGIN', preg_replace('#/home\\.html$#i', '', APP_HOME_URL));
define('APP_VOCAB_ROOT_PATH', '/vocba_prac/');

// 为避免把密码写进仓库：优先从环境变量读取；本地没有就填这里
// Windows PowerShell 临时设置示例：$env:VOCAB_DB_PASS="your_password"
define('DB_PASS', getenv('VOCAB_DB_PASS') ?: '123456');

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  return $pdo;
}

function vocab_table(string $name): string {
  $map = [
    'books' => 'vocab_word_books',
    'words' => 'vocab_words',
    'book_words' => 'vocab_word_book_words',
    'user_selections' => 'vocab_user_wordbook_selections',
    'sessions' => 'vocab_sessions',
    'session_items' => 'vocab_session_items',
    'session_responses' => 'vocab_session_responses',
    'user_progress' => 'vocab_user_word_progress',
  ];
  if (!isset($map[$name])) {
    throw new InvalidArgumentException('Unknown vocab table: ' . $name);
  }
  return $map[$name];
}

function vocab_now(): string {
  return date('Y-m-d H:i:s');
}

function vocab_current_user_id(): int {
  return (int)(($_SESSION['auth_user']['user_id'] ?? 0));
}

function vocab_current_user(): ?array {
  $user = $_SESSION['auth_user'] ?? null;
  return is_array($user) ? $user : null;
}

function vocab_is_authenticated(): bool {
  return vocab_current_user_id() > 0;
}

function vocab_require_auth(): void {
  if (vocab_is_authenticated()) {
    return;
  }
  $target = APP_HOME_URL . '?login=1';
  if (!headers_sent()) {
    header('Location: ' . $target, true, 302);
  }
  exit;
}

function vocab_json_response(array $payload, int $status = 200): void {
  if (!headers_sent()) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function vocab_read_json_body(): array {
  $raw = file_get_contents('php://input');
  if (!is_string($raw) || trim($raw) === '') {
    return [];
  }
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function vocab_normalize_status(?string $status): string {
  $value = strtolower(trim((string)$status));
  $allowed = ['new', 'learning', 'mastered', 'forgot'];
  return in_array($value, $allowed, true) ? $value : 'new';
}

function vocab_selected_book_rows(int $userId): array {
  if ($userId <= 0) {
    return [];
  }
  $booksTable = vocab_table('books');
  $selectionTable = vocab_table('user_selections');
  $stmt = db()->prepare("
    SELECT wb.word_book_id AS id, wb.slug, wb.title, wb.description
    FROM {$selectionTable} sel
    JOIN {$booksTable} wb ON wb.word_book_id = sel.word_book_id
    WHERE sel.user_id = ?
    ORDER BY wb.word_book_id ASC
  ");
  $stmt->execute([$userId]);
  return $stmt->fetchAll();
}

function vocab_selected_book_slugs(int $userId, bool $fallbackDaily = false): array {
  $rows = vocab_selected_book_rows($userId);
  $slugs = array_values(array_filter(array_map(
    static fn(array $row): string => (string)($row['slug'] ?? ''),
    $rows
  )));
  if (!$slugs && $fallbackDaily) {
    return ['daily'];
  }
  return $slugs;
}

function vocab_save_selected_book_slugs(int $userId, array $slugs): array {
  $userId = (int)$userId;
  if ($userId <= 0) {
    return [];
  }

  $normalized = [];
  foreach ($slugs as $slug) {
    $slug = trim((string)$slug);
    if ($slug !== '') {
      $normalized[$slug] = true;
    }
  }
  $normalized = array_keys($normalized);
  $booksTable = vocab_table('books');
  $selectionTable = vocab_table('user_selections');
  $rows = [];
  if ($normalized) {
    $placeholders = implode(',', array_fill(0, count($normalized), '?'));
    $stmt = db()->prepare("SELECT word_book_id, slug FROM {$booksTable} WHERE slug IN ({$placeholders}) ORDER BY word_book_id ASC");
    $stmt->execute($normalized);
    $rows = $stmt->fetchAll();
  }

  $pdo = db();
  $pdo->beginTransaction();
  try {
    $deleteStmt = $pdo->prepare("DELETE FROM {$selectionTable} WHERE user_id = ?");
    $deleteStmt->execute([$userId]);

    $insertStmt = $pdo->prepare("INSERT INTO {$selectionTable} (user_id, word_book_id) VALUES (?, ?)");
    $savedSlugs = [];
    foreach ($rows as $row) {
      $insertStmt->execute([$userId, (int)$row['word_book_id']]);
      $savedSlugs[] = (string)$row['slug'];
    }
    $pdo->commit();
    return $savedSlugs;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }
}

function vocab_user_progress_map(int $userId): array {
  if ($userId <= 0) {
    return [];
  }
  $progressTable = vocab_table('user_progress');
  $stmt = db()->prepare("
    SELECT word_id, times_seen, correct_count, wrong_count, first_try_correct_count, mastery_status, last_session_id, last_practiced_at
    FROM {$progressTable}
    WHERE user_id = ?
  ");
  $stmt->execute([$userId]);
  $rows = $stmt->fetchAll();
  $map = [];
  foreach ($rows as $row) {
    $map[(int)$row['word_id']] = [
      'times_seen' => (int)($row['times_seen'] ?? 0),
      'correct_count' => (int)($row['correct_count'] ?? 0),
      'wrong_count' => (int)($row['wrong_count'] ?? 0),
      'first_try_correct_count' => (int)($row['first_try_correct_count'] ?? 0),
      'mastery_status' => vocab_normalize_status((string)($row['mastery_status'] ?? 'new')),
      'last_session_id' => isset($row['last_session_id']) ? (int)$row['last_session_id'] : null,
      'last_practiced_at' => (string)($row['last_practiced_at'] ?? ''),
    ];
  }
  return $map;
}

function vocab_book_display_map(): array {
  return [
    'daily' => [
      'title' => 'Daily life & campus',
      'description' => '15 high-frequency words for lectures, assignments, study routines, and campus life.',
    ],
    'cs' => [
      'title' => 'CS core vocabulary',
      'description' => '15 starter CS words covering algorithms, code structure, debugging, data, and systems.',
    ],
    'mech' => [
      'title' => 'Mechanical engineering',
      'description' => '15 foundation words for motion, machines, materials, and manufacturing workflows.',
    ],
    'civil' => [
      'title' => 'Civil engineering',
      'description' => '15 construction and structure terms for materials, site work, and load transfer.',
    ],
    'traffic' => [
      'title' => 'Traffic & transport',
      'description' => '15 core terms for road systems, transit planning, traffic flow, and routing.',
    ],
    'math' => [
      'title' => 'Math foundations',
      'description' => '15 key words for algebra, calculus, geometry, and proof-based study.',
    ],
  ];
}

function vocab_book_meta(string $slug, ?string $fallbackTitle = null, ?string $fallbackDescription = null): array {
  $map = vocab_book_display_map();
  if (isset($map[$slug])) {
    return $map[$slug];
  }
  return [
    'title' => $fallbackTitle ?: $slug,
    'description' => $fallbackDescription ?: '',
  ];
}

function vocab_scene_url(string $word): string {
  global $baseHref;
  $base = $baseHref ?? '';
  return $base . 'word-scene.php?word=' . rawurlencode($word);
}

function vocab_book_cover_url(string $slug): string {
  global $baseHref;
  $base = $baseHref ?? '';
  return $base . 'book-cover.php?slug=' . rawurlencode($slug);
}

function vocab_book_cover_public_url(string $slug): string {
  return APP_VOCAB_ROOT_PATH . 'book-cover.php?slug=' . rawurlencode($slug);
}

function vocab_media_url(?string $url, ?string $word = null): string {
  $trimmed = trim((string)$url);
  if ($trimmed === '' || strpos($trimmed, 'placehold.co/') !== false) {
    return $word ? vocab_scene_url($word) : '';
  }
  if (preg_match('/^https?:\/\//i', $trimmed) === 1) {
    return $trimmed;
  }
  return ltrim($trimmed, '/');
}
