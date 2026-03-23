<?php
declare(strict_types=1);

$pidFiles = [
    __DIR__ . '/.run/main_8001.pid',
    __DIR__ . '/.run/vocab_8002.pid',
    __DIR__ . '/.run/forum_5173.pid',
];

foreach ($pidFiles as $pidFile) {
    if (!file_exists($pidFile)) {
        continue;
    }

    $pid = (int)trim((string)file_get_contents($pidFile));
    if ($pid > 0) {
        if (function_exists('posix_kill')) {
            @posix_kill($pid, SIGTERM);
        } else {
            @shell_exec('kill ' . (int)$pid);
        }
        echo "[stopped] PID {$pid}\n";
    }

    @unlink($pidFile);
}
