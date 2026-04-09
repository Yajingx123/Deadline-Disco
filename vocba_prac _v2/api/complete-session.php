<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  vocab_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$data = vocab_read_json_body();
$userId = vocab_current_user_id();
$mode = (int)($data['mode'] ?? 0);
$selectedBooks = is_array($data['selectedBooks'] ?? null) ? array_values($data['selectedBooks']) : [];
$steps = is_array($data['steps'] ?? null) ? $data['steps'] : [];
$correctFirstTry = (int)($data['correctFirstTry'] ?? 0);
$startedAt = trim((string)($data['startedAt'] ?? ''));
$completedAt = trim((string)($data['completedAt'] ?? ''));

if (!in_array($mode, [1, 5, 10], true)) {
  vocab_json_response(['ok' => false, 'message' => 'Invalid session mode.'], 422);
}

if (!$steps) {
  vocab_json_response(['ok' => false, 'message' => 'No session steps received.'], 422);
}

$sessionsTable = vocab_table('sessions');
$itemsTable = vocab_table('session_items');
$responsesTable = vocab_table('session_responses');
$progressTable = vocab_table('user_progress');

$startedAt = $startedAt !== '' ? $startedAt : vocab_now();
$completedAt = $completedAt !== '' ? $completedAt : vocab_now();

$pdo = db();
$pdo->beginTransaction();

try {
  $sessionStmt = $pdo->prepare("
    INSERT INTO {$sessionsTable}
      (user_id, mode_minutes, status, selected_books_snapshot, total_steps, correct_first_try, started_at, completed_at)
    VALUES
      (?, ?, 'completed', ?, ?, ?, ?, ?)
  ");
  $sessionStmt->execute([
    $userId,
    $mode,
    json_encode(array_values($selectedBooks), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    count($steps),
    $correctFirstTry,
    $startedAt,
    $completedAt,
  ]);
  $sessionId = (int)$pdo->lastInsertId();

  $itemStmt = $pdo->prepare("
    INSERT INTO {$itemsTable}
      (session_id, word_id, item_type, step_order, prompt_data, options_data, correct_answer, attempt_count, first_attempt_correct, completed_at)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  $responseStmt = $pdo->prepare("
    INSERT INTO {$responsesTable}
      (session_item_id, user_id, response_text, is_correct, attempt_no, answered_at)
    VALUES
      (?, ?, ?, ?, ?, ?)
  ");
  $progressUpsertStmt = $pdo->prepare("
    INSERT INTO {$progressTable}
      (user_id, word_id, times_seen, correct_count, wrong_count, first_try_correct_count, mastery_status, last_session_id, last_practiced_at)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      times_seen = times_seen + VALUES(times_seen),
      correct_count = correct_count + VALUES(correct_count),
      wrong_count = wrong_count + VALUES(wrong_count),
      first_try_correct_count = first_try_correct_count + VALUES(first_try_correct_count),
      last_session_id = VALUES(last_session_id),
      last_practiced_at = VALUES(last_practiced_at)
  ");

  $wordStats = [];
  foreach ($steps as $index => $step) {
    $wordId = (int)($step['wordId'] ?? 0);
    $itemType = trim((string)($step['type'] ?? ''));
    if ($wordId <= 0 || $itemType === '' || $itemType === 'learn') {
      continue;
    }

    $responses = is_array($step['responses'] ?? null) ? $step['responses'] : [];
    $attemptCount = count($responses);
    $firstAttemptCorrect = null;
    if ($attemptCount > 0) {
      $firstAttemptCorrect = !empty($responses[0]['isCorrect']) ? 1 : 0;
    }

    $itemStmt->execute([
      $sessionId,
      $wordId,
      $itemType,
      $index + 1,
      isset($step['promptData']) ? json_encode($step['promptData'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
      isset($step['options']) ? json_encode($step['options'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
      (string)($step['correctAnswer'] ?? ''),
      $attemptCount,
      $firstAttemptCorrect,
      $completedAt,
    ]);
    $sessionItemId = (int)$pdo->lastInsertId();

    if (!isset($wordStats[$wordId])) {
      $wordStats[$wordId] = [
        'times_seen' => 0,
        'correct_count' => 0,
        'wrong_count' => 0,
        'first_try_correct_count' => 0,
      ];
    }
    $wordStats[$wordId]['times_seen']++;

    foreach ($responses as $responseIndex => $response) {
      $isCorrect = !empty($response['isCorrect']) ? 1 : 0;
      $responseStmt->execute([
        $sessionItemId,
        $userId,
        (string)($response['responseText'] ?? ''),
        $isCorrect,
        $responseIndex + 1,
        (string)($response['answeredAt'] ?? $completedAt),
      ]);
      if ($isCorrect) {
        $wordStats[$wordId]['correct_count']++;
        if ($responseIndex === 0) {
          $wordStats[$wordId]['first_try_correct_count']++;
        }
      } else {
        $wordStats[$wordId]['wrong_count']++;
      }
    }
  }

  $currentProgress = vocab_user_progress_map($userId);
  foreach ($wordStats as $wordId => $stats) {
    $existingStatus = $currentProgress[$wordId]['mastery_status'] ?? 'new';
    $progressUpsertStmt->execute([
      $userId,
      $wordId,
      $stats['times_seen'],
      $stats['correct_count'],
      $stats['wrong_count'],
      $stats['first_try_correct_count'],
      $existingStatus,
      $sessionId,
      $completedAt,
    ]);
  }

  $pdo->commit();

  vocab_json_response([
    'ok' => true,
    'sessionId' => $sessionId,
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  vocab_json_response([
    'ok' => false,
    'message' => 'Failed to save session.',
    'error' => $e->getMessage(),
  ], 500);
}

