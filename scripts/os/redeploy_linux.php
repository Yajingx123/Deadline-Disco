<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$runDir = $root . '/.run';
if (!is_dir($runDir)) {
    mkdir($runDir, 0777, true);
}

$php = PHP_BINARY ?: 'php';
$npm = 'npm';
$argv = $_SERVER['argv'] ?? [];
$profile = getenv('ACADBEAT_START_PROFILE') ?: (in_array('--full', $argv, true) ? 'full' : 'simple');

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



function is_port_open(string $host, int $port, float $timeoutSeconds = 0.8): bool
{
    $errno = 0;
    $errstr = '';
    $conn = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);
    if (is_resource($conn)) {
        fclose($conn);
        return true;
    }
    return false;
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
    $pid = pcntl_fork();
    if ($pid === -1) {
        throw new RuntimeException('Could not fork process.');
    }

    if ($pid > 0) {
        return $pid;
    }

    if (posix_setsid() === -1) {
        file_put_contents($stderrLog, "[launcher] posix_setsid failed\n", FILE_APPEND);
        exit(1);
    }

    if (!@chdir($workdir)) {
        file_put_contents($stderrLog, "[launcher] chdir failed: {$workdir}\n", FILE_APPEND);
        exit(1);
    }

    @fclose(STDIN);
    @fclose(STDOUT);
    @fclose(STDERR);
    $stdin = fopen('/dev/null', 'r');
    $stdout = fopen($stdoutLog, 'ab');
    $stderr = fopen($stderrLog, 'ab');
    if ($stdin === false || $stdout === false || $stderr === false) {
        file_put_contents($stderrLog, "[launcher] failed to open stdio log files\n", FILE_APPEND);
        exit(1);
    }

    pcntl_exec('/bin/bash', ['-lc', $command]);
    file_put_contents($stderrLog, "[launcher] pcntl_exec failed for command: {$command}\n", FILE_APPEND);
    exit(1);
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
        'name' => 'forum-v2-static',
        'workdir' => $root . '/GameUI/forum-project-GameUI',
        'command' => $npm . ' install && ' . $npm . ' run build',
    ],
    [
        'name' => 'message-center-static',
        'workdir' => $root . '/message-center-project',
        'command' => $npm . ' install && ' . $npm . ' run build',
    ],
    [
        'name' => 'message-center-v2-static',
        'workdir' => $root . '/GameUI/message-center-project-GameUI',
        'command' => $npm . ' install && ' . $npm . ' run build',
    ],
    [
        'name' => 'dnd-static',
        'workdir' => $root . '/Studio/Dungeons-and-Dragons',
        'command' => $npm . ' install && ' . $npm . ' run build',
    ],
];

$serviceSetupSteps = [
    [
        'name' => 'realtime-deps',
        'workdir' => $root . '/voice-room-server',
        'command' => $npm . ' install',
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
        'name' => 'realtime',
        'host' => '127.0.0.1',
        'port' => 3001,
        'workdir' => $root . '/voice-room-server',
        'command' => $npm . ' start',
    ],
    [
        'name' => 'godot_ui',
        'host' => '127.0.0.1',
        'port' => 5500,
        'workdir' => $root . '/gameUI_src/Release',
        'command' => sh_quote($python) . ' serve.py',
    ],
];

if ($profile === 'full') {
    $serviceSetupSteps[] = [
        'name' => 'scrabble-match-deps',
        'workdir' => $root . '/Studio/Scrabble/match-server',
        'command' => $npm . ' install',
    ];
    $services[] = [
        'name' => 'forum_dev',
        'host' => '127.0.0.1',
        'port' => 5173,
        'workdir' => $root . '/forum-project',
        'command' => $npm . ' run dev -- --host 127.0.0.1 --port 5173',
    ];
    $services[] = [
        'name' => 'admin_dev',
        'host' => '127.0.0.1',
        'port' => 5174,
        'workdir' => $root . '/admin_page',
        'command' => $npm . ' run dev -- --host 127.0.0.1 --port 5174',
    ];
    $services[] = [
        'name' => 'scrabble_match',
        'host' => '127.0.0.1',
        'port' => 9000,
        'workdir' => $root . '/Studio/Scrabble/match-server',
        'command' => $npm . ' run start',
    ];
}

echo "=== Start Services (Linux / {$profile}) ===\n\n";

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

echo "=== Install Service Dependencies ===\n";
foreach ($serviceSetupSteps as $step) {
    try {
        echo "[setup] {$step['name']}\n";
        run_unix_build($step['workdir'], $step['command']);
        echo "[done] {$step['name']}\n";
    } catch (Throwable $e) {
        echo "[failed] {$step['name']}: {$e->getMessage()}\n";
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



echo "
=== Service Health Check ===
";
$healthFailures = [];
foreach ($services as $service) {
    $name = $service['name'];
    $host = $service['host'];
    $port = (int)$service['port'];
    $ok = false;
    for ($i = 0; $i < 10; $i++) {
        if (is_port_open($host, $port, 0.6)) {
            $ok = true;
            break;
        }
        usleep(300000);
    }
    if ($ok) {
        echo "[ok] {$name} http://{$host}:{$port}
";
    } else {
        $healthFailures[] = "{$name}({$host}:{$port})";
        echo "[not-listening] {$name} http://{$host}:{$port}
";
        echo "  check logs: .run/{$name}_{$port}.out.log and .run/{$name}_{$port}.err.log
";
    }
}

echo "\nLogs are in .run\n";
echo "Start command: php redeploy.php\n";
echo "Full mode command: php redeploy.php --full\n";
echo "\nHome: http://127.0.0.1:8001/home.html\n";
echo "Forum isolation: classic -> /forum-project/dist/, GameUI -> /GameUI/forum-project-GameUI/dist/\n";
if (!empty($healthFailures)) {
    fwrite(STDERR, "\n[error] Service health check failed: " . implode(', ', $healthFailures) . "\n");
    exit(2);
}
