<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$userId = vocab_current_user_id();
$booksTable = vocab_table('books');
$bookWordsTable = vocab_table('book_words');
$selectionTable = vocab_table('user_selections');
$sessionsTable = vocab_table('sessions');
$itemsTable = vocab_table('session_items');
$wordsTable = vocab_table('words');
$progressTable = vocab_table('user_progress');

$recordsStmt = db()->prepare("
  SELECT
    s.session_id,
    s.mode_minutes,
    s.correct_first_try,
    s.total_steps,
    s.completed_at,
    GROUP_CONCAT(DISTINCT w.word ORDER BY w.word SEPARATOR '||') AS words
  FROM {$sessionsTable} s
  LEFT JOIN {$itemsTable} si ON si.session_id = s.session_id
  LEFT JOIN {$wordsTable} w ON w.word_id = si.word_id
  WHERE s.user_id = ? AND s.status = 'completed'
  GROUP BY s.session_id, s.mode_minutes, s.correct_first_try, s.total_steps, s.completed_at
  ORDER BY s.completed_at DESC
  LIMIT 6
");
$recordsStmt->execute([$userId]);
$records = [];
foreach ($recordsStmt->fetchAll() as $row) {
  $words = array_values(array_filter(explode('||', (string)($row['words'] ?? ''))));
  $mode = (int)($row['mode_minutes'] ?? 1);
  $records[] = [
    'sessionId' => (int)$row['session_id'],
    'title' => $mode === 1 ? '1-minute session' : ($mode === 5 ? '3-5 minute session' : '10-minute session'),
    'completedAt' => (string)($row['completed_at'] ?? ''),
    'correctFirstTry' => (int)($row['correct_first_try'] ?? 0),
    'totalSteps' => (int)($row['total_steps'] ?? 0),
    'words' => array_slice($words, 0, 8),
  ];
}

$favoritesStmt = db()->prepare("
  SELECT
    wb.word_book_id,
    wb.slug,
    wb.title,
    wb.description,
    COUNT(DISTINCT wbw.word_id) AS total_words,
    SUM(CASE WHEN up.mastery_status = 'mastered' THEN 1 ELSE 0 END) AS mastered_words
  FROM {$selectionTable} sel
  JOIN {$booksTable} wb ON wb.word_book_id = sel.word_book_id
  LEFT JOIN {$bookWordsTable} wbw ON wbw.word_book_id = wb.word_book_id
  LEFT JOIN {$progressTable} up
    ON up.word_id = wbw.word_id
   AND up.user_id = sel.user_id
  WHERE sel.user_id = ?
  GROUP BY wb.word_book_id, wb.slug, wb.title, wb.description
  ORDER BY wb.word_book_id ASC
");
$favoritesStmt->execute([$userId]);
$favorites = [];
foreach ($favoritesStmt->fetchAll() as $row) {
  $slug = (string)($row['slug'] ?? '');
  $meta = vocab_book_meta($slug, (string)($row['title'] ?? $slug), (string)($row['description'] ?? ''));
  $total = (int)($row['total_words'] ?? 0);
  $mastered = (int)($row['mastered_words'] ?? 0);
  $favorites[] = [
    'slug' => $slug,
    'title' => (string)$meta['title'],
    'description' => (string)$meta['description'],
    'coverUrl' => vocab_book_cover_public_url($slug),
    'totalWords' => $total,
    'masteredWords' => $mastered,
    'progressPercent' => $total > 0 ? (int)round(($mastered / $total) * 100) : 0,
  ];
}

vocab_json_response([
  'ok' => true,
  'records' => $records,
  'favorites' => $favorites,
]);
