<?php
declare(strict_types=1);

$runDir = dirname(__DIR__, 2) . '/.run';
$pidFiles = glob($runDir . '/*.pid') ?: [];

function killPid(int $pid): void
{
    if ($pid <= 0) {
        return;
    }
    shell_exec(sprintf('kill %d 2>/dev/null', $pid));
    usleep(200000);
    shell_exec(sprintf('kill -9 %d 2>/dev/null', $pid));
}

function pidsByPort(int $port): array
{
    $output = shell_exec(sprintf('lsof -ti tcp:%d 2>/dev/null', $port));
    if (!is_string($output) || trim($output) === '') {
        $output = shell_exec(sprintf("ss -ltnp 'sport = :%d' 2>/dev/null | sed -n 's/.*pid=\\([0-9]\\+\\).*/\\1/p'", $port));
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
    $pid = (int)trim((string)@file_get_contents($pidFile));
    if ($pid > 0) {
        killPid($pid);
        echo "[stopped] PID {$pid}\n";
    }
    @unlink($pidFile);
}

foreach ([8001, 3001, 5500, 5173, 5174, 9000] as $port) {
    foreach (pidsByPort($port) as $pid) {
        killPid((int)$pid);
        echo "[stopped-by-port] {$port} (PID {$pid})\n";
    }
}

echo "[done] stop_all_linux completed.\n";

