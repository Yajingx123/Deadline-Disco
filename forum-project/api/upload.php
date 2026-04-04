<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    forum_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

forum_require_user();

if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
    forum_json(['ok' => false, 'message' => 'No file received.'], 422);
}

$file = $_FILES['file'];
$kind = trim((string)($_POST['kind'] ?? 'image'));

if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    forum_json(['ok' => false, 'message' => 'Upload failed.'], 422);
}

$tmpName = (string)($file['tmp_name'] ?? '');
$originalName = (string)($file['name'] ?? 'upload');
$mimeType = (string)($file['type'] ?? '');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

$allowed = [
    'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    'audio' => ['mp3', 'wav', 'ogg', 'm4a', 'webm'],
    'video' => ['mp4', 'mov', 'webm', 'm4v', 'avi'],
];

if (!isset($allowed[$kind])) {
    forum_json(['ok' => false, 'message' => 'Unsupported upload type.'], 422);
}

if (!in_array($extension, $allowed[$kind], true)) {
    forum_json(['ok' => false, 'message' => 'Unsupported file format.'], 422);
}

$maxBytes = match ($kind) {
    'image' => 8 * 1024 * 1024,
    'audio' => 10 * 1024 * 1024,
    default => 25 * 1024 * 1024,
};
if ((int)($file['size'] ?? 0) > $maxBytes) {
    $message = match ($kind) {
        'image' => 'Image is too large. Keep it under 8MB.',
        'audio' => 'Audio is too large. Keep it under 10MB.',
        default => 'Video is too large. Keep it under 25MB.',
    };
    forum_json(['ok' => false, 'message' => $message], 422);
}

$uploadRoot = dirname(__DIR__) . '/uploads/' . $kind;
if (!is_dir($uploadRoot) && !@mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
    forum_json(['ok' => false, 'message' => 'Failed to prepare upload folder.'], 500);
}
if (!is_writable($uploadRoot)) {
    forum_json(['ok' => false, 'message' => 'Upload folder is not writable by PHP-FPM user.'], 500);
}

$safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME));
$safeBase = trim((string)$safeBase, '-');
if ($safeBase === '') {
    $safeBase = $kind;
}

$filename = sprintf('%s-%s.%s', $safeBase, bin2hex(random_bytes(6)), $extension);
$targetPath = $uploadRoot . '/' . $filename;

if (!@move_uploaded_file($tmpName, $targetPath)) {
    forum_json(['ok' => false, 'message' => 'Failed to save uploaded file.'], 500);
}

$publicUrl = forum_public_base_url() . '/forum-project/uploads/' . $kind . '/' . rawurlencode($filename);

forum_json([
    'ok' => true,
    'url' => $publicUrl,
    'fileName' => $originalName,
    'mimeType' => $mimeType,
]);
