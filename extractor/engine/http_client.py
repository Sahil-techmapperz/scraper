import time
import logging
from typing import Optional, Dict, Any, Tuple
from curl_cffi.requests import AsyncSession, Response
from extractor.config import settings
from extractor.utils.user_agents import get_browser_headers

logger = logging.getLogger(__name__)

class SimpleTTLResponseCache:
    """High-speed in-memory TTL cache for repeat search queries."""
    def __init__(self, ttl: int = 60, max_size: int = 150):
        self.ttl = ttl
        self.max_size = max_size
        self._cache: Dict[str, Tuple[float, Response]] = {}

    def get(self, key: str) -> Optional[Response]:
        item = self._cache.get(key)
        if not item:
            return None
        timestamp, response = item
        if time.time() - timestamp < self.ttl:
            return response
        del self._cache[key]
        return None

    def set(self, key: str, response: Response):
        if len(self._cache) >= self.max_size:
            # Evict oldest 25% of entries to keep memory low
            sorted_keys = sorted(self._cache.keys(), key=lambda k: self._cache[k][0])
            for k in sorted_keys[: len(sorted_keys) // 4]:
                del self._cache[k]
        self._cache[key] = (time.time(), response)

    def clear(self):
        self._cache.clear()

class HttpClient:
    def __init__(self):
        self.timeout = settings.DEFAULT_TIMEOUT
        self.proxy = settings.PROXY_URL if settings.PROXY_URL else None
        self._session: Optional[AsyncSession] = None
        self._cache = SimpleTTLResponseCache(ttl=settings.CACHE_TTL_SECONDS)

    async def get_session(self) -> AsyncSession:
        """Returns or initializes a persistent Keep-Alive session for fast HTTP/2 & TLS reuse."""
        if self._session is None:
            self._session = AsyncSession(impersonate="chrome120")
        return self._session

    async def close(self):
        """Cleanly closes the persistent session."""
        if self._session is not None:
            try:
                await self._session.close()
            except Exception:
                pass
            self._session = None

    async def get(
        self,
        url: str,
        params: Optional[Dict[str, Any]] = None,
        headers: Optional[Dict[str, str]] = None,
        use_cache: bool = True
    ) -> Optional[Response]:
        # 1. Fast cache check (returns in < 1ms for repeat queries)
        cache_key = f"{url}?{sorted(params.items()) if params else ''}"
        if use_cache and settings.ENABLE_HTTP_CACHE:
            cached = self._cache.get(cache_key)
            if cached is not None:
                logger.debug(f"Cache hit for {url}")
                return cached

        req_headers = get_browser_headers()
        if headers:
            req_headers.update(headers)

        proxy = self.proxy if self.proxy else None

        for attempt in range(1, settings.MAX_RETRIES + 1):
            try:
                session = await self.get_session()
                response = await session.get(
                    url,
                    params=params,
                    headers=req_headers,
                    timeout=self.timeout,
                    proxy=proxy,
                )
                if response.status_code == 200:
                    if use_cache and settings.ENABLE_HTTP_CACHE:
                        self._cache.set(cache_key, response)
                    return response
                elif response.status_code in (403, 429, 503):
                    logger.warning(f"HTTP {response.status_code} received from {url} on attempt {attempt}/{settings.MAX_RETRIES}")
                else:
                    logger.warning(f"Unexpected status code {response.status_code} from {url}")
                    return response
            except Exception as e:
                logger.error(f"HTTP request error for {url} on attempt {attempt}: {str(e)}")
                # Reset session on transport failure to re-establish a fresh connection on next retry
                await self.close()

        return None

http_client = HttpClient()
