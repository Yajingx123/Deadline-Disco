<?php
declare(strict_types=1);

$root = __DIR__;
$runDir = $root . '/.run';
if (!is_dir($runDir)) {
    mkdir($runDir, 0777, true);
}

$php = PHP_BINARY ?: 'php';
$npm = 'npm.cmd';

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
        'command' => '"' . $php . '" -S 127.0.0.1:8001 -t .',
    ],
    [
        'name' => 'vocab',
        'host' => '127.0.0.1',
        'port' => 8002,
        'workdir' => $root . '/vocba_prac',
        'command' => '"' . $php . '" -S 127.0.0.1:8002 -t .',
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
        'name' => 'godot_ui',
        'host' => '127.0.0.1',
        'port' => 5500,
        'workdir' => $root . '/gameUI_src/Release',
        'command' => 'python serve.py',
    ],
];

function startWindowsDetached(string $title, string $workdir, string $command, string $stdoutLog, string $stderrLog): void {
    $cmd = 'start "' . $title . '" /min cmd /c "cd /d "' . $workdir . '" && ' . $command . ' > "' . $stdoutLog . '" 2> "' . $stderrLog . '"';
    pclose(popen($cmd, 'r'));
}

function runWindowsBuild(string $workdir, string $command): void {
    $fullCommand = 'cmd /c "cd /d "' . $workdir . '" && ' . $command . '"';
    passthru($fullCommand, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Build failed with exit code {$exitCode}.");
    }
}

echo "=== Start Services (Windows) ===\n\n";

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

echo "\nLogs are in .run\n";
echo "Start command: php start_all_windows.php\n";
echo "Auto-detect command: php start_all.php\n";
echo "\n=== 浏览器入口（唯一推荐）===\n";
echo "  http://127.0.0.1:8001/home.html\n";
echo "  （或 http://127.0.0.1:8001/ 会跳转到主页）\n";
echo "  在主页登录后，使用右上角 Switch 进入 Godot；不要单独把 5500 当主入口。\n";
echo "\n（后台已启动：8001 主站、8002 词表、5173 Vite 论坛、5174 管理、3001 实时 WebSocket、5500 Godot 静态导出）\n";
echo "  论坛开发地址：http://127.0.0.1:5173/forum-project/dist/ （须带 /forum-project/dist/ 路径）\n";
