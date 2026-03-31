<?php
declare(strict_types=1);

$family = PHP_OS_FAMILY;
$script = match ($family) {
    'Windows' => __DIR__ . '/start_all_windows.php',
    'Darwin' => __DIR__ . '/start_all_mac.php',
    default => null,
};

if ($script === null || !is_file($script)) {
    fwrite(STDERR, "Unsupported OS: {$family}\n");
    fwrite(STDERR, "Use start_all_windows.php or start_all_mac.php directly.\n");
    exit(1);
}

require $script;
