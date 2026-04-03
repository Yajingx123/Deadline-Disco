# 本地「部署」前端：生成各子项目的 dist/（PHP 源码无需复制，由 php -S 直接读仓库）
# 用法：在项目根目录执行  .\deploy-static.ps1

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot

$projects = @(
    @{ Name = 'forum-project'; Path = Join-Path $root 'forum-project' },
    @{ Name = 'admin_page'; Path = Join-Path $root 'admin_page' },
    @{ Name = 'message-center-project'; Path = Join-Path $root 'message-center-project' }
)

Write-Host "=== AcadBeat: build static frontends (dist/) ===" -ForegroundColor Cyan
Write-Host "Root: $root`n"

foreach ($p in $projects) {
    if (-not (Test-Path $p.Path)) {
        Write-Host "[skip] $($p.Name): folder not found" -ForegroundColor Yellow
        continue
    }
    Write-Host "=== $($p.Name) ===" -ForegroundColor Cyan
    Push-Location $p.Path
    try {
        if (-not (Test-Path 'package.json')) {
            throw "package.json missing"
        }
        if (-not (Test-Path 'node_modules')) {
            Write-Host "npm install ..." -ForegroundColor DarkGray
            npm install
        }
        npm run build
        Write-Host "[ok] $($p.Name)`n" -ForegroundColor Green
    } finally {
        Pop-Location
    }
}

Write-Host "Done. 浏览器访问（需已启动 php -S，见 start_all_windows.php）：" -ForegroundColor Green
Write-Host "  http://127.0.0.1:8001/home.html"
Write-Host "  论坛静态页: http://127.0.0.1:8001/forum-project/dist/index.html"
Write-Host "  管理端:     http://127.0.0.1:8001/admin_page/dist/index.html"
