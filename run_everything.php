<?php
declare(strict_types=1);

$root = __DIR__;
$config = require $root . '/Auth/backend/config/config.php';

$tableSql = $root . '/101_acadbeat_all_tables.sql';
$dataSql = $root . '/102_acadbeat_all_data.sql';

if (!is_file($tableSql) || !is_file($dataSql)) {
    fwrite(STDERR, "Missing SQL files. Expected 101_acadbeat_all_tables.sql and 102_acadbeat_all_data.sql\n");
    exit(1);
}

$mysqlHost = (string)($config['db_host'] ?? '127.0.0.1');
$mysqlPort = (string)($config['db_port'] ?? '3306');
$mysqlUser = (string)($config['db_user'] ?? 'root');
$mysqlPass = (string)($config['db_pass'] ?? '');

function run_or_fail(string $command, string $label): void {
    echo "[run] {$label}\n";
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "[failed] {$label}\n");
        exit($exitCode ?: 1);
    }
    echo "[done] {$label}\n\n";
}

$mysqlBase = sprintf(
    'mysql -h %s -P %s -u %s %s',
    escapeshellarg($mysqlHost),
    escapeshellarg($mysqlPort),
    escapeshellarg($mysqlUser),
    $mysqlPass !== '' ? '-p' . escapeshellarg($mysqlPass) : ''
);

echo "=== AcadBeat Full Bootstrap ===\n\n";

run_or_fail(
    sprintf('%s < %s', $mysqlBase, escapeshellarg($tableSql)),
    'Import tables'
);

run_or_fail(
    sprintf('%s < %s', $mysqlBase, escapeshellarg($dataSql)),
    'Import seed data'
);

run_or_fail(
    escapeshellarg(PHP_BINARY ?: 'php') . ' ' . escapeshellarg($root . '/stop_all.php'),
    'Stop old services'
);

run_or_fail(
    escapeshellarg(PHP_BINARY ?: 'php') . ' ' . escapeshellarg($root . '/start_all.php'),
    'Start all services'
);

echo "=== Ready ===\n";
echo "Home: http://127.0.0.1:8001/home.html\n";
echo "Forum: http://127.0.0.1:5173/?view=forum\n";
echo "Message Center: http://127.0.0.1:5173/?view=messages\n";
echo "Admin: http://127.0.0.1:5174/\n";
echo "Realtime health: http://127.0.0.1:3001/health\n";
