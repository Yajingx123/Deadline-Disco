<?php
declare(strict_types=1);

$root = __DIR__;
function env_guess(string $key, string $fallback): string
{
    $value = getenv($key);
    if ($value === false) {
        return $fallback;
    }
    $trimmed = trim((string) $value);
    return $trimmed === '' ? $fallback : $trimmed;
}

function upsert_env_line(string $content, string $key, string $value): string
{
    $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
    $line = $key . '=' . $value;
    if (preg_match($pattern, $content) === 1) {
        return (string) preg_replace($pattern, $line, $content, 1);
    }
    return rtrim($content) . PHP_EOL . $line . PHP_EOL;
}

function ensure_local_env_file(string $root): void
{
    $envPath = $root . '/.env';
    if (is_file($envPath)) {
        return;
    }

    $templatePath = $root . '/.env.example';
    $content = is_file($templatePath)
        ? (string) file_get_contents($templatePath)
        : "APP_ENV=local\nAPP_URL=http://127.0.0.1:8001\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_USER=root\nDB_PASS=123456\nDB_NAME=acadbeat\n";

    $suggested = [
        'APP_ENV' => 'local',
        'APP_URL' => env_guess('APP_URL', 'http://127.0.0.1:8001'),
        'DB_HOST' => env_guess('DB_HOST', '127.0.0.1'),
        'DB_PORT' => env_guess('DB_PORT', '3306'),
        'DB_USER' => env_guess('DB_USER', 'root'),
        'DB_PASS' => env_guess('DB_PASS', '123456'),
        'DB_NAME' => env_guess('DB_NAME', 'acadbeat'),
        'ALLOWED_ORIGINS' => env_guess('ALLOWED_ORIGINS', 'http://127.0.0.1:5173,http://127.0.0.1:5174,http://127.0.0.1:5500,http://127.0.0.1:8001'),
        'REALTIME_HOST' => env_guess('REALTIME_HOST', '127.0.0.1'),
        'REALTIME_PORT' => env_guess('REALTIME_PORT', '3001'),
        'REALTIME_ALLOWED_ORIGINS' => env_guess('REALTIME_ALLOWED_ORIGINS', 'http://127.0.0.1:5173,http://127.0.0.1:5174,http://127.0.0.1:5500,http://127.0.0.1:8001'),
        'REALTIME_CORS_ALLOW_ALL' => env_guess('REALTIME_CORS_ALLOW_ALL', '1'),
        'ZEGO_APP_ID' => env_guess('ZEGO_APP_ID', '189218924'),
        'ZEGO_SERVER_SECRET' => env_guess('ZEGO_SERVER_SECRET', '8baf8a622f61de63e0997e0fae76c935'),
        'ZEGO_TOKEN_MODE' => env_guess('ZEGO_TOKEN_MODE', 'production'),
        'ZEGO_ROOM_PREFIX' => env_guess('ZEGO_ROOM_PREFIX', 'acadbeat_match_'),
    ];

    foreach ($suggested as $key => $value) {
        $content = upsert_env_line($content, $key, $value);
    }

    if (file_put_contents($envPath, $content) === false) {
        fwrite(STDERR, "[warn] Failed to auto-create .env\n");
        return;
    }

    echo "[info] .env not found. Generated with local defaults (override by editing .env)\n";
}

ensure_local_env_file($root);
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
