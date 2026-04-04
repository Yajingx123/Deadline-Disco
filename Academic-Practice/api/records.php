<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    listening_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$user = listening_require_user();
$pdo = listening_db();
listening_ensure_table($pdo);

$recordId = (int)($_GET['id'] ?? 0);

if ($recordId > 0) {
    $stmt = $pdo->prepare("
        SELECT record_id, video_id, mode, title, person_meta, difficulty, duration_label, source_name, country_name,
               main_content, key_word, personal_view, created_at
        FROM listening_practice_records
        WHERE record_id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$recordId, (int)$user['user_id']]);
    $row = $stmt->fetch();
    if (!$row) {
        listening_json(['ok' => false, 'message' => 'Record not found.'], 404);
    }
    listening_json([
        'ok' => true,
        'record' => [
            'recordId' => (int)$row['record_id'],
            'videoId' => (string)$row['video_id'],
            'mode' => (string)$row['mode'],
            'title' => (string)$row['title'],
            'personMeta' => (string)($row['person_meta'] ?? ''),
            'difficulty' => (string)($row['difficulty'] ?? ''),
            'duration' => (string)($row['duration_label'] ?? ''),
            'source' => (string)($row['source_name'] ?? ''),
            'country' => (string)($row['country_name'] ?? ''),
            'mainContent' => (string)($row['main_content'] ?? ''),
            'keyWord' => (string)($row['key_word'] ?? ''),
            'personalView' => (string)($row['personal_view'] ?? ''),
            'createdAt' => (string)($row['created_at'] ?? ''),
        ],
    ]);
}

$stmt = $pdo->prepare("
    SELECT record_id, video_id, mode, title, person_meta, difficulty, duration_label, source_name, country_name,
           main_content, key_word, personal_view, created_at
    FROM listening_practice_records
    WHERE user_id = ?
    ORDER BY created_at DESC, record_id DESC
    LIMIT 12
");
$stmt->execute([(int)$user['user_id']]);

$records = array_map(static function (array $row): array {
    return [
        'recordId' => (int)$row['record_id'],
        'videoId' => (string)$row['video_id'],
        'mode' => (string)$row['mode'],
        'title' => (string)$row['title'],
        'personMeta' => (string)($row['person_meta'] ?? ''),
        'difficulty' => (string)($row['difficulty'] ?? ''),
        'duration' => (string)($row['duration_label'] ?? ''),
        'source' => (string)($row['source_name'] ?? ''),
        'country' => (string)($row['country_name'] ?? ''),
        'mainContent' => (string)($row['main_content'] ?? ''),
        'keyWord' => (string)($row['key_word'] ?? ''),
        'personalView' => (string)($row['personal_view'] ?? ''),
        'createdAt' => (string)($row['created_at'] ?? ''),
    ];
}, $stmt->fetchAll());

listening_json([
    'ok' => true,
    'records' => $records,
]);
