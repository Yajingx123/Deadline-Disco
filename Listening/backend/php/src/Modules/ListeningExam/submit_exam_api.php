<?php
declare(strict_types=1);

function api_submit_exam(PDO $db, string $examId, string $userId, string $mode, array $body): void
{
    $submitMode = listening_mode($body['mode'] ?? $mode, $mode);
    $answers = is_array($body['answers'] ?? null) ? $body['answers'] : [];

    $examStmt = $db->prepare('SELECT id FROM exams WHERE id = :id LIMIT 1');
    $examStmt->execute(['id' => $examId]);
    if (!$examStmt->fetch()) {
        send_error('Exam not found', 404);
    }

    $questions = load_exam_questions($db, $examId);
    $perQuestion = [];
    $score = 0;

    // Build the review payload and score in one pass so frontend review can render directly.
    foreach ($questions as $question) {
        $questionId = $question['id'];
        $userAnswer = $answers[$questionId] ?? null;
        $correct = answer_is_correct($question, $userAnswer);
        if ($correct) {
            $score += 1;
        }

        $perQuestion[] = [
            'questionId' => $questionId,
            'correct' => $correct,
            'userAnswer' => $userAnswer,
            'correctAnswer' => $question['correctAnswer'] ?? null,
            'explanation' => $question['explanation'] ?? null,
        ];
    }

    $result = [
        'examId' => $examId,
        'mode' => $submitMode,
        'score' => $score,
        'total' => count($questions),
        'perQuestion' => $perQuestion,
        'submittedAt' => (int) round(microtime(true) * 1000),
    ];

    $resultStmt = $db->prepare(
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
    $resultStmt->execute([
        'user_id' => $userId,
        'exam_id' => $examId,
        'mode' => $submitMode,
        'score' => $result['score'],
        'total' => $result['total'],
        'per_question_json' => encode_json_column_value($result['perQuestion']),
        'submitted_at' => $result['submittedAt'],
    ]);

    $deleteStmt = $db->prepare('DELETE FROM exam_progress WHERE user_id = :user_id AND exam_id = :exam_id AND mode = :mode');
    $deleteStmt->execute([
        'user_id' => $userId,
        'exam_id' => $examId,
        'mode' => $submitMode,
    ]);

    send_json(['result' => $result]);
}
