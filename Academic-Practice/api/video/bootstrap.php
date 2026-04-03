<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function video_db(): PDO
{
    return listening_db();
}

function video_json_response(array $payload, int $status = 200): void
{
    listening_json($payload, $status);
}

function video_input(): array
{
    return listening_input();
}

function video_current_user(): ?array
{
    $user = $_SESSION['auth_user'] ?? null;
    return is_array($user) ? $user : null;
}

function video_require_user(): array
{
    return listening_require_user();
}

function video_now(): string
{
    return date('Y-m-d H:i:s');
}

function video_initials(string $username): string
{
    $trimmed = trim($username);
    return strtoupper(substr($trimmed !== '' ? $trimmed : 'U', 0, 2));
}

function video_format_display_name(string $username): string
{
    $trimmed = trim($username);
    if ($trimmed === '') {
        return 'User';
    }

    $parts = preg_split('/[\s._-]+/', $trimmed) ?: [];
    $parts = array_filter($parts, static fn(string $part): bool => $part !== '');
    if (!$parts) {
        return ucfirst($trimmed);
    }

    return implode(' ', array_map(static function (string $part): string {
        return strtoupper(substr($part, 0, 1)) . substr($part, 1);
    }, $parts));
}
