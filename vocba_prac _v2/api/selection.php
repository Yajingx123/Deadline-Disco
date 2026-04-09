<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$userId = vocab_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  vocab_json_response([
    'ok' => true,
    'selectedBooks' => vocab_selected_book_slugs($userId),
  ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  vocab_json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$data = vocab_read_json_body();
$selectedBooks = is_array($data['selectedBooks'] ?? null) ? $data['selectedBooks'] : [];
$saved = vocab_save_selected_book_slugs($userId, $selectedBooks);

vocab_json_response([
  'ok' => true,
  'selectedBooks' => $saved,
]);

