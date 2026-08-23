@echo off
setlocal
title Start EMERGEX
set "WINDOWS_POWERSHELL=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"

"%WINDOWS_POWERSHELL%" -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0START_EMERGEX.ps1"
if errorlevel 1 (
    echo.
    echo EMERGEX could not be started. Please take a screenshot of this window.
    pause
    exit /b 1
)

start "" "http://127.0.0.1:8080/emergex/public/"
echo.
echo EMERGEX is ready. Your browser should open automatically.
ping 127.0.0.1 -n 4 >nul
endlocal
