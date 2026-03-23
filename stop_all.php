<?php
declare(strict_types=1);

$pidFiles = [
    __DIR__ . '/.run/main_8001.pid',
    __DIR__ . '/.run/vocab_8002.pid',
    __DIR__ . '/.run/forum_5173.pid',
];

// Windows 下杀死进程
function killProcess(int $pid): void {
    if ($pid <= 0) return;
    // 先尝试优雅终止，失败则强制杀死
    shell_exec(sprintf('taskkill /PID %d /T /F 2>NUL', $pid));
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
