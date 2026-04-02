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

$appId = (int) (video_env('ZEGO_APP_ID', video_env('ZEGO_APPID', '0')) ?? '0');
$serverSecret = (string) (video_env('ZEGO_SERVER_SECRET', '') ?? '');
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
    'token_endpoint' => (string) (video_env('ZEGO_TOKEN_ENDPOINT', './api/video/zego-token.php') ?? './api/video/zego-token.php'),
    'room_prefix' => $roomPrefix,
    'project_name' => (string) (video_env('ZEGO_PROJECT_NAME', 'AcadBeat Video Match') ?? 'AcadBeat Video Match'),
    'branding_logo_url' => (string) (video_env('ZEGO_BRANDING_LOGO_URL', '') ?? ''),
];
