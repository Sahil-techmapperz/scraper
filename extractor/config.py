import os
from pydantic import BaseModel
from dotenv import load_dotenv

load_dotenv()

class Settings(BaseModel):
    HOST: str = os.getenv("EXTRACTOR_HOST", "0.0.0.0")
    PORT: int = int(os.getenv("EXTRACTOR_PORT", "8000"))
    DEBUG: bool = os.getenv("EXTRACTOR_DEBUG", "false").lower() == "true"
    
    # Extraction Engine Config
    DEFAULT_TIMEOUT: int = int(os.getenv("EXTRACTOR_TIMEOUT", "20"))
    MAX_RETRIES: int = int(os.getenv("EXTRACTOR_MAX_RETRIES", "3"))
    ENABLE_BROWSER_FALLBACK: bool = os.getenv("ENABLE_BROWSER_FALLBACK", "true").lower() == "true"
    HEADLESS_BROWSER: bool = os.getenv("HEADLESS_BROWSER", "true").lower() == "true"
    
    # High-Performance Caching & Connection Pool
    ENABLE_HTTP_CACHE: bool = os.getenv("ENABLE_HTTP_CACHE", "true").lower() == "true"
    CACHE_TTL_SECONDS: int = int(os.getenv("CACHE_TTL_SECONDS", "60"))

    # Optional Proxy Configuration (e.g., http://user:pass@proxy.example.com:8080)
    PROXY_URL: str = os.getenv("EXTRACTOR_PROXY_URL", "")
    
    # OLX Base URLs & Endpoints
    OLX_IN_BASE_URL: str = "https://www.olx.in"
    OLX_API_BASE_URL: str = "https://www.olx.in/api"

    # CarDekho Base URLs & Endpoints
    CARDEKHO_BASE_URL: str = "https://www.cardekho.com"

    # Naukri Base URLs & Endpoints
    NAUKRI_BASE_URL: str = "https://www.naukri.com"

    # Cashify Base URLs & Endpoints
    CASHIFY_BASE_URL: str = "https://www.cashify.in"

settings = Settings()

