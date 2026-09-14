import sys
import asyncio

if sys.platform == "win32":
    asyncio.set_event_loop_policy(asyncio.WindowsProactorEventLoopPolicy())

import logging
from typing import Optional
from contextlib import asynccontextmanager
from fastapi import FastAPI, Depends, HTTPException, Query
from fastapi.responses import HTMLResponse
from fastapi.middleware.cors import CORSMiddleware
import uvicorn

from extractor.config import settings
from extractor.models.request import SearchQueryParams
from extractor.models.response import SearchResponse, DetailResponse
from extractor.extractors.olx_search import olx_search_extractor
from extractor.extractors.olx_detail import olx_detail_extractor
from extractor.extractors.cardekho_search import cardekho_search_extractor
from extractor.extractors.cardekho_detail import cardekho_detail_extractor
from extractor.extractors.naukri_search import naukri_search_extractor
from extractor.extractors.naukri_detail import naukri_detail_extractor
from extractor.extractors.cashify_search import cashify_search_extractor
from extractor.extractors.cashify_detail import cashify_detail_extractor
from extractor.engine.browser_pool import browser_manager
from extractor.engine.http_client import http_client

logging.basicConfig(
    level=logging.INFO if not settings.DEBUG else logging.DEBUG,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("marketplace_extractor")

@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("Starting Data Extraction Microservice (OLX, CarDekho, Naukri & Cashify)...")
    yield
    logger.info("Shutting down extractor and closing connections...")
    await browser_manager.close()
    await http_client.close()

app = FastAPI(
    title="Marketplace Data Extractor Microservice",
    description="High-performance extraction engine for real-time OLX India, CarDekho, Naukri.com, and Cashify.in marketplace data",
    version="1.3.0",
    lifespan=lifespan,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

DOCS_HTML = """<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Marketplace Data Extractor API Documentation</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    :root {
      --bg: #0b0f19;
      --card-bg: #111827;
      --card-border: #1f293d;
      --text-main: #f3f4f6;
      --text-muted: #9ca3af;
      --accent: #38bdf8;
      --accent-glow: rgba(56, 189, 248, 0.2);
      --success: #10b981;
      --code-bg: #030712;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background-color: var(--bg); color: var(--text-main); line-height: 1.6; padding-bottom: 60px; }
    header {
      background: linear-gradient(180deg, #111827 0%, #0b0f19 100%);
      border-bottom: 1px solid var(--card-border);
      padding: 30px 20px;
      margin-bottom: 30px;
    }
    .header-content {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 20px;
    }
    .brand h1 { font-size: 1.8rem; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 12px; }
    .brand h1 i { color: var(--accent); }
    .brand p { color: var(--text-muted); font-size: 0.95rem; margin-top: 4px; }
    .nav-links { display: flex; gap: 12px; }
    .nav-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-radius: 8px;
      background: #1e293b;
      color: #fff;
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 600;
      border: 1px solid #334155;
      transition: all 0.2s;
    }
    .nav-btn:hover { background: #334155; border-color: var(--accent); }
    .nav-btn.primary { background: #0284c7; border-color: #38bdf8; }
    .nav-btn.primary:hover { background: #0369a1; }
    .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
    .metrics-bar {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 16px;
      margin-bottom: 30px;
    }
    .metric-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 12px;
      padding: 16px 20px;
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .metric-icon {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      background: rgba(56, 189, 248, 0.1);
      color: var(--accent);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
    }
    .metric-data h3 { font-size: 1.25rem; font-weight: 700; color: #fff; }
    .metric-data p { font-size: 0.82rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .section-title {
      font-size: 1.4rem;
      font-weight: 700;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      border-left: 4px solid var(--accent);
      padding-left: 12px;
    }
    .card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 12px;
      padding: 24px;
      margin-bottom: 24px;
    }
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
      padding-bottom: 12px;
      border-bottom: 1px solid #1f2937;
    }
    .card-header h2 { font-size: 1.2rem; display: flex; align-items: center; gap: 10px; }
    .method-badge {
      font-size: 0.75rem;
      font-weight: 800;
      padding: 4px 10px;
      border-radius: 6px;
      background: #065f46;
      color: #34d399;
      letter-spacing: 0.05em;
    }
    .endpoint-url {
      font-family: monospace;
      font-size: 0.95rem;
      color: #38bdf8;
      background: #030712;
      padding: 6px 12px;
      border-radius: 6px;
      border: 1px solid #1f2937;
    }
    table { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 0.9rem; }
    th { text-align: left; padding: 10px 12px; background: #0f172a; color: #94a3b8; font-weight: 600; border-bottom: 1px solid #1e293b; }
    td { padding: 10px 12px; border-bottom: 1px solid #1e293b; color: #e2e8f0; }
    .code-block {
      background: var(--code-bg);
      border: 1px solid #1f2937;
      border-radius: 8px;
      padding: 14px 16px;
      margin-top: 14px;
      overflow-x: auto;
      position: relative;
    }
    .code-block pre { color: #a5f3fc; font-family: monospace; font-size: 0.85rem; }
    .badge {
      display: inline-block;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 4px;
      background: #1e293b;
      color: #94a3b8;
      border: 1px solid #334155;
    }
    .badge.cat { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border-color: rgba(56, 189, 248, 0.3); }
    .badge.excluded { background: rgba(239, 68, 68, 0.15); color: #f87171; border-color: rgba(239, 68, 68, 0.3); text-decoration: line-through; }
    .cat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 12px;
      margin-top: 12px;
    }
    .cat-item {
      background: #0f172a;
      border: 1px solid #1e293b;
      padding: 12px 14px;
      border-radius: 8px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .cat-item span { font-size: 0.9rem; font-weight: 600; }
  </style>
</head>
<body>
  <header>
    <div class="header-content">
      <div class="brand">
        <h1><i class="fa-solid fa-bolt"></i> Marketplace Extractor Microservice</h1>
        <p>Async FastAPI Data Engine for OLX India, CarDekho, Naukri & Cashify</p>
      </div>
      <div class="nav-links">
        <a href="/docs" target="_blank" class="nav-btn primary"><i class="fa-solid fa-code"></i> Swagger UI</a>
        <a href="/redoc" target="_blank" class="nav-btn"><i class="fa-solid fa-book"></i> ReDoc</a>
        <a href="/health" target="_blank" class="nav-btn"><i class="fa-solid fa-heart-pulse"></i> Health Check</a>
      </div>
    </div>
  </header>

  <main class="container">
    <div class="metrics-bar">
      <div class="metric-card">
        <div class="metric-icon"><i class="fa-solid fa-gauge-high"></i></div>
        <div class="metric-data">
          <h3>&lt; 900 ms</h3>
          <p>500 Listings / 10 Cats Parallel</p>
        </div>
      </div>
      <div class="metric-card">
        <div class="metric-icon" style="color: #10b981; background: rgba(16, 185, 129, 0.1);"><i class="fa-solid fa-microchip"></i></div>
        <div class="metric-data">
          <h3>~8.0 ms</h3>
          <p>In-Memory TTL Cache Hits</p>
        </div>
      </div>
      <div class="metric-card">
        <div class="metric-icon" style="color: #f59e0b; background: rgba(245, 158, 11, 0.1);"><i class="fa-solid fa-network-wired"></i></div>
        <div class="metric-data">
          <h3>4 Platforms</h3>
          <p>OLX, CarDekho, Naukri, Cashify</p>
        </div>
      </div>
      <div class="metric-card">
        <div class="metric-icon" style="color: #a855f7; background: rgba(168, 85, 247, 0.1);"><i class="fa-solid fa-car-side"></i></div>
        <div class="metric-data">
          <h3>2-in-1 Auto</h3>
          <p>Cars & Bikes Interleaved</p>
        </div>
      </div>
    </div>

    <!-- Active Categories -->
    <h2 class="section-title"><i class="fa-solid fa-tags"></i> Active OLX India Categories</h2>
    <div class="card">
      <p style="color: var(--text-muted); margin-bottom: 14px;">All active categories use verified internal OLX IDs for high-speed direct extraction. Querying <code>category=automobile</code> automatically queries both Cars (84) and Bikes (81) in parallel and interleaves them.</p>
      <div class="cat-grid">
        <div class="cat-item"><span><i class="fa-solid fa-car"></i> Automobiles (Cars & Bikes)</span><span class="badge cat">automobile (84 + 81)</span></div>
        <div class="cat-item"><span><i class="fa-solid fa-motorcycle"></i> Motorcycles & Bikes</span><span class="badge cat">bikes (81)</span></div>
        <div class="cat-item"><span><i class="fa-solid fa-building"></i> Properties & Real Estate</span><span class="badge cat">properties (3)</span></div>
        <div class="cat-item"><span><i class="fa-solid fa-tv"></i> Electronics & Appliances</span><span class="badge cat">electronics (99)</span></div>
        <div class="cat-item"><span><i class="fa-solid fa-mobile-screen"></i> Mobiles & Tablets</span><span class="badge cat">mobiles (1411)</span></div>
        <div class="cat-item"><span><i class="fa-solid fa-briefcase"></i> Jobs & Hiring</span><span class="badge cat">jobs (4)</span></div>
        <div class="cat-item"><span><i class="fa-solid fa-couch"></i> Furniture & Decor</span><span class="badge cat">furniture (628)</span></div>
        <div class="cat-item"><span><i class="fa-solid fa-shirt"></i> Fashion & Clothing</span><span class="badge cat">fashion (87)</span></div>
        <div class="cat-item"><span><i class="fa-solid fa-paw"></i> Pets & Animals</span><span class="badge cat">pets (103)</span></div>
        <div class="cat-item"><span><i class="fa-solid fa-wrench"></i> Services</span><span class="badge cat">services (619)</span></div>
        <div class="cat-item" style="opacity: 0.5;"><span><i class="fa-solid fa-truck"></i> Commercial Vehicles</span><span class="badge excluded">Excluded</span></div>
        <div class="cat-item" style="opacity: 0.5;"><span><i class="fa-solid fa-book"></i> Books & Hobbies</span><span class="badge excluded">Excluded</span></div>
      </div>
    </div>

    <!-- API Endpoints -->
    <h2 class="section-title"><i class="fa-solid fa-server"></i> API Endpoints</h2>

    <!-- OLX -->
    <div class="card">
      <div class="card-header">
        <h2><i class="fa-solid fa-globe" style="color: #38bdf8;"></i> OLX India Search</h2>
        <span class="method-badge">GET</span>
      </div>
      <div class="endpoint-url">/olx/listings?category={cat}&city={city}&limit={limit}</div>
      <table>
        <thead>
          <tr><th>Param</th><th>Type</th><th>Default</th><th>Description</th></tr>
        </thead>
        <tbody>
          <tr><td><code>category</code></td><td>string</td><td>null</td><td><code>automobile</code>, <code>cars</code>, <code>bikes</code>, <code>properties</code>, <code>electronics</code>, <code>mobiles</code>, <code>jobs</code>, <code>furniture</code>, <code>fashion</code>, <code>pets</code>, <code>services</code></td></tr>
          <tr><td><code>subcategory</code></td><td>string</td><td>null</td><td>Optional subcategory filter (e.g. <code>cars</code>, <code>bikes</code>)</td></tr>
          <tr><td><code>city</code></td><td>string</td><td>null</td><td>City name (e.g. <code>Delhi</code>, <code>Bangalore</code>, <code>Mumbai</code>, <code>Kolkata</code>)</td></tr>
          <tr><td><code>keyword</code></td><td>string</td><td>null</td><td>Free-text search query</td></tr>
          <tr><td><code>min_price / max_price</code></td><td>int</td><td>null</td><td>Price bounds in INR</td></tr>
          <tr><td><code>limit</code></td><td>int</td><td>50</td><td>Number of listings (1 to 300)</td></tr>
          <tr><td><code>page</code></td><td>int</td><td>1</td><td>Page offset (1-indexed)</td></tr>
        </tbody>
      </table>
      <div class="code-block">
        <pre>curl -X GET "http://127.0.0.1:8000/olx/listings?category=automobile&city=Delhi&limit=10"</pre>
      </div>
    </div>

    <!-- CarDekho -->
    <div class="card">
      <div class="card-header">
        <h2><i class="fa-solid fa-car" style="color: #f97316;"></i> CarDekho Cars Search</h2>
        <span class="method-badge">GET</span>
      </div>
      <div class="endpoint-url">/cardekho/listings?city={city}&brand={brand}&limit={limit}</div>
      <table>
        <thead><tr><th>Param</th><th>Type</th><th>Description</th></tr></thead>
        <tbody>
          <tr><td><code>city</code></td><td>string</td><td>City name (e.g. <code>delhi</code>, <code>mumbai</code>, <code>bangalore</code>)</td></tr>
          <tr><td><code>brand</code></td><td>string</td><td>Car brand (e.g. <code>maruti</code>, <code>hyundai</code>, <code>honda</code>)</td></tr>
          <tr><td><code>min_price / max_price</code></td><td>int</td><td>Price bounds in INR</td></tr>
          <tr><td><code>fuel_type</code></td><td>string</td><td><code>petrol</code>, <code>diesel</code>, <code>cng</code>, <code>electric</code></td></tr>
        </tbody>
      </table>
      <div class="code-block">
        <pre>curl -X GET "http://127.0.0.1:8000/cardekho/listings?city=delhi&brand=hyundai&limit=10"</pre>
      </div>
    </div>

    <!-- Cashify -->
    <div class="card">
      <div class="card-header">
        <h2><i class="fa-solid fa-mobile-screen" style="color: #10b981;"></i> Cashify Refurbished Gadgets</h2>
        <span class="method-badge">GET</span>
      </div>
      <div class="endpoint-url">/cashify/listings?category={category}&brand={brand}&limit={limit}</div>
      <table>
        <thead><tr><th>Param</th><th>Type</th><th>Description</th></tr></thead>
        <tbody>
          <tr><td><code>category</code></td><td>string</td><td><code>smartphones</code>, <code>laptops</code>, <code>smartwatches</code>, <code>tablets</code>, <code>accessories</code></td></tr>
          <tr><td><code>brand</code></td><td>string</td><td><code>apple</code>, <code>samsung</code>, <code>oneplus</code>, <code>xiaomi</code>, <code>google</code></td></tr>
          <tr><td><code>min_price / max_price</code></td><td>int</td><td>Price bounds in INR</td></tr>
        </tbody>
      </table>
      <div class="code-block">
        <pre>curl -X GET "http://127.0.0.1:8000/cashify/listings?category=smartphones&brand=apple&limit=5"</pre>
      </div>
    </div>

    <!-- Naukri -->
    <div class="card">
      <div class="card-header">
        <h2><i class="fa-solid fa-briefcase" style="color: #3b82f6;"></i> Naukri Jobs Search</h2>
        <span class="method-badge">GET</span>
      </div>
      <div class="endpoint-url">/naukri/listings?keyword={keyword}&city={city}&experience={exp}</div>
      <table>
        <thead><tr><th>Param</th><th>Type</th><th>Description</th></tr></thead>
        <tbody>
          <tr><td><code>keyword</code></td><td>string</td><td>Role or tech stack (e.g. <code>python react</code>, <code>devops</code>)</td></tr>
          <tr><td><code>city</code></td><td>string</td><td>Job location (e.g. <code>Bangalore</code>, <code>Hyderabad</code>, <code>Pune</code>)</td></tr>
          <tr><td><code>experience</code></td><td>int</td><td>Years of experience (e.g. <code>0</code>, <code>3</code>, <code>5</code>)</td></tr>
        </tbody>
      </table>
      <div class="code-block">
        <pre>curl -X GET "http://127.0.0.1:8000/naukri/listings?keyword=python&city=Bangalore&limit=10"</pre>
      </div>
    </div>
  </main>
</body>
</html>
"""

@app.get("/", response_class=HTMLResponse, include_in_schema=False)
@app.get("/api-docs", response_class=HTMLResponse, include_in_schema=False)
@app.get("/docs-page", response_class=HTMLResponse, include_in_schema=False)
async def documentation_page():
    return HTMLResponse(content=DOCS_HTML)

@app.get("/health")
async def health_check():
    return {
        "status": "healthy",
        "service": "marketplace-python-extractor",
        "sources": ["olx", "cardekho", "naukri", "cashify"],
        "browser_fallback_enabled": settings.ENABLE_BROWSER_FALLBACK,
    }



# ==================== OLX ENDPOINTS ====================

@app.get("/listings", response_model=SearchResponse, tags=["OLX"])
@app.get("/olx/listings", response_model=SearchResponse, tags=["OLX"])
async def search_olx_listings(
    category: Optional[str] = Query(None),
    subcategory: Optional[str] = Query(None),
    keyword: Optional[str] = Query(None),
    city: Optional[str] = Query(None),
    state: Optional[str] = Query(None),
    locality: Optional[str] = Query(None),
    pincode: Optional[str] = Query(None),
    min_price: Optional[int] = Query(None),
    max_price: Optional[int] = Query(None),
    sort: Optional[str] = Query("newest"),
    page: int = Query(1, ge=1),
    limit: int = Query(50, ge=1, le=300),
    brand: Optional[str] = Query(None),
    model: Optional[str] = Query(None),
    fuel_type: Optional[str] = Query(None),
    transmission: Optional[str] = Query(None),
    min_year: Optional[int] = Query(None),
    max_year: Optional[int] = Query(None),
    min_km: Optional[int] = Query(None),
    max_km: Optional[int] = Query(None),
    bhk: Optional[int] = Query(None),
    url: Optional[str] = Query(None),
):
    if url:
        detail_res = await olx_detail_extractor.get_detail(listing_id="", listing_url=url)
        if not detail_res.data:
            raise HTTPException(status_code=404, detail="Listing not found at provided URL")
        return SearchResponse(
            data=[detail_res.data],
            pagination={"page": 1, "limit": 1, "has_next": False, "total_records": 1}
        )

    params = SearchQueryParams(
        category=category,
        subcategory=subcategory,
        keyword=keyword,
        city=city,
        state=state,
        locality=locality,
        pincode=pincode,
        min_price=min_price,
        max_price=max_price,
        sort=sort,
        page=page,
        limit=limit,
        brand=brand,
        model=model,
        fuel_type=fuel_type,
        transmission=transmission,
        min_year=min_year,
        max_year=max_year,
        min_km=min_km,
        max_km=max_km,
        bhk=bhk,
    )
    return await olx_search_extractor.search(params)

@app.get("/listings/{listing_id}", response_model=DetailResponse, tags=["OLX"])
@app.get("/olx/listings/{listing_id}", response_model=DetailResponse, tags=["OLX"])
async def get_olx_listing_detail(listing_id: str, url: Optional[str] = Query(None)):
    res = await olx_detail_extractor.get_detail(listing_id=listing_id, listing_url=url)
    if not res.data:
        raise HTTPException(status_code=404, detail=f"Listing {listing_id} not found")
    return res

# ==================== CARDEKHO ENDPOINTS ====================

@app.get("/cardekho/listings", response_model=SearchResponse, tags=["CarDekho"])
async def search_cardekho_listings(
    keyword: Optional[str] = Query(None),
    city: Optional[str] = Query(None),
    brand: Optional[str] = Query(None),
    model: Optional[str] = Query(None),
    min_price: Optional[int] = Query(None),
    max_price: Optional[int] = Query(None),
    min_year: Optional[int] = Query(None),
    max_year: Optional[int] = Query(None),
    min_km: Optional[int] = Query(None),
    max_km: Optional[int] = Query(None),
    fuel_type: Optional[str] = Query(None),
    transmission: Optional[str] = Query(None),
    sort: Optional[str] = Query(None),
    page: int = Query(1, ge=1),
    limit: int = Query(20, ge=1, le=300),
    url: Optional[str] = Query(None),
):
    if url:
        detail_res = await cardekho_detail_extractor.get_detail(listing_id="", listing_url=url)
        if not detail_res.data:
            raise HTTPException(status_code=404, detail="CarDekho listing not found at provided URL")
        return SearchResponse(
            data=[detail_res.data],
            pagination={"page": 1, "limit": 1, "has_next": False, "total_records": 1}
        )

    params = SearchQueryParams(
        category="automobile",
        subcategory="cars",
        keyword=keyword,
        city=city,
        brand=brand,
        model=model,
        min_price=min_price,
        max_price=max_price,
        min_year=min_year,
        max_year=max_year,
        min_km=min_km,
        max_km=max_km,
        fuel_type=fuel_type,
        transmission=transmission,
        sort=sort,
        page=page,
        limit=limit,
    )
    return await cardekho_search_extractor.search(params)

@app.get("/cardekho/listings/{listing_id}", response_model=DetailResponse, tags=["CarDekho"])
async def get_cardekho_listing_detail(listing_id: str, url: Optional[str] = Query(None)):
    res = await cardekho_detail_extractor.get_detail(listing_id=listing_id, listing_url=url)
    if not res.data:
        raise HTTPException(status_code=404, detail=f"CarDekho listing {listing_id} not found")
    return res

@app.get("/cardekho/listing", response_model=DetailResponse, tags=["CarDekho"])
async def get_cardekho_listing_by_url(url: str = Query(...)):
    res = await cardekho_detail_extractor.get_detail(listing_id="", listing_url=url)
    if not res.data:
        raise HTTPException(status_code=404, detail="CarDekho listing not found at provided URL")
    return res

# ==================== NAUKRI ENDPOINTS ====================

@app.get("/naukri/listings", response_model=SearchResponse, tags=["Naukri"])
async def search_naukri_listings(
    keyword: Optional[str] = Query(None),
    city: Optional[str] = Query(None),
    category: Optional[str] = Query(None),
    subcategory: Optional[str] = Query(None),
    experience: Optional[int] = Query(None),
    min_experience: Optional[int] = Query(None),
    max_experience: Optional[int] = Query(None),
    min_price: Optional[int] = Query(None),
    max_price: Optional[int] = Query(None),
    company: Optional[str] = Query(None),
    skills: Optional[str] = Query(None),
    salary_range: Optional[str] = Query(None),
    sort: Optional[str] = Query(None),
    page: int = Query(1, ge=1),
    limit: int = Query(20, ge=1, le=300),
    url: Optional[str] = Query(None),
):
    if url:
        detail_res = await naukri_detail_extractor.get_detail(listing_id="", listing_url=url)
        if not detail_res.data:
            raise HTTPException(status_code=404, detail="Naukri job not found at provided URL")
        return SearchResponse(
            data=[detail_res.data],
            pagination={"page": 1, "limit": 1, "has_next": False, "total_records": 1}
        )

    params = SearchQueryParams(
        category=category or "jobs",
        subcategory=subcategory,
        keyword=keyword,
        city=city,
        experience=experience,
        min_experience=min_experience,
        max_experience=max_experience,
        min_price=min_price,
        max_price=max_price,
        company=company,
        skills=skills,
        salary_range=salary_range,
        sort=sort,
        page=page,
        limit=limit,
    )
    return await naukri_search_extractor.search(params)

@app.get("/naukri/listings/{listing_id}", response_model=DetailResponse, tags=["Naukri"])
async def get_naukri_listing_detail(listing_id: str, url: Optional[str] = Query(None)):
    res = await naukri_detail_extractor.get_detail(listing_id=listing_id, listing_url=url)
    if not res.data:
        raise HTTPException(status_code=404, detail=f"Naukri listing {listing_id} not found")
    return res

@app.get("/naukri/listing", response_model=DetailResponse, tags=["Naukri"])
async def get_naukri_listing_by_url(url: str = Query(...)):
    res = await naukri_detail_extractor.get_detail(listing_id="", listing_url=url)
    if not res.data:
        raise HTTPException(status_code=404, detail="Naukri listing not found at provided URL")
    return res

# ==================== CASHIFY ENDPOINTS ====================

@app.get("/cashify/listings", response_model=SearchResponse, tags=["Cashify"])
async def search_cashify_listings(
    keyword: Optional[str] = Query(None),
    category: Optional[str] = Query(None),
    subcategory: Optional[str] = Query(None),
    brand: Optional[str] = Query(None),
    city: Optional[str] = Query(None),
    min_price: Optional[int] = Query(None),
    max_price: Optional[int] = Query(None),
    sort: Optional[str] = Query(None),
    page: int = Query(1, ge=1),
    limit: int = Query(20, ge=1, le=300),
    url: Optional[str] = Query(None),
):
    if url:
        detail_res = await cashify_detail_extractor.get_detail(listing_id="", listing_url=url)
        if not detail_res.data:
            raise HTTPException(status_code=404, detail="Cashify listing not found at provided URL")
        return SearchResponse(
            data=[detail_res.data],
            pagination={"page": 1, "limit": 1, "has_next": False, "total_records": 1}
        )

    params = SearchQueryParams(
        category=category or "mobiles",
        subcategory=subcategory,
        keyword=keyword,
        brand=brand,
        city=city,
        min_price=min_price,
        max_price=max_price,
        sort=sort,
        page=page,
        limit=limit,
    )
    return await cashify_search_extractor.search(params)

@app.get("/cashify/listings/{listing_id}", response_model=DetailResponse, tags=["Cashify"])
async def get_cashify_listing_detail(listing_id: str, url: Optional[str] = Query(None)):
    res = await cashify_detail_extractor.get_detail(listing_id=listing_id, listing_url=url)
    if not res.data:
        raise HTTPException(status_code=404, detail=f"Cashify listing {listing_id} not found")
    return res

@app.get("/cashify/listing", response_model=DetailResponse, tags=["Cashify"])
async def get_cashify_listing_by_url(url: str = Query(...)):
    res = await cashify_detail_extractor.get_detail(listing_id="", listing_url=url)
    if not res.data:
        raise HTTPException(status_code=404, detail="Cashify listing not found at provided URL")
    return res

if __name__ == "__main__":
    uvicorn.run(
        "extractor.main:app",
        host=settings.HOST,
        port=settings.PORT,
        reload=settings.DEBUG,
    )

