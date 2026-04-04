<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$targetDir = $root . '/sql';

$pairs = [
    $root . '/database/bootstrap/101_acadbeat_all_tables.sql' => $targetDir . '/101_acadbeat_core_tables.sql',
    $root . '/database/bootstrap/102_acadbeat_all_data.sql' => $targetDir . '/102_acadbeat_core_seed_data.sql',
    $root . '/101_acadbeat_all_tables.sql' => $targetDir . '/101_acadbeat_core_tables.sql',
    $root . '/102_acadbeat_all_data.sql' => $targetDir . '/102_acadbeat_core_seed_data.sql',
    $root . '/105_academic_practice_video_match_tables.sql' => $targetDir . '/105_academic_practice_video_match_tables.sql',
    $root . '/Academic-Practice/sql/video_resources.sql' => $targetDir . '/210_academic_practice_video_resources.sql',
    $root . '/forum-project/sql/forum_announcements.sql' => $targetDir . '/220_forum_announcements.sql',
];

if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
    fwrite(STDERR, "[error] Failed to create directory: {$targetDir}\n");
    exit(1);
}

foreach ($pairs as $src => $dst) {
    if (!is_file($src)) {
        fwrite(STDERR, "[warn] Source not found, skipped: {$src}\n");
        continue;
    }

    $srcHash = hash_file('sha256', $src);
    $dstHash = is_file($dst) ? hash_file('sha256', $dst) : null;

    if ($dstHash !== null && $srcHash === $dstHash) {
        echo "[skip] Up to date: {$dst}\n";
        continue;
    }

    if (!copy($src, $dst)) {
        fwrite(STDERR, "[error] Copy failed: {$src} -> {$dst}\n");
        exit(1);
    }

    $newHash = hash_file('sha256', $dst);
    if ($newHash !== $srcHash) {
        fwrite(STDERR, "[error] Hash mismatch after copy: {$dst}\n");
        exit(1);
    }

    echo "[ok] Synced: {$src} -> {$dst}\n";
}

echo "[done] SQL sync complete. Canonical directory: ./sql\n";
