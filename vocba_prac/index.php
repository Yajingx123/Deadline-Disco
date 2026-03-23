<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
vocab_require_auth();

// Vocabulary 模块入口：统一走 PHP 数据版页面
header('Location: ./practice.php', true, 302);
exit;
