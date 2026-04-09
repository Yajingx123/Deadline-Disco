<?php
declare(strict_types=1);

$runDir = dirname(__DIR__, 2) . '/.run';
$pidFiles = glob($runDir . '/*.pid') ?: [];

function killPid(int $pid): void
{
    if ($pid <= 0) {
        return;
    }
    shell_exec(sprintf('taskkill /PID %d /T /F 2>NUL', $pid));
}

function pidsByPort(int $port): array
{
    $output = shell_exec(sprintf('netstat -ano | findstr ":%d"', $port));
    if (!is_string($output) || trim($output) === '') {
        return [];
    }

    $pids = [];
    $lines = preg_split('/\R+/', trim($output)) ?: [];
    foreach ($lines as $line) {
        if (preg_match('/\s(\d+)\s*$/', trim($line), $m)) {
            $pid = (int)$m[1];
            if ($pid > 0) {
                $pids[$pid] = true;
            }
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

echo "[done] stop_all_windows completed.\n";

