@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

echo === Start Services (Windows) ===
echo.

:: 自动创建日志文件夹（不使用 .run，全新目录）
if not exist "service_logs" mkdir "service_logs"

:: ====================== 1. main (PHP 8001) ======================
set NAME=main
set PORT=8001
set WORKDIR=%cd%
set LOG_OUT=service_logs\%NAME%_%PORT%.out.log
set LOG_ERR=service_logs\%NAME%_%PORT%.err.log

start /min "main" php -S 127.0.0.1:8001 -t "%WORKDIR%" > "%LOG_OUT%" 2> "%LOG_ERR%"
echo [started] main http://127.0.0.1:8001

:: ====================== 2. vocab (PHP 8002) ======================
set NAME=vocab
set PORT=8002
set WORKDIR=%cd%\vocba_prac
set LOG_OUT=service_logs\%NAME%_%PORT%.out.log
set LOG_ERR=service_logs\%NAME%_%PORT%.err.log

start /min "vocab" php -S 127.0.0.1:8002 -t "%WORKDIR%" > "%LOG_OUT%" 2> "%LOG_ERR%"
echo [started] vocab http://127.0.0.1:8002

:: ====================== 3. forum (npm run dev 5173) ======================
set NAME=forum
set PORT=5173
set WORKDIR=%cd%\forum-project
set LOG_OUT=%cd%\service_logs\%NAME%_%PORT%.out.log
set LOG_ERR=%cd%\service_logs\%NAME%_%PORT%.err.log

start /min "forum" cmd /c "cd /d "%WORKDIR%" && npm run dev -- --host 127.0.0.1 --port 5173 > "%LOG_OUT%" 2> "%LOG_ERR%""
echo [started] forum http://127.0.0.1:5173

:: ====================== 4. admin (npm run dev 5174) ======================
set NAME=admin
set PORT=5174
set WORKDIR=%cd%\admin_page
set LOG_OUT=%cd%\service_logs\%NAME%_%PORT%.out.log
set LOG_ERR=%cd%\service_logs\%NAME%_%PORT%.err.log

start /min "admin" cmd /c "cd /d "%WORKDIR%" && npm run dev -- --host 127.0.0.1 --port 5174 > "%LOG_OUT%" 2> "%LOG_ERR%""
echo [started] admin http://127.0.0.1:5174

:: ====================== 5. godot homepage (python 5500) ======================
set NAME=godot_ui
set PORT=5500
set WORKDIR=%cd%\gameUI_src\Release
set LOG_OUT=%cd%\service_logs\%NAME%_%PORT%.out.log
set LOG_ERR=%cd%\service_logs\%NAME%_%PORT%.err.log

start /min "godot_ui" cmd /c "cd /d "%WORKDIR%" && python serve.py > "%LOG_OUT%" 2> "%LOG_ERR%""
echo [started] godot_ui http://127.0.0.1:5500

echo.
echo Logs are in service_logs
echo All services started successfully!
echo.
echo === Entry URLs (open in browser) ===
echo   Main homepage (default^):     http://127.0.0.1:8001/home.html
echo   (or via index redirect^)      http://127.0.0.1:8001/
echo   Godot animated UI:           http://127.0.0.1:5500/index.html
echo   Tip: start from Main; use "Animated Home" in nav or Godot switch to swap.
echo.
pause
