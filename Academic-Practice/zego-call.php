<?php
declare(strict_types=1);

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = './video_call/room.php' . ($query !== '' ? ('?' . $query) : '');
header('Location: ' . $target, true, 302);
exit;
