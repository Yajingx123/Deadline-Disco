<?php
declare(strict_types=1);

/**
 * Unified start entrypoint.
 * Auto-dispatch to OS-specific starter scripts.
 */

echo ">>> Browser entry: http://127.0.0.1:8001/home.html\n\n";

$family = PHP_OS_FAMILY;
$script = match ($family) {
    'Windows' => __DIR__ . '/start_all_windows.php',
    'Darwin' => __DIR__ . '/start_all_mac.php',
    'Linux' => __DIR__ . '/start_all_linux.php',
    default => null,
};

if ($script === null || !is_file($script)) {
    fwrite(STDERR, "Unsupported OS family: {$family}\n");
    fwrite(STDERR, "Please run an OS-specific script manually.\n");
    fwrite(STDERR, "Expected files: start_all_windows.php / start_all_mac.php / start_all_linux.php\n");
    exit(1);
}

require $script;
