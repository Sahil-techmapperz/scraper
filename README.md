# Multi-Platform (OLX India & Cashify) Real-Time Data Extraction API & Web Explorer

Production-ready CodeIgniter 4 REST API and Python Extraction Microservice for authenticated, cache-aware OLX India and Cashify refurbished electronics data extraction with a built-in interactive Web UI dashboard.

---

## 🌟 Key Features

* **Interactive Web Explorer UI**: Explore, filter, and view live vehicle, phone, property, and refurbished electronics listings at `http://localhost:8085/`.
* **Multi-Platform Support**:
  * **OLX India**: Cars, Commercial Vehicles, Mobile Phones, Real Estate, and Electronics.
  * **Cashify India**: Refurbished Mobile Phones, Laptops, Tablets, Smartwatches, and Gaming Consoles with condition grades, warranty periods, and MRP discounts.
* **High-Performance Python Extraction Engine**: Uses `curl_cffi` Chrome TLS/JA3 impersonation and Next.js RSC state stream extraction to extract live listings in **~0.8s - 1.5s**.
* **Auto-Pagination up to 300 Items**: Concurrent multi-page extraction across multiple pages per request.
* **Full Multi-Tenancy & Security**: API-Key authentication (`X-API-Key`), rate limits, monthly request quotas, and deterministic SHA-256 query caching with Redis.
* **Normalized Relational Persistence & Web App Integration**: 1-click push to main web application database (`extracted_products`) with typed TypeScript mappers (`CashifyMapper`, `OlxMapper`).

---

## 🚀 Quick Start (Running Both Services)

### Option 1: One-Click Startup (Windows)
Double-click [`start_services.bat`](start_services.bat) in the project root or run [`start_services.ps1`](start_services.ps1).

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
* **OLX Search API**: `GET /api/v1/olx/listings?category=cars&city=Kolkata&limit=50`
* **OLX Detail API**: `GET /api/v1/olx/listings/{listing_id}`
* **Cashify Search API**: `GET /api/v1/cashify/listings?category=mobile-phones&brand=apple&limit=50`
* **Cashify Detail API**: `GET /api/v1/cashify/listings/{listing_id}`
* **Cashify Direct URL Lookup**: `GET /api/v1/cashify/listing?url=https://www.cashify.in/buy-refurbished-mobile-phones/...`

---

## 📚 Documentation Links

* 📖 **[Service Startup Guide](docs/SERVICE_STARTUP_GUIDE.md)**: Detailed startup instructions, scripts, and troubleshooting.
* 💡 **[API Usage Examples](docs/API_EXAMPLES.md)**: cURL examples for all categories, limits up to 300 items, and cache bypass.
* 🚀 **[Deployment Guide](docs/DEPLOYMENT.md)**: Production architecture, Systemd, PM2, and Nginx configurations.
* ⚖️ **[Compliance & Governance](docs/COMPLIANCE.md)**: Operating modes, caching policies, and DPDP privacy guidelines.
