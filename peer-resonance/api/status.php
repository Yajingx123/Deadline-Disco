<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = peer_require_user();
$pdo = peer_db();

peer_json([
    'ok' => true,
    'state' => peer_build_state($pdo, (int)$user['user_id']),
]);
