<?php
/**
 * Vocabulary module – shared config (PHP 团队规定)
 * 未来可在此增加：数据库连接、Session、站点名等
 */
// 当前模块在站点中的基础路径（若部署在子目录可改为 '/vocab/'）
// 用于处理当页面被访问为 /backend/*.php 时，静态资源（frontend）需要回退一级目录。
$baseHref = '';
if (!empty($_SERVER['REQUEST_URI'] ?? '')) {
  $uri = (string)$_SERVER['REQUEST_URI'];
  if (strpos($uri, '/backend/') !== false) {
    $baseHref = '../';
  }
}

// 站点/模块名（用于 title、导航等）
$siteName = 'Vocabulary Practice';

// 数据库配置（MySQL Workbench 连接到的 MySQL Server）
// 你已确认 schema 名：vocab_dd
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'vocab_dd');
define('DB_USER', 'root');

// 为避免把密码写进仓库：优先从环境变量读取；本地没有就填这里
// Windows PowerShell 临时设置示例：$env:VOCAB_DB_PASS="your_password"
define('DB_PASS', getenv('VOCAB_DB_PASS') ?: '123456');

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  return $pdo;
}
