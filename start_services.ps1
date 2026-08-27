# PowerShell Script to Start Both Scraper Services
Write-Host "========================================================" -ForegroundColor Green
Write-Host "  Starting OLX Real-Time Data Scraper Stack" -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Green
Write-Host ""

$RootPath = $PSScriptRoot

# 1. Start Redis Server
Write-Host "[1/3] Checking Redis Server on Port 6379..." -ForegroundColor Cyan
$RedisRunning = $false
try {
    $Tcp = Test-NetConnection -ComputerName 127.0.0.1 -Port 6379 -WarningAction SilentlyContinue
    $RedisRunning = $Tcp.TcpTestSucceeded
} catch {}

if (-not $RedisRunning) {
    $RedisCmd = Get-Command redis-server -ErrorAction SilentlyContinue
    if ($null -ne $RedisCmd) {
        Write-Host "      Starting Redis Server..." -ForegroundColor Yellow
        Start-Process -FilePath $RedisCmd.Source -WorkingDirectory "$RootPath\writable" -WindowStyle Minimized
        Start-Sleep -Seconds 1
    } elseif (Test-Path "C:\Program Files\Redis\redis-server.exe") {
        Write-Host "      Starting Redis Server from Program Files..." -ForegroundColor Yellow
        Start-Process -FilePath "C:\Program Files\Redis\redis-server.exe" -WorkingDirectory "$RootPath\writable" -WindowStyle Minimized
        Start-Sleep -Seconds 1
    } else {
        Write-Host "      Redis server not found locally. Fallback file-cache will be used." -ForegroundColor Yellow
    }
} else {
    Write-Host "      Redis Server is running." -ForegroundColor Green
}

# 2. Start Python Extractor
Write-Host "[2/3] Launching Python Extraction Microservice on Port 8000..." -ForegroundColor Cyan
Start-Process -FilePath "cmd.exe" -ArgumentList "/k cd /d `"$RootPath`" && extractor\.venv\Scripts\python -m uvicorn extractor.main:app --host 127.0.0.1 --port 8000 --loop asyncio" -WindowStyle Normal

Start-Sleep -Seconds 3

# 3. Start CodeIgniter 4 API
Write-Host "[3/3] Launching CodeIgniter 4 API on Port 8085..." -ForegroundColor Cyan
Start-Process -FilePath "cmd.exe" -ArgumentList "/k cd /d `"$RootPath`" && php spark serve --port 8085" -WindowStyle Normal

Write-Host ""
Write-Host "========================================================" -ForegroundColor Green
Write-Host "  All services are now running in separate windows!" -ForegroundColor Green
Write-Host ""
Write-Host "  - Redis Cache:      127.0.0.1:6379" -ForegroundColor Yellow
Write-Host "  - Web UI Dashboard: http://localhost:8085/" -ForegroundColor Yellow
Write-Host "  - Swagger API Docs: http://localhost:8085/api/docs" -ForegroundColor Yellow
Write-Host "  - Python Extractor: http://127.0.0.1:8000/health" -ForegroundColor Yellow
Write-Host "========================================================" -ForegroundColor Green
