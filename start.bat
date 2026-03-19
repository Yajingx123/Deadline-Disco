@echo off
chcp 65001 > nul
title Deadline Disco 全栈服务运行中...

echo ===================================================
echo   🚀 正在后台静默启动全栈开发环境...
echo   (请保持此窗口开启，不要直接点右上角关闭)
echo ===================================================

:: ===================== 新增：npm 依赖安装逻辑 =====================
echo [0/5] 📦 检查并安装前端 npm 依赖...
:: 进入前端目录
cd Listening\frontend

:: 检查 node_modules 文件夹是否存在（判断是否已装依赖）
if not exist "node_modules" (
    echo ⚠️  未检测到 node_modules，正在安装 npm 依赖...
    :: 执行 npm install，显示安装日志（如需静默可加 --silent）
    npm install
    if errorlevel 1 (
        echo ❌ npm 依赖安装失败！请检查：
        echo    1. 是否安装了 Node.js（建议v16+）
        echo    2. 网络是否正常（可尝试 npm install --registry=https://registry.npmmirror.com）
        pause
        exit /b 1
    )
    echo ✅ npm 依赖安装完成！
) else (
    echo ✅ 已检测到 node_modules，跳过 npm 安装
)

:: 回到脚本根目录
cd ../..
:: ===================== npm 安装逻辑结束 =====================

:: 1. Listening & Intensive 后端 (主 API)
echo [1/5] 📦 启动 Listening/Intensive 后端 (Port: 8000)...
start /B cmd /c "cd Listening\backend && php -S 127.0.0.1:8000 php\router.php >nul 2>&1"

:: 2. Auth 后端 (目前前端未接，但保留运行以备后用)
echo [2/5] 🔐 启动 Auth 后端 (Port: 8001)...
start /B cmd /c "cd Auth\backend && php -S 127.0.0.1:8001 >nul 2>&1"

:: 3. Vocabulary Practice 后端 (App.jsx硬性依赖此端口)
echo [3/5] 📚 启动 Vocab Practice (Port: 8002)...
start /B cmd /c "cd vocba_prac && php -S 127.0.0.1:8002 >nul 2>&1"

:: 4. Vocabulary Exam (静态测验页)
echo [4/5] 📝 启动 Vocab Exam (Port: 8003)...
start /B cmd /c "cd vocabulary-exam && php -S 127.0.0.1:8003 >nul 2>&1"

:: 5. React / Vite 主前端
echo [5/5] 🌐 启动 Vite 主前端 (Port: 5173)...
start /B cmd /c "cd Listening\frontend && set VITE_API_BASE_URL=http://127.0.0.1:8000 && npm run dev -- --host 127.0.0.1 --port 5173 >nul 2>&1"

echo.
echo ✅ 所有 5 个服务均已在后台静默运行！
echo ⏳ 正在为您打开浏览器...
timeout /t 3 > nul
start http://127.0.0.1:5173

echo ===================================================
echo 💡 【安全退出】请另写一个 stop.bat 使用 taskkill 结束进程
echo ===================================================
cmd /k