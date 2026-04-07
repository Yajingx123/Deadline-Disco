<?php
declare(strict_types=1);

$checks = [
    'home' => 'http://127.0.0.1:8001/home.html',
    'forum-classic' => 'http://127.0.0.1:8001/forum-project/dist/index.html?view=forum',
    'admin-static' => 'http://127.0.0.1:8001/admin_page/dist/index.html',
    'message-center' => 'http://127.0.0.1:8001/message-center-project/dist/index.html',
    'godot-ui' => 'http://127.0.0.1:5500/index.html',
    'realtime-health' => 'http://127.0.0.1:3001/health',
];

function httpGetStatus(string $url): int {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 3,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $headers = $http_response_header ?? [];
    if (!is_array($headers) || $headers === []) {
        return 0;
    }
    if (preg_match('#\s(\d{3})\s#', $headers[0], $m)) {
        return (int)$m[1];
    }
    return $body === false ? 0 : 200;
}

$failed = false;
echo "=== Smoke Check ===\n";
foreach ($checks as $name => $url) {
    $status = httpGetStatus($url);
    $ok = $status >= 200 && $status < 400;
    echo sprintf("[%s] %-14s %s (HTTP %d)\n", $ok ? 'PASS' : 'FAIL', $name, $url, $status);
    if (!$ok) {
        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

echo "[PASS] All smoke checks passed.\n";
