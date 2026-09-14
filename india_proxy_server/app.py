import os
import logging
from typing import Optional, Dict, Any
from fastapi import FastAPI, Header, HTTPException, status
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from curl_cffi import requests

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("india_proxy")

app = FastAPI(
    title="XYZFinders Indian Proxy Gateway",
    description="Lightweight micro-proxy bypassing Akamai & Cloudflare geo-restrictions via Chrome 120 TLS impersonation.",
    version="1.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Secret token for authentication (override via environment variable)
PROXY_SECRET_TOKEN = os.getenv("PROXY_SECRET_TOKEN", "xyz-india-secret-key-2026")

class FetchPayload(BaseModel):
    url: str
    method: Optional[str] = "GET"
    headers: Optional[Dict[str, str]] = None
    params: Optional[Dict[str, Any]] = None
    data: Optional[Dict[str, Any]] = None
    timeout: Optional[int] = 15

@app.get("/")
def health_check():
    """Quick health check showing server status and Indian public IP."""
    try:
        ip_info = requests.get("https://ipinfo.io/json", timeout=5).json()
        current_ip = ip_info.get("ip")
        country = ip_info.get("country")
        city = ip_info.get("city")
    except Exception:
        current_ip, country, city = "Unknown", "Unknown", "Unknown"

    return {
        "status": "healthy",
        "service": "XYZFinders India Proxy Node",
        "ip": current_ip,
        "country": country,
        "city": city,
    }

@app.post("/fetch")
def proxy_fetch(payload: FetchPayload, x_proxy_token: Optional[str] = Header(None)):
    """Fetch URL with Chrome 120 TLS fingerprint impersonation from this Indian IP."""
    # 1. Authenticate request
    if x_proxy_token != PROXY_SECRET_TOKEN:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing X-Proxy-Token header",
        )

    # 2. Build default realistic headers if not provided
    default_headers = {
        "Accept": "application/json, text/plain, */*",
        "Accept-Language": "en-IN,en;q=0.9,hi;q=0.8",
        "Referer": "https://www.olx.in/",
        "Origin": "https://www.olx.in",
    }
    if payload.headers:
        default_headers.update(payload.headers)

    # 3. Execute request using curl_cffi with Chrome 120 impersonation
    try:
        logger.info(f"Proxying {payload.method} request to: {payload.url}")
        
        if payload.method.upper() == "POST":
            resp = requests.post(
                payload.url,
                json=payload.data,
                params=payload.params,
                headers=default_headers,
                impersonate="chrome120",
                timeout=payload.timeout,
            )
        else:
            resp = requests.get(
                payload.url,
                params=payload.params,
                headers=default_headers,
                impersonate="chrome120",
                timeout=payload.timeout,
            )

        # 4. Return parsed response
        is_json = "application/json" in resp.headers.get("content-type", "").lower()
        
        return {
            "success": resp.status_code == 200,
            "status_code": resp.status_code,
            "url": payload.url,
            "data": resp.json() if is_json else resp.text,
        }

    except Exception as exc:
        logger.error(f"Error fetching {payload.url}: {exc}")
        raise HTTPException(status_code=500, detail=str(exc))
