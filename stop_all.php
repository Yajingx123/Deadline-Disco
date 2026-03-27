<?php
declare(strict_types=1);

$pidFiles = [
    __DIR__ . '/.run/main_8001.pid',
    __DIR__ . '/.run/vocab_8002.pid',
    __DIR__ . '/.run/forum_5173.pid',
    __DIR__ . '/.run/realtime_3001.pid',
];

function killProcess(int $pid): void {
    if ($pid <= 0) return;
    if (PHP_OS_FAMILY === 'Windows') {
        shell_exec(sprintf('taskkill /PID %d /T /F 2>NUL', $pid));
        return;
    }

    shell_exec(sprintf('kill %d 2>/dev/null', $pid));
    usleep(200000);
    shell_exec(sprintf('kill -9 %d 2>/dev/null', $pid));
}

foreach ($pidFiles as $pidFile) {
    if (!file_exists($pidFile)) {
        continue;
    }

    $pid = (int)trim((string)file_get_contents($pidFile));
    if ($pid > 0) {
        killProcess($pid);
        echo "[stopped] PID {$pid}\n";
    }

    @unlink($pidFile);
}
