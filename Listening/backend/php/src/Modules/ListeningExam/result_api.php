<?php
declare(strict_types=1);

function api_get_exam_result(PDO $db, string $examId, string $userId, string $mode): void
{
    $stmt = $db->prepare(
        'SELECT mode, score, total, per_question_json, submitted_at
         FROM exam_results
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
        send_json(['result' => null]);
    }

    send_json([
        'result' => [
            'examId' => $examId,
            'mode' => $row['mode'],
            'score' => (int) $row['score'],
            'total' => (int) $row['total'],
            'perQuestion' => decode_json_column_value($row['per_question_json']) ?? [],
            'submittedAt' => (int) $row['submitted_at'],
        ],
    ]);
}

function api_save_exam_result(PDO $db, string $examId, string $userId, string $mode, array $body): void
{
    $bodyMode = listening_mode($body['mode'] ?? $mode, $mode);
    $stmt = $db->prepare(
        'INSERT INTO exam_results
          (user_id, exam_id, mode, score, total, per_question_json, submitted_at)
         VALUES
          (:user_id, :exam_id, :mode, :score, :total, :per_question_json, :submitted_at)
         ON DUPLICATE KEY UPDATE
          mode = VALUES(mode),
          score = VALUES(score),
          total = VALUES(total),
          per_question_json = VALUES(per_question_json),
          submitted_at = VALUES(submitted_at)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'exam_id' => $examId,
        'mode' => $bodyMode,
        'score' => (int) ($body['score'] ?? 0),
        'total' => (int) ($body['total'] ?? 0),
        'per_question_json' => encode_json_column_value($body['perQuestion'] ?? []),
        'submitted_at' => (int) ($body['submittedAt'] ?? round(microtime(true) * 1000)),
    ]);

    send_json(['ok' => true]);
}
