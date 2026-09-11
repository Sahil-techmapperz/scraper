@echo off
title Multi-Platform (OLX & Cashify) Data Scraper Services
color 0A

echo ========================================================
echo   Starting Multi-Platform (OLX & Cashify) Scraper Stack
echo ========================================================
echo.

echo [1/3] Checking and Starting Redis Server (Port 6379)...
powershell -NoProfile -ExecutionPolicy Bypass -Command "if (!(Test-NetConnection -ComputerName 127.0.0.1 -Port 6379 -WarningAction SilentlyContinue).TcpTestSucceeded) { if (Test-Path 'C:\Program Files\Redis\redis-server.exe') { Start-Process 'C:\Program Files\Redis\redis-server.exe' -WorkingDirectory '%~dp0writable' -WindowStyle Minimized } elseif (Get-Command redis-server -ErrorAction SilentlyContinue) { Start-Process (Get-Command redis-server).Source -WorkingDirectory '%~dp0writable' -WindowStyle Minimized } }"

echo [2/3] Starting Python Extraction Microservice (Port 8000)...
start "Python Extractor (Port 8000)" cmd /k "cd /d %~dp0 && extractor\.venv\Scripts\uvicorn extractor.main:app --host 127.0.0.1 --port 8000 --reload"

timeout /t 3 /nobreak >nul

echo [3/3] Starting CodeIgniter 4 API Server (Port 8085)...
start "CodeIgniter 4 API (Port 8085)" cmd /k "cd /d %~dp0 && php spark serve --port 8085"

echo.
echo ========================================================
echo   All services have been launched!
echo.
echo   - Redis Cache:      127.0.0.1:6379
echo   - Web UI Dashboard: http://localhost:8085/
echo   - Swagger API Docs: http://localhost:8085/api/docs
echo   - Python Extractor: http://127.0.0.1:8000/health
echo ========================================================
echo.
pause
