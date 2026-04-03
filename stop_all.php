<?php
declare(strict_types=1);

$pidFiles = [
    __DIR__ . '/.run/main_8001.pid',
    __DIR__ . '/.run/vocab_8002.pid',
    __DIR__ . '/.run/forum_5173.pid',
    __DIR__ . '/.run/admin_5174.pid',
    __DIR__ . '/.run/realtime_3001.pid',
    __DIR__ . '/.run/godot_ui_5500.pid',
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

function pidsByPortWindows(int $port): array {
    $output = shell_exec(sprintf('netstat -ano | findstr ":%d"', $port));
    if (!is_string($output) || trim($output) === '') {
        return [];
    }

    $pids = [];
    $lines = preg_split('/\R+/', trim($output)) ?: [];
    foreach ($lines as $line) {
        if (!preg_match('/\s(\d+)\s*$/', trim($line), $m)) {
            continue;
        }
        $pid = (int)$m[1];
        if ($pid > 0) {
            $pids[$pid] = true;
        }
    }
    return array_keys($pids);
}

function pidsByPortUnix(int $port): array {
    $output = shell_exec(sprintf('lsof -ti tcp:%d 2>/dev/null', $port));
    if (!is_string($output) || trim($output) === '') {
        return [];
    }
    $pids = [];
    $lines = preg_split('/\R+/', trim($output)) ?: [];
    foreach ($lines as $line) {
        $pid = (int)trim($line);
        if ($pid > 0) {
            $pids[$pid] = true;
        }
    }
    return array_keys($pids);
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

$ports = [8001, 8002, 5173, 5174, 3001, 5500];
foreach ($ports as $port) {
    $pids = PHP_OS_FAMILY === 'Windows' ? pidsByPortWindows($port) : pidsByPortUnix($port);
    foreach ($pids as $pid) {
        killProcess((int)$pid);
        echo "[stopped-by-port] {$port} (PID {$pid})\n";
    }
}
