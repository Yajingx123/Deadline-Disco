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
        'name' => 'godot_ui',
        'host' => '127.0.0.1',
        'port' => 5500,
        'workdir' => $root . '/newUI/homepage/Release',
        'command' => 'python serve.py',
    ],
];

function startWindowsDetached(string $title, string $workdir, string $command, string $stdoutLog, string $stderrLog): void {
    $cmd = 'start "' . $title . '" /min cmd /c "cd /d "' . $workdir . '" && ' . $command . ' > "' . $stdoutLog . '" 2> "' . $stderrLog . '"';
    pclose(popen($cmd, 'r'));
}

echo "=== Start Services (Windows) ===\n\n";

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
echo "\n=== Entry URLs (open in browser) ===\n";
echo "  Main homepage (default):     http://127.0.0.1:8001/home.html\n";
echo "  (or via index redirect)      http://127.0.0.1:8001/\n";
echo "  Godot animated UI:           http://127.0.0.1:5500/index.html\n";
echo "  Tip: start from Main; use \"Animated Home\" in nav or Godot switch to swap.\n";
