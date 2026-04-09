<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $books = db()->query("SELECT COUNT(*) AS c FROM " . vocab_table('books'))->fetch();
  $words = db()->query("SELECT COUNT(*) AS c FROM " . vocab_table('words'))->fetch();
  $links = db()->query("SELECT COUNT(*) AS c FROM " . vocab_table('book_words'))->fetch();

  echo json_encode([
    'ok' => true,
    'books' => (int)($books['c'] ?? 0),
    'words' => (int)($words['c'] ?? 0),
    'links' => (int)($links['c'] ?? 0),
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
