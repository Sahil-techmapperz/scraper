import sys
import asyncio

if sys.platform == "win32":
    asyncio.set_event_loop_policy(asyncio.WindowsProactorEventLoopPolicy())

import logging
from typing import Optional
from contextlib import asynccontextmanager
from fastapi import FastAPI, Depends, HTTPException, Query
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

logging.basicConfig(
    level=logging.INFO if not settings.DEBUG else logging.DEBUG,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("marketplace_extractor")

@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("Starting Data Extraction Microservice (OLX, CarDekho, Naukri & Cashify)...")
    yield
    logger.info("Shutting down extractor and closing browser pool...")
    await browser_manager.close()

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

