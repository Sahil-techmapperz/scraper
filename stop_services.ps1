# PowerShell Script to Stop Both Scraper Services
Write-Host "========================================================" -ForegroundColor Red
Write-Host "  Stopping OLX Real-Time Data Scraper Stack" -ForegroundColor Red
Write-Host "========================================================" -ForegroundColor Red
Write-Host ""

# Stop Port 8000 (Python Extractor)
Write-Host "[1/2] Stopping Python Extractor on Port 8000..." -ForegroundColor Yellow
$p8000 = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if ($p8000) {
    Stop-Process -Id $p8000.OwningProcess -Force -ErrorAction SilentlyContinue
    Write-Host "  - Terminated PID $($p8000.OwningProcess)" -ForegroundColor Green
} else {
    Write-Host "  - Port 8000 is not currently active." -ForegroundColor Gray
}
Get-Process -Name "uvicorn" -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue

# Stop Port 8085 (CodeIgniter API)
Write-Host "[2/2] Stopping CodeIgniter API on Port 8085..." -ForegroundColor Yellow
$p8085 = Get-NetTCPConnection -LocalPort 8085 -State Listen -ErrorAction SilentlyContinue
if ($p8085) {
    Stop-Process -Id $p8085.OwningProcess -Force -ErrorAction SilentlyContinue
    Write-Host "  - Terminated PID $($p8085.OwningProcess)" -ForegroundColor Green
} else {
    Write-Host "  - Port 8085 is not currently active." -ForegroundColor Gray
}

Write-Host ""
Write-Host "========================================================" -ForegroundColor Green
Write-Host "  All scraper microservices have been stopped!" -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Green
