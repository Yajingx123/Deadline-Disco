<?php
declare(strict_types=1);

/**
 * Central runtime config with lightweight .env loading.
 * Priority: process env > .env.<APP_ENV> > .env > hardcoded defaults.
 */

if (!function_exists('config_put_env')) {
    function config_put_env(string $key, string $value): void
    {
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('config_load_env_file')) {
    function config_load_env_file(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $parts = explode('=', $trimmed, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            config_put_env($key, $value);
        }
    }
}

if (!function_exists('config_env')) {
    function config_env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        $value = trim((string) $value);
        return $value === '' ? $default : $value;
    }
}

if (!function_exists('config_env_list')) {
    function config_env_list(string $key, array $default): array
    {
        $raw = config_env($key, '');
        if ($raw === null || $raw === '') {
            return $default;
        }

        $items = array_map('trim', explode(',', $raw));
        $items = array_values(array_filter($items, static fn($v) => $v !== ''));
        return $items ?: $default;
    }
}

$root = dirname(__DIR__, 3);

// Load .env first, then .env.<APP_ENV> for overrides.
config_load_env_file($root . '/.env');
$appEnv = strtolower((string) (config_env('APP_ENV', 'local') ?? 'local'));
config_load_env_file($root . '/.env.' . $appEnv);

$defaultOrigins = [
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5174',
    'http://127.0.0.1:5500',
    'http://127.0.0.1:8001',
];

$allowedOrigins = config_env_list('ALLOWED_ORIGINS', $defaultOrigins);

return [
    'app_env' => $appEnv,
    'app_url' => (string) (config_env('APP_URL', 'http://127.0.0.1:8001') ?? 'http://127.0.0.1:8001'),

    'db_host' => (string) (config_env('DB_HOST', '127.0.0.1') ?? '127.0.0.1'),
    'db_port' => (string) (config_env('DB_PORT', '3306') ?? '3306'),
    'db_user' => (string) (config_env('DB_USER', 'root') ?? 'root'),
    'db_pass' => (string) (config_env('DB_PASS', '123456') ?? '123456'),
    'db_name' => (string) (config_env('DB_NAME', 'acadbeat') ?? 'acadbeat'),

    'allowed_origin' => (string) ($allowedOrigins[0] ?? 'http://127.0.0.1:8001'),
    'allowed_origins' => $allowedOrigins,
];
