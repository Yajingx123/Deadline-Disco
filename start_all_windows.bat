@echo off
chcp 65001 >nul
setlocal

echo === Start Services (Windows Wrapper) ===
echo Delegating to canonical entry: php start_all.php
echo.
php start_all.php
echo.
pause
