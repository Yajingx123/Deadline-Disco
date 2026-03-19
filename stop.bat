@echo off
chcp 65001 > nul
echo ===================================================
echo   🛑 正在停止所有 Deadline Disco 后台服务...
echo ===================================================

:: 强制结束所有 PHP 测试服务器进程
taskkill /F /IM php.exe >nul 2>&1
echo ✅ 已清理 PHP 后端服务

:: 强制结束 Node 进程 (Vite 前端)
taskkill /F /IM node.exe >nul 2>&1
echo ✅ 已清理 Node 前端服务

echo.
echo 🎉 所有端口均已释放！可以安全关闭窗口。
pause