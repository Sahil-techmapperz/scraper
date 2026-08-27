# OLX India Real-Time Data Extraction API & Web Explorer

Production-ready CodeIgniter 4 REST API and Python Extraction Microservice for authenticated, cache-aware OLX India listing data extraction with a built-in interactive Web UI dashboard.

---

## 🌟 Key Features

* **Interactive Web Explorer UI**: Explore, filter, and view live vehicle, phone, and property listings at `http://localhost:8085/`.
* **High-Performance Python Extraction Engine**: Uses `curl_cffi` Chrome TLS/JA3 impersonation and Playwright stealth fallback to extract live listings in **~1.2s - 2.2s**.
* **Auto-Pagination up to 300 Items**: Concurrent multi-page extraction (`asyncio.gather`) across up to 8 pages per request.
* **Full Multi-Tenancy & Security**: API-Key authentication (`X-API-Key`), rate limits, monthly request quotas, and deterministic SHA-256 query caching.
* **Normalized Relational Persistence**: Optional database upserts into MySQL/SQLite with normalized seller, image, location, and attribute tables.

---

## 🚀 Quick Start (Running Both Services)

### Option 1: One-Click Startup (Windows)
Double-click [`start_services.bat`](start_services.bat) in the project root.

### Option 2: Manual Startup

**Terminal 1 (Python Extractor - Port 8000):**
```bash
extractor\.venv\Scripts\uvicorn extractor.main:app --host 127.0.0.1 --port 8000
```

**Terminal 2 (CodeIgniter API - Port 8085):**
```bash
php spark serve --port 8085
```

---

## ⏹️ Stopping the Services

Double-click [`stop_services.bat`](stop_services.bat) in the project root to stop both services instantly.

---

## 🌐 Endpoints & Access

* **Web UI Dashboard**: [http://localhost:8085/](http://localhost:8085/)
* **Interactive Swagger Docs**: [http://localhost:8085/api/docs](http://localhost:8085/api/docs)
* **Search API (Max 300 items)**: `GET /api/v1/olx/listings?category=cars&city=Kolkata&limit=300`
* **Detail API**: `GET /api/v1/olx/listings/{listing_id}`
* **Direct URL Lookup**: `GET /api/v1/olx/listing?url=https://www.olx.in/item/...`

---

## 📚 Documentation Links

* 📖 **[Service Startup Guide](docs/SERVICE_STARTUP_GUIDE.md)**: Detailed startup instructions, scripts, and troubleshooting.
* 💡 **[API Usage Examples](docs/API_EXAMPLES.md)**: cURL examples for all categories, limits up to 300 items, and cache bypass.
* 🚀 **[Deployment Guide](docs/DEPLOYMENT.md)**: Production architecture, Systemd, PM2, and Nginx configurations.
* ⚖️ **[Compliance & Governance](docs/COMPLIANCE.md)**: Operating modes, caching policies, and DPDP privacy guidelines.
