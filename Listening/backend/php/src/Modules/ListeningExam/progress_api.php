<?php
declare(strict_types=1);

function api_get_exam_progress(PDO $db, string $examId, string $userId, string $mode): void
{
    $stmt = $db->prepare(
        'SELECT mode, current_question, answers_json, audio_time, answered_questions,
                exam_duration_seconds, timer_seconds, exam_status, updated_at
         FROM exam_progress
         WHERE user_id = :user_id AND exam_id = :exam_id AND mode = :mode
         LIMIT 1'
    );
    $stmt->execute([
        'user_id' => $userId,
        'exam_id' => $examId,
        'mode' => $mode,
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        send_json(['progress' => null]);
    }

    send_json([
        'progress' => [
            'exam_id' => $examId,
            'mode' => $row['mode'],
            'current_question' => (int) $row['current_question'],
            'answers' => decode_json_column_value($row['answers_json']) ?? [],
            'audio_time' => (float) $row['audio_time'],
            'answered_questions' => (int) $row['answered_questions'],
            'exam_duration_seconds' => $row['exam_duration_seconds'] !== null ? (int) $row['exam_duration_seconds'] : null,
            'timer_seconds' => (int) $row['timer_seconds'],
            'exam_status' => $row['exam_status'],
            'updated_at' => isset($row['updated_at']) ? strtotime((string) $row['updated_at']) * 1000 : 0,
        ],
    ]);
}

function api_save_exam_progress(PDO $db, string $examId, string $userId, string $mode, array $body): void
{
    $bodyMode = listening_mode($body['mode'] ?? $mode, $mode);
    $stmt = $db->prepare(
        'INSERT INTO exam_progress
          (user_id, exam_id, mode, current_question, answers_json, audio_time,
           answered_questions, exam_duration_seconds, timer_seconds, exam_status)
         VALUES
          (:user_id, :exam_id, :mode, :current_question, :answers_json, :audio_time,
           :answered_questions, :exam_duration_seconds, :timer_seconds, :exam_status)
         ON DUPLICATE KEY UPDATE
          mode = VALUES(mode),
          current_question = VALUES(current_question),
          answers_json = VALUES(answers_json),
          audio_time = VALUES(audio_time),
          answered_questions = VALUES(answered_questions),
          exam_duration_seconds = VALUES(exam_duration_seconds),
          timer_seconds = VALUES(timer_seconds),
          exam_status = VALUES(exam_status)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'exam_id' => $examId,
        'mode' => $bodyMode,
        'current_question' => (int) ($body['current_question'] ?? 0),
        'answers_json' => encode_json_column_value($body['answers'] ?? []),
        'audio_time' => (float) ($body['audio_time'] ?? 0),
        'answered_questions' => (int) ($body['answered_questions'] ?? 0),
        'exam_duration_seconds' => array_key_exists('exam_duration_seconds', $body) && $body['exam_duration_seconds'] !== null
            ? (int) $body['exam_duration_seconds']
            : null,
        'timer_seconds' => (int) ($body['timer_seconds'] ?? 0),
        'exam_status' => (string) ($body['exam_status'] ?? 'in_progress'),
    ]);

    send_json(['ok' => true]);
}

function api_delete_exam_progress(PDO $db, string $examId, string $userId, string $mode): void
{
    $stmt = $db->prepare('DELETE FROM exam_progress WHERE user_id = :user_id AND exam_id = :exam_id AND mode = :mode');
    $stmt->execute([
        'user_id' => $userId,
        'exam_id' => $examId,
        'mode' => $mode,
    ]);

    send_json(['ok' => true]);
}
