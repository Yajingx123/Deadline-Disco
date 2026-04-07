<?php
declare(strict_types=1);

/**
 * Unified start entrypoint.
 * Auto-dispatch to OS-specific starter scripts.
 */

echo ">>> Browser entry: http://127.0.0.1:8001/home.html\n";

$argv = $_SERVER['argv'] ?? [];
$profile = in_array('--full', $argv, true) ? 'full' : 'simple';
putenv('ACADBEAT_START_PROFILE=' . $profile);
echo ">>> Start profile: {$profile} (use --full to start extra dev services)\n\n";

$pck = __DIR__ . '/gameUI_src/Release/index.pck';
$watchFiles = [
    __DIR__ . '/gameUI_src/Scripts/homepage/door.gd',
    __DIR__ . '/gameUI_src/Scripts/global/right_side_btn.gd',
    __DIR__ . '/gameUI_src/Scripts/global/external_link.gd',
];
if (is_file($pck)) {
    $pckTime = filemtime($pck) ?: 0;
    $stale = [];
    foreach ($watchFiles as $file) {
        if (is_file($file) && (filemtime($file) ?: 0) > $pckTime) {
            $stale[] = basename($file);
        }
    }
    if (!empty($stale)) {
        echo ">>> Warning: Godot Web export may be stale.\n";
        echo "    index.pck is older than: " . implode(', ', $stale) . "\n";
        echo "    Re-export Web to gameUI_src/Release/index.html before testing lobby links.\n\n";
    }
}

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
