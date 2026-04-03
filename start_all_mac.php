<?php
declare(strict_types=1);

$root = __DIR__;
$runDir = $root . '/.run';
if (!is_dir($runDir)) {
    mkdir($runDir, 0777, true);
}

$php = PHP_BINARY ?: 'php';
$npm = 'npm';

$frontendBuilds = [
    [
        'name' => 'forum-static',
        'workdir' => $root . '/forum-project',
        'command' => escapeshellarg($npm) . ' run build',
    ],
    [
        'name' => 'admin-static',
        'workdir' => $root . '/admin_page',
        'command' => escapeshellarg($npm) . ' run build',
    ],
    [
        'name' => 'message-center-static',
        'workdir' => $root . '/message-center-project',
        'command' => escapeshellarg($npm) . ' run build',
    ],
];

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
    [
        'name' => 'admin',
        'host' => '127.0.0.1',
        'port' => 5174,
        'workdir' => $root . '/admin_page',
        'command' => escapeshellarg($npm) . ' run dev -- --host 127.0.0.1 --port 5174',
    ],
    [
        'name' => 'realtime',
        'host' => '127.0.0.1',
        'port' => 3001,
        'workdir' => $root . '/voice-room-server',
        'command' => escapeshellarg($npm) . ' start',
    ],
    [
        'name' => 'godot_ui',
        'host' => '127.0.0.1',
        'port' => 5500,
        'workdir' => $root . '/gameUI_src/Release',
        'command' => 'python3 serve.py',
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

function runForegroundBuild(string $command, string $workdir): void {
    $shellCommand = sprintf('cd %s && %s 2>&1', escapeshellarg($workdir), $command);
    passthru($shellCommand, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Build failed with exit code {$exitCode}.");
    }
}

echo "=== Start Services (macOS) ===\n\n";

echo "=== Build Static Frontends ===\n";
foreach ($frontendBuilds as $build) {
    try {
        echo "[build] {$build['name']}\n";
        runForegroundBuild($build['command'], $build['workdir']);
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

echo "\n=== 浏览器入口（唯一推荐）===\n";
echo "  http://127.0.0.1:8001/home.html\n";
echo "  （或 http://127.0.0.1:8001/ 会跳转到主页）\n";
echo "  在主页登录后，使用右上角 Switch 进入 Godot；不要单独把 5500 当主入口。\n";
echo "\n（后台已启动：8001 主站、8002 词表、5173 Vite 论坛、5174 管理、3001 语音房、5500 Godot 静态导出）\n";
echo "  论坛开发地址：http://127.0.0.1:5173/forum-project/dist/\n";
