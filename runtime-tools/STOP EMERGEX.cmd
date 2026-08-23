@echo off
setlocal
title Stop EMERGEX
set "WINDOWS_POWERSHELL=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"

"%WINDOWS_POWERSHELL%" -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0STOP_EMERGEX.ps1"
if errorlevel 1 (
    echo.
    echo EMERGEX could not be stopped. Please take a screenshot of this window.
    pause
    exit /b 1
)

echo.
echo It is now safe to close this window.
ping 127.0.0.1 -n 4 >nul
endlocal
