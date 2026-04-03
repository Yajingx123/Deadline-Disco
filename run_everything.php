<?php
declare(strict_types=1);

$root = __DIR__;
$config = require $root . '/Auth/backend/config/config.php';

$tableSql = $root . '/sql/101_acadbeat_core_tables.sql';
$dataSql = $root . '/sql/102_acadbeat_core_seed_data.sql';
$videoMatchSql = $root . '/sql/105_academic_practice_video_match_tables.sql';

if (!is_file($tableSql) || !is_file($dataSql) || !is_file($videoMatchSql)) {
    fwrite(
        STDERR,
        "Missing SQL files. Expected under ./sql/: 101_acadbeat_core_tables.sql, 102_acadbeat_core_seed_data.sql, 105_academic_practice_video_match_tables.sql\n"
    );
    exit(1);
}

$mysqlHost = (string)($config['db_host'] ?? '127.0.0.1');
$mysqlPort = (string)($config['db_port'] ?? '3306');
$mysqlUser = (string)($config['db_user'] ?? 'root');
$mysqlPass = (string)($config['db_pass'] ?? '');

function cli_quote(string $value): string {
    if (PHP_OS_FAMILY === 'Windows') {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return escapeshellarg($value);
}

function run_or_fail(string $command, string $label): void {
    echo "[run] {$label}\n";
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "[failed] {$label}\n");
        exit($exitCode ?: 1);
    }
    echo "[done] {$label}\n\n";
}

function mysql_import_command(string $host, string $port, string $user, string $pass, string $sqlFile, bool $force = false): string {
    $parts = [
        'mysql',
        '--host=' . cli_quote($host),
        '--port=' . cli_quote($port),
        '--user=' . cli_quote($user),
    ];
    if ($pass !== '') {
        $parts[] = '--password=' . cli_quote($pass);
    }
    if ($force) {
        $parts[] = '--force';
    }

    return implode(' ', $parts) . ' < ' . cli_quote($sqlFile);
}

echo "=== AcadBeat Full Bootstrap ===\n\n";
echo "[info] Using table SQL: {$tableSql}\n";
echo "[info] Using data SQL:  {$dataSql}\n\n";

run_or_fail(
    mysql_import_command($mysqlHost, $mysqlPort, $mysqlUser, $mysqlPass, $tableSql),
    'Import tables'
);

run_or_fail(
    mysql_import_command($mysqlHost, $mysqlPort, $mysqlUser, $mysqlPass, $dataSql),
    'Import seed data'
);

if (is_file($videoMatchSql)) {
    run_or_fail(
        mysql_import_command($mysqlHost, $mysqlPort, $mysqlUser, $mysqlPass, $videoMatchSql, true),
        'Import video match tables'
    );
}

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
echo "Forum: http://127.0.0.1:8001/forum-project/dist/index.html?view=forum\n";
echo "Message Center: http://127.0.0.1:8001/message-center-project/dist/index.html\n";
echo "Admin: http://127.0.0.1:5174/admin_page/dist/\n";
echo "Realtime health: http://127.0.0.1:3001/health\n";
