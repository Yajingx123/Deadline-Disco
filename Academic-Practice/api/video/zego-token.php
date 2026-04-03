<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../zego_server_assistant/src/ZEGO/ZegoAssistantToken.php';
require_once __DIR__ . '/../../../zego_server_assistant/src/ZEGO/ZegoErrorCodes.php';
require_once __DIR__ . '/../../../zego_server_assistant/src/ZEGO/ZegoServerAssistant.php';

use ZEGO\ZegoErrorCodes;
use ZEGO\ZegoServerAssistant;

function video_token_request_value(string $key, ?string $fallback = null): ?string
{
    $value = $_GET[$key] ?? $_POST[$key] ?? null;
    if (!is_string($value)) {
        return $fallback;
    }

    $value = trim($value);
    return $value === '' ? $fallback : $value;
}

function video_sanitize_identifier(string $value, int $maxLength = 128): string
{
    $value = preg_replace('/[^A-Za-z0-9_]/', '_', trim($value));
    $value = preg_replace('/_+/', '_', (string) $value);
    $value = trim((string) $value, '_');
    if ($value === '') {
        return '';
    }

    return substr($value, 0, $maxLength);
}

function video_effective_time_seconds(): int
{
    $raw = video_token_request_value('effectiveTimeInSeconds')
        ?? video_token_request_value('effective_time_in_seconds')
        ?? video_token_request_value('ttl')
        ?? '3600';

    $seconds = (int) $raw;
    if ($seconds < 60) {
        return 3600;
    }

    return min($seconds, 24 * 24 * 60 * 60);
}

function video_allowed_user_ids(array $user): array
{
    $userId = (int) ($user['user_id'] ?? 0);
    $username = (string) ($user['username'] ?? '');
    $candidates = [
        (string) $userId,
        $username,
        'acadbeat_' . $userId,
        'acadbeat_' . $username,
    ];

    $allowed = [];
    foreach ($candidates as $candidate) {
        $sanitized = video_sanitize_identifier($candidate);
        if ($sanitized !== '') {
            $allowed[] = $sanitized;
        }
    }

    return array_values(array_unique($allowed));
}

function video_resolve_user_id(array $user): string
{
    $requested = video_sanitize_identifier((string) (video_token_request_value('userID', '') ?? ''));
    $allowed = video_allowed_user_ids($user);
    $defaultUserId = (string) ($allowed[0] ?? video_sanitize_identifier('acadbeat_' . ((int) ($user['user_id'] ?? 0))));

    if ($requested === '') {
        return $defaultUserId;
    }

    if (in_array($requested, $allowed, true)) {
        return $requested;
    }

    video_json_response([
        'ok' => false,
        'message' => 'The requested ZEGO userID does not match the current login session.',
    ], 403);
}

function video_resolve_room_id(): string
{
    return video_sanitize_identifier((string) (video_token_request_value('roomID', '') ?? ''));
}

function video_build_payload(string $roomId): string
{
    if ($roomId === '') {
        return '';
    }

    return (string) json_encode([
        'room_id' => $roomId,
        'privilege' => [
            1 => 1,
            2 => 1,
        ],
        'stream_id_list' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$user = video_require_user();
$zegoConfig = require __DIR__ . '/zego-config.php';
$appId = (int) ($zegoConfig['app_id'] ?? 0);
$serverSecret = trim((string) ($zegoConfig['server_secret'] ?? ''));

if ($appId <= 0 || $serverSecret === '') {
    video_json_response([
        'ok' => false,
        'message' => 'ZEGO appID or server secret is not configured on the server.',
    ], 500);
}

$resolvedUserId = video_resolve_user_id($user);
$roomId = video_resolve_room_id();
$effectiveTimeInSeconds = video_effective_time_seconds();
$payload = video_build_payload($roomId);

try {
    $generated = ZegoServerAssistant::generateToken04(
        $appId,
        $resolvedUserId,
        $serverSecret,
        $effectiveTimeInSeconds,
        $payload
    );

    if (($generated->code ?? null) !== ZegoErrorCodes::success || !is_string($generated->token ?? null) || $generated->token === '') {
        throw new RuntimeException((string) ($generated->message ?? 'Unknown ZEGO token error.'));
    }
} catch (Throwable $e) {
    video_json_response([
        'ok' => false,
        'message' => 'Failed to generate ZEGO token: ' . $e->getMessage(),
    ], 500);
}

video_json_response([
    'ok' => true,
    'token' => $generated->token,
    'appID' => $appId,
    'userID' => $resolvedUserId,
    'roomID' => $roomId,
    'effectiveTimeInSeconds' => $effectiveTimeInSeconds,
    'createdAt' => time(),
    'expireAt' => time() + $effectiveTimeInSeconds,
]);
