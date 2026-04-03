<?php
declare(strict_types=1);

$runDir = __DIR__ . '/.run';
$pidFiles = glob($runDir . '/*.pid') ?: [];

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
        $output = shell_exec(sprintf("ss -ltnp 'sport = :%d' 2>/dev/null | sed -n 's/.*pid=\\([0-9]\\+\\).*/\\1/p'", $port));
    }
    if (!is_string($output) || trim($output) === '') {
        $output = shell_exec(sprintf("netstat -ltnp 2>/dev/null | awk '/:%d[[:space:]]/ {print $7}' | cut -d'/' -f1", $port));
    }
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

echo "[done] stop_all completed.\n";
