<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  vocab_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$data = vocab_read_json_body();
$userId = vocab_current_user_id();
$wordId = (int)($data['wordId'] ?? 0);
$status = vocab_normalize_status((string)($data['status'] ?? 'new'));
$sessionId = isset($data['sessionId']) ? (int)$data['sessionId'] : null;

if ($wordId <= 0) {
  vocab_json_response(['ok' => false, 'message' => 'Invalid word id.'], 422);
}

$progressTable = vocab_table('user_progress');
$now = vocab_now();
$stmt = db()->prepare("
  INSERT INTO {$progressTable}
    (user_id, word_id, mastery_status, status_set_at, last_session_id, last_practiced_at)
  VALUES
    (?, ?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    mastery_status = VALUES(mastery_status),
    status_set_at = VALUES(status_set_at),
    last_session_id = COALESCE(VALUES(last_session_id), last_session_id),
    last_practiced_at = VALUES(last_practiced_at)
");
$stmt->execute([
  $userId,
  $wordId,
  $status,
  $now,
  $sessionId ?: null,
  $now,
]);

vocab_json_response([
  'ok' => true,
  'wordId' => $wordId,
  'status' => $status,
  'savedAt' => $now,
]);

