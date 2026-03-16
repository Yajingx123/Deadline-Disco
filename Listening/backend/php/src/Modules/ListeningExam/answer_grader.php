<?php
declare(strict_types=1);

function answer_is_correct(array $question, mixed $answer): bool
{
    $type = $question['type'] ?? '';
    $correct = $question['correctAnswer'] ?? null;

    if ($type === 'multiple_choice') {
        return $answer === $correct;
    }

    if ($type === 'multiple_select') {
        if (!is_array($answer) || !is_array($correct)) {
            return false;
        }
        sort($answer);
        sort($correct);
        return $answer === $correct;
    }

    if ($type === 'fill_blank') {
        $given = strtolower(trim((string) ($answer ?? '')));
        $expected = strtolower(trim((string) ($correct ?? '')));
        return $given !== '' && $given === $expected;
    }

    if ($type === 'matching') {
        if (!is_array($answer) || !is_array($correct)) {
            return false;
        }
        ksort($answer);
        ksort($correct);
        return $answer === $correct;
    }

    if ($type === 'ordering') {
        return is_array($answer) && is_array($correct) && $answer === $correct;
    }

    return false;
}
