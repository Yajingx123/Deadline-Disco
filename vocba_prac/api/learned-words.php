<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$userId = vocab_current_user_id();
$wordsTable = vocab_table('words');
$progressTable = vocab_table('user_progress');

$stmt = db()->prepare("
  SELECT DISTINCT UPPER(w.word) AS word
  FROM {$progressTable} up
  INNER JOIN {$wordsTable} w ON w.word_id = up.word_id
  WHERE up.user_id = ?
    AND up.mastery_status = 'mastered'
  ORDER BY word ASC
");
$stmt->execute([$userId]);

$words = [];
foreach ($stmt->fetchAll() as $row) {
  $word = strtoupper(trim((string)($row['word'] ?? '')));
  $word = preg_replace('/[^A-Z]/', '', $word);
  if (!is_string($word) || $word === '') {
    continue;
  }
  if (strlen($word) < 2 || strlen($word) > 15) {
    continue;
  }
  $words[] = $word;
}

vocab_json_response([
  'ok' => true,
  'words' => array_values(array_unique($words)),
]);

