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
$audioData = trim((string)($input['audioData'] ?? ''));

if ($videoId === '' || $title === '' || $audioData === '') {
    listening_json(['ok' => false, 'message' => 'Recording data is required.'], 422);
}

$pdo = listening_db();
integrated_ensure_table($pdo);

$stmt = $pdo->prepare("
    INSERT INTO integrated_response_records (
        user_id, video_id, mode, title, person_meta, difficulty, duration_label, source_name, country_name, audio_data, audio_mime
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    (int)$user['user_id'],
    $videoId,
    trim((string)($input['mode'] ?? 'respond')),
    $title,
    trim((string)($input['personMeta'] ?? '')),
    trim((string)($input['difficulty'] ?? '')),
    trim((string)($input['duration'] ?? '')),
    trim((string)($input['source'] ?? '')),
    trim((string)($input['country'] ?? '')),
    $audioData,
    trim((string)($input['audioMime'] ?? 'audio/webm')),
]);

listening_json([
    'ok' => true,
    'message' => 'Saved to your study record.',
    'recordId' => (int)$pdo->lastInsertId(),
]);
