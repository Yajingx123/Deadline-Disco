<?php
declare(strict_types=1);

$root = __DIR__;
$runDir = $root . '/.run';
if (!is_dir($runDir)) {
    mkdir($runDir, 0777, true);
}

$php = PHP_BINARY ?: 'php';
$npm = 'npm';

$services = [
    [
        'name' => 'main',
        'host' => '127.0.0.1',
        'port' => 8001,
        'workdir' => $root,
        'command' => escapeshellarg($php) . ' -S 127.0.0.1:8001 -t ' . escapeshellarg($root),
    ],
    [
        'name' => 'vocab',
        'host' => '127.0.0.1',
        'port' => 8002,
        'workdir' => $root . '/vocba_prac',
        'command' => escapeshellarg($php) . ' -S 127.0.0.1:8002 -t ' . escapeshellarg($root . '/vocba_prac'),
    ],
    [
        'name' => 'forum',
        'host' => '127.0.0.1',
        'port' => 5173,
        'workdir' => $root . '/forum-project',
        'command' => escapeshellarg($npm) . ' run dev -- --host 127.0.0.1 --port 5173',
    ],
];

function startMacProcess(string $command, string $workdir, string $stdoutLog, string $stderrLog): int {
    $shellCommand = sprintf(
        'cd %s && nohup %s > %s 2> %s < /dev/null & echo $!',
        escapeshellarg($workdir),
        $command,
        escapeshellarg($stdoutLog),
        escapeshellarg($stderrLog)
    );

    $output = shell_exec($shellCommand);
    $pid = (int)trim((string)$output);

    if ($pid <= 0) {
        throw new RuntimeException('Failed to start background process.');
    }

    return $pid;
}

echo "=== Start Services (macOS) ===\n\n";

foreach ($services as $service) {
    $name = $service['name'];
    $port = $service['port'];
    $host = $service['host'];
    $stdoutLog = "{$runDir}/{$name}_{$port}.out.log";
    $stderrLog = "{$runDir}/{$name}_{$port}.err.log";
    $pidFile = "{$runDir}/{$name}_{$port}.pid";

    try {
        $pid = startMacProcess(
            $service['command'],
            $service['workdir'],
            $stdoutLog,
            $stderrLog
        );
        file_put_contents($pidFile, (string)$pid);
        echo "[started] {$name} http://{$host}:{$port} (PID {$pid})\n";
    } catch (Throwable $e) {
        echo "[failed] {$name}: {$e->getMessage()}\n";
    }
}

echo "\nLogs are in .run\n";
echo "Start command: php start_all_mac.php\n";
echo "Auto-detect command: php start_all.php\n";
