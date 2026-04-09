<?php
declare(strict_types=1);

$family = PHP_OS_FAMILY;
$script = match ($family) {
    'Windows' => __DIR__ . '/scripts/os/shutdown_windows.php',
    'Darwin' => __DIR__ . '/scripts/os/shutdown_mac.php',
    'Linux' => __DIR__ . '/scripts/os/shutdown_linux.php',
    default => null,
};

if ($script === null || !is_file($script)) {
    fwrite(STDERR, "Unsupported OS family: {$family}\n");
    fwrite(STDERR, "Expected: scripts/os/shutdown_windows.php | shutdown_mac.php | shutdown_linux.php\n");
    exit(1);
}

require $script;

