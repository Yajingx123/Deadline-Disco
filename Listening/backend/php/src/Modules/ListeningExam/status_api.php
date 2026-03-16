<?php
declare(strict_types=1);

function api_get_exam_statuses(PDO $db, string $userId, string $mode): void
{
    $examStmt = $db->query('SELECT id FROM exams');
    $resultStmt = $db->prepare(
        'SELECT score, total FROM exam_results WHERE user_id = :user_id AND exam_id = :exam_id AND mode = :mode LIMIT 1'
    );

    $statuses = [];
    foreach ($examStmt->fetchAll() as $examRow) {
        $examId = $examRow['id'];
        $resultStmt->execute([
            'user_id' => $userId,
            'exam_id' => $examId,
            'mode' => $mode,
        ]);
        $result = $resultStmt->fetch();

        $statuses[$examId] = $result
            ? [
                'status' => 'Completed',
                'answeredQuestions' => (int) $result['total'],
                'bestScore' => sprintf('%d/%d', (int) $result['score'], (int) $result['total']),
            ]
            : [
                'status' => 'Not Started',
                'answeredQuestions' => 0,
                'bestScore' => '-',
            ];
    }

    send_json(['statuses' => $statuses]);
}
