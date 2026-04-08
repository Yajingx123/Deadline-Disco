<?php
declare(strict_types=1);

$root = __DIR__;
$runDir = $root . '/.run';
if (!is_dir($runDir)) {
    mkdir($runDir, 0777, true);
}

$php = PHP_BINARY ?: 'php';
$npm = 'npm.cmd';
$profile = getenv('ACADBEAT_START_PROFILE') ?: 'simple';

function is_port_open(string $host, int $port, float $timeoutSeconds = 0.8): bool
{
    $errno = 0;
    $errstr = '';
    $conn = @fsockopen($host, (string)$port, $errno, $errstr, $timeoutSeconds);
    if (is_resource($conn)) {
        fclose($conn);
        return true;
    }
    return false;
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
        'workdir' => $root . '/forum-project-v2',
        'command' => $npm . ' install && ' . $npm . ' run build',
    ],
    [
        'name' => 'message-center-static',
        'workdir' => $root . '/message-center-project',
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
        'command' => '"' . $php . '" -S 127.0.0.1:8001 -t .',
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
        'command' => 'python serve.py',
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

function startWindowsDetached(string $title, string $workdir, string $command, string $stdoutLog, string $stderrLog): void {
    global $runDir;
    $npmCache = $runDir . '\\npm-cache';
    $cmd = 'start "' . $title . '" /min cmd /c "cd /d "' . $workdir . '" && set "npm_config_cache=' . $npmCache . '" && ' . $command . ' > "' . $stdoutLog . '" 2> "' . $stderrLog . '"';
    pclose(popen($cmd, 'r'));
}

function runWindowsBuild(string $workdir, string $command): void {
    global $runDir;
    $npmCache = $runDir . '\\npm-cache';
    $fullCommand = 'cmd /c "cd /d "' . $workdir . '" && set "npm_config_cache=' . $npmCache . '" && ' . $command . '"';
    passthru($fullCommand, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Build failed with exit code {$exitCode}.");
    }
}

echo "=== Start Services (Windows / {$profile}) ===\n\n";

echo "=== Build Static Frontends ===\n";
foreach ($frontendBuilds as $build) {
    try {
        echo "[build] {$build['name']}\n";
        runWindowsBuild($build['workdir'], $build['command']);
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
        runWindowsBuild($step['workdir'], $step['command']);
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

    try {
        startWindowsDetached($name, $service['workdir'], $service['command'], $stdoutLog, $stderrLog);
        echo "[started] {$name} http://{$host}:{$port}\n";
    } catch (Throwable $e) {
        echo "[failed] {$name}: {$e->getMessage()}\n";
    }
}



echo "
=== Service Health Check ===
";
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
        echo "[not-listening] {$name} http://{$host}:{$port}
";
        echo "  check logs: .run/{$name}_{$port}.out.log and .run/{$name}_{$port}.err.log
";
    }
}

echo "\nLogs are in .run\n";
echo "Start command: php start_all_windows.php\n";
echo "Auto-detect command: php start_all.php\n";
echo "Full mode command: php start_all.php --full\n";
echo "\n=== 浏览器入口（唯一推荐）===\n";
echo "  http://127.0.0.1:8001/home.html\n";
echo "  （或 http://127.0.0.1:8001/ 会跳转到主页）\n";
echo "  在主页登录后，使用右上角 Switch 进入 Godot；不要单独把 5500 当主入口。\n";
echo "\n论坛隔离：经典 UI -> /forum-project/dist/；新 UI 壳 -> /forum-project-v2/dist/\n";
echo "新 UI 论坛壳地址：http://127.0.0.1:8001/newUI/shell/forum/shell.html\n";
if ($profile === 'full') {
    echo "Full 模式包含：5173 forum-dev、5174 admin-dev、9000 scrabble-match\n";
}
