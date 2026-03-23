<?php
declare(strict_types=1);

$root = __DIR__;
$runDir = $root . '/.run';
if (!is_dir($runDir)) {
    mkdir($runDir, 0777, true);
}

$php = PHP_BINARY ?: 'php';
$npm = trim((string)shell_exec('command -v npm'));
$services = [
    [
        'name' => 'main',
        'host' => '127.0.0.1',
        'port' => 8001,
        'docroot' => $root,
        'pid_file' => $runDir . '/main_8001.pid',
        'log_file' => $runDir . '/main_8001.log',
        'url' => 'http://127.0.0.1:8001/home.html',
    ],
    [
        'name' => 'vocab',
        'host' => '127.0.0.1',
        'port' => 8002,
        'docroot' => $root . '/vocba_prac',
        'pid_file' => $runDir . '/vocab_8002.pid',
        'log_file' => $runDir . '/vocab_8002.log',
        'url' => 'http://127.0.0.1:8002/',
    ],
    [
        'name' => 'forum',
        'host' => '127.0.0.1',
        'port' => 5173,
        'docroot' => $root . '/forum-project',
        'pid_file' => $runDir . '/forum_5173.pid',
        'log_file' => $runDir . '/forum_5173.log',
        'url' => 'http://127.0.0.1:5173/',
        'type' => 'vite',
    ],
];

function processAlive(int $pid): bool {
    if ($pid <= 0) return false;
    return function_exists('posix_kill') ? @posix_kill($pid, 0) : false;
}

foreach ($services as $service) {
    $pidFile = $service['pid_file'];
    if (file_exists($pidFile)) {
        $existingPid = (int)trim((string)file_get_contents($pidFile));
        if ($existingPid > 0 && processAlive($existingPid)) {
            echo "[running] {$service['name']} on {$service['url']} (PID {$existingPid})\n";
            continue;
        }
        @unlink($pidFile);
    }

    if (($service['type'] ?? 'php') === 'vite') {
        if ($npm === '') {
            echo "[failed] {$service['name']} could not start (npm not found)\n";
            continue;
        }
        $command = sprintf(
            'cd %s && %s run dev -- --host %s --port %d > %s 2>&1 & echo $!',
            escapeshellarg($service['docroot']),
            escapeshellarg($npm),
            escapeshellarg($service['host']),
            $service['port'],
            escapeshellarg($service['log_file'])
        );
    } else {
        $command = sprintf(
            '%s -S %s:%d -t %s > %s 2>&1 & echo $!',
            escapeshellarg($php),
            $service['host'],
            $service['port'],
            escapeshellarg($service['docroot']),
            escapeshellarg($service['log_file'])
        );
    }

    $pid = (int)trim((string)shell_exec($command));
    if ($pid <= 0) {
        echo "[failed] {$service['name']} could not start\n";
        continue;
    }

    file_put_contents($pidFile, (string)$pid);
    echo "[started] {$service['name']} on {$service['url']} (PID {$pid})\n";
}

echo "\nLogs: ./.run/main_8001.log, ./.run/vocab_8002.log, and ./.run/forum_5173.log\n";
