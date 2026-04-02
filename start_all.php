<?php
declare(strict_types=1);

/**
 * 根据系统自动调用 start_all_windows.php 或 start_all_mac.php，拉起主站 / 论坛 / Godot 等本地服务。
 *
 * 日常请只从「主页」进入浏览器：http://127.0.0.1:8001/home.html
 * 在主页登录后使用 Switch 再进入 Godot（5500）；不要直接把 5500 当主入口。
 */

echo ">>> 浏览器入口（仅此）：http://127.0.0.1:8001/home.html  →  登录后点 Switch 进入 Godot\n\n";

$family = PHP_OS_FAMILY;
$script = match ($family) {
    'Windows' => __DIR__ . '/start_all_windows.php',
    'Darwin' => __DIR__ . '/start_all_mac.php',
    default => null,
};

if ($script === null || !is_file($script)) {
    fwrite(STDERR, "Unsupported OS: {$family}\n");
    fwrite(STDERR, "Use start_all_windows.php or start_all_mac.php directly.\n");
    exit(1);
}

require $script;
