import logging
from typing import Optional, Dict, Any
from curl_cffi.requests import AsyncSession, Response
from extractor.config import settings
from extractor.utils.user_agents import get_browser_headers

logger = logging.getLogger(__name__)

class HttpClient:
    def __init__(self):
        self.timeout = settings.DEFAULT_TIMEOUT
        self.proxy = settings.PROXY_URL if settings.PROXY_URL else None

    async def get(self, url: str, params: Optional[Dict[str, Any]] = None, headers: Optional[Dict[str, str]] = None) -> Optional[Response]:
        req_headers = get_browser_headers()
        if headers:
            req_headers.update(headers)

        proxies = {"http": self.proxy, "https": self.proxy} if self.proxy else None

        for attempt in range(1, settings.MAX_RETRIES + 1):
            try:
                async with AsyncSession(impersonate="chrome120") as session:
                    response = await session.get(
                        url,
                        params=params,
                        headers=req_headers,
                        timeout=self.timeout,
                        proxies=proxies,
                    )
                    if response.status_code == 200:
                        return response
                    elif response.status_code in (403, 429, 503):
                        logger.warning(f"HTTP {response.status_code} received from {url} on attempt {attempt}/{settings.MAX_RETRIES}")
                    else:
                        logger.warning(f"Unexpected status code {response.status_code} from {url}")
                        return response
            except Exception as e:
                logger.error(f"HTTP request error for {url} on attempt {attempt}: {str(e)}")
        return None

http_client = HttpClient()
