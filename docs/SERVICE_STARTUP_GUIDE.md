# Service Startup & Management Guide

This guide explains how to start, verify, and manage the two microservices that power the OLX India Real-Time Data Extraction API.

---

## 1. Overview of the Microservice Pair

```
┌────────────────────────────────────────────────────────┐
│                   CLIENT / FRONTEND                    │
│        (Browser UI / Mobile App / API Consumer)        │
└───────────────────────────┬────────────────────────────┘
                            │ (X-API-Key: dev-local-api-key)
                            ▼
┌────────────────────────────────────────────────────────┐
│             SERVICE 1: CodeIgniter 4 API               │
│               http://localhost:8085                    │
│   - Client Auth & Quotas                               │
│   - Request Validation & Caching (Redis/File)          │
│   - Data Normalization & MySQL/SQLite Storage          │
│   - Interactive Web UI Explorer (http://localhost:8085)│
└───────────────────────────┬────────────────────────────┘
                            │ (HTTP Internal Proxy)
                            ▼
┌────────────────────────────────────────────────────────┐
│           SERVICE 2: Python Extraction Engine          │
│               http://127.0.0.1:8000                    │
│   - Fast TLS Chrome Fingerprint (curl_cffi)            │
│   - Headless Browser Fallback (Playwright)             │
│   - Multi-Page Auto-Pagination (up to 300 items)       │
└───────────────────────────┬────────────────────────────┘
                            │
                            ▼
                     [ OLX India Web ]
```

---

## 2. Starting the Services on Windows

### Method A: Manual Start (Two Terminal Windows)

#### Terminal 1: Start Python Extractor (Port 8000)
```powershell
cd e:\scraper
.\extractor\.venv\Scripts\uvicorn extractor.main:app --host 127.0.0.1 --port 8000
```
*You should see:* `Uvicorn running on http://127.0.0.1:8000`

#### Terminal 2: Start CodeIgniter API (Port 8085)
```powershell
cd e:\scraper
php spark serve --port 8085
```
*You should see:* `CodeIgniter development server started on http://localhost:8085`

---

### Method B: One-Click Startup Script (Windows Batch)

Double-click or run [`start_services.bat`](file:///e:/scraper/start_services.bat) in the project root:

```cmd
e:\scraper\start_services.bat
```

---

## 3. Starting the Services on Linux / macOS

### Terminal 1: Start Python Extractor
```bash
cd /path/to/scraper
source extractor/.venv/bin/activate
uvicorn extractor.main:app --host 127.0.0.1 --port 8000
```

### Terminal 2: Start CodeIgniter 4 API
```bash
cd /path/to/scraper
php spark serve --host 0.0.0.0 --port 8085
```

---

## 4. Verifying Both Services

Run these quick health checks to verify both services are running:

### 1. Check Python Extractor Health:
```bash
curl http://127.0.0.1:8000/health
```
**Expected Output:**
```json
{"status": "healthy", "service": "olx-python-extractor", "browser_fallback_enabled": true}
```

### 2. Check CodeIgniter API Health:
```bash
curl http://localhost:8085/api/v1/health
```
**Expected Output:**
```json
{"status": "healthy", "service": "olx-india-data-api"}
```

### 3. Open the Web UI:
Open **[http://localhost:8085/](http://localhost:8085/)** in your browser.

---

## 5. How to Stop the Services

### Option A: One-Click Stop (Windows)
Double-click or run [`stop_services.bat`](file:///e:/scraper/stop_services.bat) in the project root:
```cmd
e:\scraper\stop_services.bat
```
*(Or run `.\stop_services.ps1` in PowerShell)*

### Option B: Interactive Terminals
Press `Ctrl + C` in both terminal windows.

### Option C: Manual Command Line
* **On Windows**:
  ```powershell
  taskkill /f /im uvicorn.exe
  taskkill /f /im php.exe
  ```
* **On Linux**:
  ```bash
  pkill -f uvicorn
  pkill -f "spark serve"
  ```

---

## 6. Troubleshooting Common Issues

| Issue | Cause | Solution |
| :--- | :--- | :--- |
| **`502 Bad Gateway / SOURCE_UNAVAILABLE`** | Python Extractor on port 8000 is not running. | Start the Python extractor in Terminal 1 (`uvicorn extractor.main:app --host 127.0.0.1 --port 8000`). |
| **`401 Unauthorized`** | Missing or incorrect `X-API-Key` header. | Pass `-H "X-API-Key: dev-local-api-key"` in your request. |
| **`Port 8085 already in use`** | Another process is using port 8085. | Start on another port (e.g. `php spark serve --port 8086`) and update `app.baseURL` in `.env`. |
| **`Playwright Browser Missing`** | Chromium binaries not downloaded yet. | Run `extractor\.venv\Scripts\playwright install chromium`. |
