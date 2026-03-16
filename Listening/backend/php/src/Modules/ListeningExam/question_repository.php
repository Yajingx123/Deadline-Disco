<?php
declare(strict_types=1);

function load_exam_questions(PDO $db, string $examId): array
{
    $stmt = $db->prepare(
        'SELECT question_key, type, question_text, options_json, left_items_json,
                right_items_json, ordering_items_json, correct_answer_json,
                explanation, transcript_reference
         FROM questions
         WHERE exam_id = :exam_id
         ORDER BY sort_order ASC'
    );
    $stmt->execute(['exam_id' => $examId]);

    $questions = [];
    foreach ($stmt->fetchAll() as $row) {
        $question = [
            'id' => $row['question_key'],
            'type' => $row['type'],
            'questionText' => $row['question_text'],
            'correctAnswer' => decode_json_column_value($row['correct_answer_json']),
            'explanation' => $row['explanation'],
            'transcriptReference' => $row['transcript_reference'],
        ];

        $optionalMappings = [
            'options' => $row['options_json'],
            'leftItems' => $row['left_items_json'],
            'rightItems' => $row['right_items_json'],
            'orderingItems' => $row['ordering_items_json'],
        ];

        foreach ($optionalMappings as $key => $rawValue) {
            $decoded = decode_json_column_value($rawValue);
            if ($decoded !== null) {
                $question[$key] = $decoded;
            }
        }

        $questions[] = $question;
    }

    return $questions;
}
