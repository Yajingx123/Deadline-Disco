<?php
declare(strict_types=1);

$root = __DIR__;
$runDir = $root . '/.run';
if (!is_dir($runDir)) {
    mkdir($runDir, 0777, true);
}

$php = PHP_BINARY ?: 'php';
$npm = 'npm';

function sh_quote(string $value): string
{
    return escapeshellarg($value);
}

function resolve_binary(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        $output = shell_exec('command -v ' . sh_quote($candidate) . ' 2>/dev/null');
        if (is_string($output) && trim($output) !== '') {
            return trim($output);
        }
    }
    return null;
}

function run_unix_build(string $workdir, string $command): void
{
    $cmd = 'cd ' . sh_quote($workdir) . ' && ' . $command;
    passthru('bash -lc ' . sh_quote($cmd), $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Build failed with exit code {$exitCode}.");
    }
}

function start_unix_detached(string $workdir, string $command, string $stdoutLog, string $stderrLog): int
{
    $cmd = sprintf(
        'cd %s && nohup %s >> %s 2>> %s < /dev/null & echo $!',
        sh_quote($workdir),
        $command,
        sh_quote($stdoutLog),
        sh_quote($stderrLog)
    );
    $output = shell_exec('bash -lc ' . sh_quote($cmd));
    $pid = (int) trim((string) $output);
    if ($pid <= 0) {
        throw new RuntimeException('Could not capture process PID.');
    }
    return $pid;
}

$python = resolve_binary(['python3', 'python']);
if ($python === null) {
    $python = 'python3';
}

$frontendBuilds = [
    [
        'name' => 'forum-static',
        'workdir' => $root . '/forum-project',
        'command' => $npm . ' install && ' . $npm . ' run build',
    ],
    [
        'name' => 'admin-static',
        'workdir' => $root . '/admin_page',
        'command' => $npm . ' install && ' . $npm . ' run build',
    ],
    [
        'name' => 'message-center-static',
        'workdir' => $root . '/message-center-project',
        'command' => $npm . ' install && ' . $npm . ' run build',
    ],
];

$services = [
    [
        'name' => 'main',
        'host' => '127.0.0.1',
        'port' => 8001,
        'workdir' => $root,
        'command' => sh_quote($php) . ' -S 127.0.0.1:8001 -t .',
    ],
    [
        'name' => 'vocab',
        'host' => '127.0.0.1',
        'port' => 8002,
        'workdir' => $root . '/vocba_prac',
        'command' => sh_quote($php) . ' -S 127.0.0.1:8002 -t .',
    ],
    [
        'name' => 'forum',
        'host' => '127.0.0.1',
        'port' => 5173,
        'workdir' => $root . '/forum-project',
        'command' => $npm . ' run dev -- --host 127.0.0.1 --port 5173',
    ],
    [
        'name' => 'admin',
        'host' => '127.0.0.1',
        'port' => 5174,
        'workdir' => $root . '/admin_page',
        'command' => $npm . ' run dev -- --host 127.0.0.1 --port 5174',
    ],
    [
        'name' => 'realtime',
        'host' => '127.0.0.1',
        'port' => 3001,
        'workdir' => $root . '/voice-room-server',
        'command' => $npm . ' start',
    ],
    [
        'name' => 'scrabble_match',
        'host' => '127.0.0.1',
        'port' => 9000,
        'workdir' => $root . '/Studio/Scrabble/match-server',
        'command' => $npm . ' run start',
    ],
    [
        'name' => 'godot_ui',
        'host' => '127.0.0.1',
        'port' => 5500,
        'workdir' => $root . '/gameUI_src/Release',
        'command' => sh_quote($python) . ' serve.py',
    ],
];

echo "=== Start Services (macOS) ===\n\n";

echo "=== Build Static Frontends ===\n";
foreach ($frontendBuilds as $build) {
    try {
        echo "[build] {$build['name']}\n";
        run_unix_build($build['workdir'], $build['command']);
        echo "[done] {$build['name']}\n";
    } catch (Throwable $e) {
        echo "[failed] {$build['name']}: {$e->getMessage()}\n";
    }
}
echo "\n";

foreach ($services as $service) {
    $name = $service['name'];
    $port = $service['port'];
    $host = $service['host'];
    $stdoutLog = "{$runDir}/{$name}_{$port}.out.log";
    $stderrLog = "{$runDir}/{$name}_{$port}.err.log";
    $pidFile = "{$runDir}/{$name}_{$port}.pid";

    try {
        $pid = start_unix_detached($service['workdir'], $service['command'], $stdoutLog, $stderrLog);
        file_put_contents($pidFile, (string) $pid);
        echo "[started] {$name} http://{$host}:{$port} (PID {$pid})\n";
    } catch (Throwable $e) {
        echo "[failed] {$name}: {$e->getMessage()}\n";
    }
}

echo "\nLogs are in .run\n";
echo "Start command: php start_all_mac.php\n";
echo "Auto-detect command: php start_all.php\n";
echo "\nHome: http://127.0.0.1:8001/home.html\n";
