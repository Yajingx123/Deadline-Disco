# PHP + 数据存储说明（团队规定：PHP）

## 当前与未来

- **当前**：Vocabulary 模块大部分仍是静态 `.html` + CSS + JS，本地用 `localStorage` 存词书选择等，方便先做 UI 和流程。
- **未来**：整站将使用 **PHP + HTML**，数据存服务器（数据库），团队规定语言为 PHP。

## 已做的 PHP 基础

为方便后续迁移和存数据，已加上一套最小 PHP 结构，可与现有静态页并行使用：

| 文件 | 作用 |
|------|------|
| `config.php` | 公共配置：`$baseHref`、`$siteName`，预留数据库常量 |
| `includes/header.php` | 公共头部：`<head>`、顶栏导航，需在每页定义 `$pageTitle`、`$activeNav` |
| `includes/footer.php` | 公共尾部：闭合 `</main></div></body></html>` |
| `profile.php` | 示例页：用 `require` 引入 config、header、footer，中间是页面主体 |

其他页面（wordbank、practice、progress、friends 等）要改成 PHP 时，按 `profile.php` 的方式：

1. 顶部：`require_once config.php`，设置 `$pageTitle`、`$activeNav`，再 `require_once includes/header.php`。
2. 中间：原有 HTML 主体。
3. 底部：`require_once includes/footer.php`。

导航里的链接已指向 `.php`（如 `wordbank.php`），等对应页面改成 PHP 后即可统一用 PHP 入口。

## 数据存储的两种常见做法

1. **PHP 直接读库、渲染 HTML**  
   在 PHP 里查数据库，把结果放进变量，在 HTML 里用 `<?php echo ... ?>` 输出。适合：词书列表、用户信息、进度等「整页一起算」的内容。

2. **PHP 只提供接口，前端用 JS 请求**  
   PHP 里写接口（如 `api/wordbooks.php`）返回 JSON；页面里的 JS 用 `fetch()` 请求这些接口，再更新 DOM 或和现有 `localStorage` 逻辑配合。适合：练习提交、实时保存进度、无刷新更新等。

两种可以混用：例如列表用 PHP 渲染，练习提交/保存用 API + JS。

## 运行方式

- 本地需有 **PHP 环境**（如 XAMPP、PHP 内置服务器、或本机已装 Apache/Nginx+PHP）。
- 在项目根目录执行：`php -S localhost:8080`，浏览器访问 `http://localhost:8080/profile.php` 即可查看示例 PHP 页。
- 部署到服务器时，把整站放到支持 PHP 的目录即可，`config.php` 里可把 `$baseHref` 改为实际子路径（若有）。

之后要「存数据」时，只要在 `config.php` 里配好数据库常量，在需要的页面或接口里用 PDO/MySQLi 读写即可，现有 HTML/CSS/JS 可逐步从静态页迁到 PHP 页而不必大改结构。
