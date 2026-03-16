<?php
declare(strict_types=1);

function listening_mode(?string $mode, string $default = 'exam'): string
{
    return in_array($mode, ['practice', 'exam'], true) ? $mode : $default;
}
