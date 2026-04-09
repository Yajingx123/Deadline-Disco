<?php
declare(strict_types=1);

$family = PHP_OS_FAMILY;
$script = match ($family) {
    'Windows' => __DIR__ . '/scripts/os/redeploy_windows.php',
    'Darwin' => __DIR__ . '/scripts/os/redeploy_mac.php',
    'Linux' => __DIR__ . '/scripts/os/redeploy_linux.php',
    default => null,
};

if ($script === null || !is_file($script)) {
    fwrite(STDERR, "Unsupported OS family: {$family}\n");
    fwrite(STDERR, "Expected: scripts/os/redeploy_windows.php | redeploy_mac.php | redeploy_linux.php\n");
    exit(1);
}

require $script;

