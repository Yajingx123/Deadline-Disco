<?php
declare(strict_types=1);

function decode_json_column_value(mixed $value): mixed
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_array($value)) {
        return $value;
    }
    $decoded = json_decode((string) $value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
}

function encode_json_column_value(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
