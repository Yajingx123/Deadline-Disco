<?php
declare(strict_types=1);

function video_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    $trimmed = trim($value);
    return $trimmed === '' ? $default : $trimmed;
}

// Local safe defaults. In production, always provide ZEGO_* via env.
$appEnv = strtolower((string) (video_env('APP_ENV', 'local') ?? 'local'));
$isProduction = in_array($appEnv, ['prod', 'production'], true);
$defaultAppId = $isProduction ? '0' : '189218924';
$defaultServerSecret = $isProduction ? '' : '8baf8a622f61de63e0997e0fae76c935';

$appId = (int) (video_env('ZEGO_APP_ID', video_env('ZEGO_APPID', $defaultAppId)) ?? $defaultAppId);
$serverSecret = (string) (video_env('ZEGO_SERVER_SECRET', $defaultServerSecret) ?? $defaultServerSecret);
$legacyAppSign = (string) (video_env('ZEGO_APP_SIGN', video_env('ZEGO_APPSIGN', '')) ?? '');
$testSecret = $serverSecret !== '' ? $serverSecret : $legacyAppSign;
$tokenMode = (string) (video_env('ZEGO_TOKEN_MODE', $serverSecret !== '' ? 'production' : 'test') ?? 'test');

if ($tokenMode === 'production' && $serverSecret === '') {
    $tokenMode = 'test';
}

$roomPrefix = preg_replace('/[^A-Za-z0-9_]/', '_', (string) (video_env('ZEGO_ROOM_PREFIX', 'acadbeat_match_') ?? 'acadbeat_match_'));
$roomPrefix = is_string($roomPrefix) && $roomPrefix !== '' ? rtrim($roomPrefix, '_') . '_' : 'acadbeat_match_';

return [
    'app_id' => $appId,
    'server_secret' => $serverSecret,
    'test_secret' => $testSecret,
    'token_mode' => $tokenMode,
    'token_endpoint' => (string) (video_env('ZEGO_TOKEN_ENDPOINT', '/video-chat-project/api/zego-token.php') ?? '/video-chat-project/api/zego-token.php'),
    'room_prefix' => $roomPrefix,
    'project_name' => (string) (video_env('ZEGO_PROJECT_NAME', 'AcadBeat Video Match') ?? 'AcadBeat Video Match'),
    'branding_logo_url' => (string) (video_env('ZEGO_BRANDING_LOGO_URL', '') ?? ''),
];
