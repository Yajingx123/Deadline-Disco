<?php
declare(strict_types=1);

function api_health_check(): void
{
    send_json(['ok' => true, 'service' => 'listening-exam-php-backend']);
}

function api_list_exams(PDO $db): void
{
    $stmt = $db->query(
        'SELECT e.id, e.title, e.difficulty, e.duration_seconds, COUNT(q.id) AS question_count
         FROM exams e
         LEFT JOIN questions q ON q.exam_id = e.id
         GROUP BY e.id, e.title, e.difficulty, e.duration_seconds
         ORDER BY e.created_at ASC'
    );

    $exams = array_map(static function (array $row): array {
        return [
            'id' => $row['id'],
            'title' => $row['title'],
            'difficulty' => $row['difficulty'],
            'durationSeconds' => (int) $row['duration_seconds'],
            'questionCount' => (int) ($row['question_count'] ?? 0),
        ];
    }, $stmt->fetchAll());

    send_json(['exams' => $exams]);
}

function api_get_exam(PDO $db, string $examId): void
{
    $stmt = $db->prepare('SELECT * FROM exams WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $examId]);
    $exam = $stmt->fetch();

    if (!$exam) {
        send_error('Exam not found', 404);
    }

    send_json([
        'exam' => [
            'id' => $exam['id'],
            'title' => $exam['title'],
            'difficulty' => $exam['difficulty'],
            'durationSeconds' => (int) $exam['duration_seconds'],
            'audioUrl' => $exam['audio_url'],
            'transcript' => $exam['transcript'],
            'questions' => load_exam_questions($db, $examId),
        ],
    ]);
}
