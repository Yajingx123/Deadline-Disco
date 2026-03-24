<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
forum_require_user();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$input = forum_input();
$postId = (int)($input['postId'] ?? 0);
if ($postId <= 0) {
    forum_json(['ok' => false, 'message' => 'Invalid post id.'], 422);
}

$stmt = forum_db()->prepare("
    UPDATE forum_posts
    SET view_count = view_count + 1, updated_at = NOW()
    WHERE post_id = ? AND status = 'active'
");
$stmt->execute([$postId]);

forum_json(['ok' => true]);
