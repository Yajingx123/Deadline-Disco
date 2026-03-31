<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    listening_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = listening_require_user();
$input = listening_input();
$videoId = trim((string)($input['videoId'] ?? ''));
$title = trim((string)($input['title'] ?? ''));

if ($videoId === '' || $title === '') {
    listening_json(['ok' => false, 'message' => 'Video information is required.'], 422);
}

$pdo = listening_db();
listening_ensure_table($pdo);

$stmt = $pdo->prepare("
    INSERT INTO listening_practice_records (
        user_id, video_id, mode, title, person_meta, difficulty, duration_label, source_name, country_name,
        main_content, key_word, personal_view
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    (int)$user['user_id'],
    $videoId,
    trim((string)($input['mode'] ?? 'understand')),
    $title,
    trim((string)($input['personMeta'] ?? '')),
    trim((string)($input['difficulty'] ?? '')),
    trim((string)($input['duration'] ?? '')),
    trim((string)($input['source'] ?? '')),
    trim((string)($input['country'] ?? '')),
    trim((string)($input['mainContent'] ?? '')),
    trim((string)($input['keyWord'] ?? '')),
    trim((string)($input['personalView'] ?? '')),
]);

$recordId = (int)$pdo->lastInsertId();

listening_json([
    'ok' => true,
    'message' => 'Saved to your study record.',
    'recordId' => $recordId,
]);
