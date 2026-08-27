@echo off
title Stop OLX Scraper Services
color 0C

echo ========================================================
echo   Stopping OLX Real-Time Data Scraper Stack
echo ========================================================
echo.

echo [1/2] Terminating Python Extractor on Port 8000...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":8000" ^| findstr "LISTENING"') do (
    taskkill /F /PID %%a >nul 2>&1
    echo   - Stopped process on port 8000 (PID: %%a)
)
taskkill /F /IM uvicorn.exe >nul 2>&1

echo.
echo [2/2] Terminating CodeIgniter API on Port 8085...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":8085" ^| findstr "LISTENING"') do (
    taskkill /F /PID %%a >nul 2>&1
    echo   - Stopped process on port 8085 (PID: %%a)
)

echo.
echo ========================================================
echo   All scraper microservices have been stopped!
echo ========================================================
echo.
timeout /t 3 >nul
