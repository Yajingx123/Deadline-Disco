<?php
declare(strict_types=1);

$root = __DIR__;
$runDir = $root . '/.run';
if (!is_dir($runDir)) {
    mkdir($runDir, 0777, true);
}

$php = PHP_BINARY ?: 'php';
$npm = 'npm.cmd';

$services = [
    [
        'name' => 'main',
        'host' => '127.0.0.1',
        'port' => 8001,
        'workdir' => $root,
        'exe' => $php,
        'args' => ['-S', '127.0.0.1:8001', '-t', $root],
    ],
    [
        'name' => 'vocab',
        'host' => '127.0.0.1',
        'port' => 8002,
        'workdir' => $root . '/vocba_prac',
        'exe' => $php,
        'args' => ['-S', '127.0.0.1:8002', '-t', $root . '/vocba_prac'],
    ],
    [
        'name' => 'forum',
        'host' => '127.0.0.1',
        'port' => 5173,
        'workdir' => $root . '/forum-project',
        'exe' => $npm,
        'args' => ['run', 'dev', '--', '--host', '127.0.0.1', '--port', '5173'],
    ],
];

function pwshQuote(string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
}

function startWindowsProcess(string $exe, array $args, string $workdir, string $stdoutLog, string $stderrLog): int {
    $argList = '@(' . implode(',', array_map(
        static fn(string $arg): string => pwshQuote($arg),
        $args
    )) . ')';

    $command = implode('; ', [
        '$ErrorActionPreference = "Stop"',
        '$p = Start-Process -FilePath ' . pwshQuote($exe)
            . ' -ArgumentList ' . $argList
            . ' -WorkingDirectory ' . pwshQuote($workdir)
            . ' -PassThru -WindowStyle Hidden'
            . ' -RedirectStandardOutput ' . pwshQuote($stdoutLog)
            . ' -RedirectStandardError ' . pwshQuote($stderrLog),
        'Write-Output $p.Id',
    ]);

    $shell = 'powershell -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($command);
    $output = shell_exec($shell);
    $pid = (int)trim((string)$output);

    if ($pid <= 0) {
        throw new RuntimeException("Failed to start process: {$exe}");
    }

    return $pid;
}

echo "=== Start Services (Windows) ===\n\n";

foreach ($services as $service) {
    $name = $service['name'];
    $port = $service['port'];
    $host = $service['host'];
    $stdoutLog = "{$runDir}/{$name}_{$port}.out.log";
    $stderrLog = "{$runDir}/{$name}_{$port}.err.log";
    $pidFile = "{$runDir}/{$name}_{$port}.pid";

    try {
        $pid = startWindowsProcess(
            $service['exe'],
            $service['args'],
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
echo "Start command: php start_all_windows.php\n";
echo "Auto-detect command: php start_all.php\n";
