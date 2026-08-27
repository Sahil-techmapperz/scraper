import asyncio
import logging
from typing import Optional
from playwright.sync_api import sync_playwright
from playwright_stealth import Stealth
from extractor.config import settings
from extractor.utils.user_agents import get_random_user_agent

logger = logging.getLogger(__name__)

def _fetch_page_sync(url: str, wait_selector: Optional[str] = None, wait_ms: int = 4500) -> Optional[str]:
    stealth = Stealth()
    try:
        with sync_playwright() as p:
            args = [
                "--disable-blink-features=AutomationControlled",
                "--no-sandbox",
                "--disable-setuid-sandbox",
                "--disable-dev-shm-usage",
                "--disable-infobars",
                "--window-size=1920,1080",
            ]
            
            browser = None
            for channel_name in ["chrome", "msedge", None]:
                try:
                    kwargs = {
                        "headless": settings.HEADLESS_BROWSER,
                        "args": args,
                    }
                    if channel_name:
                        kwargs["channel"] = channel_name
                    browser = p.chromium.launch(**kwargs)
                    logger.debug(f"Launched browser using channel='{channel_name}'")
                    break
                except Exception as e:
                    logger.debug(f"Could not launch browser with channel='{channel_name}': {e}")
                    
            if not browser:
                browser = p.chromium.launch(
                    headless=settings.HEADLESS_BROWSER,
                    args=args,
                )

            user_agent = get_random_user_agent()
            proxy = {"server": settings.PROXY_URL} if settings.PROXY_URL else None
            
            extra_headers = {
                "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8",
                "Accept-Language": "en-US,en;q=0.9,hi;q=0.8",
                "Sec-Ch-Ua": '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
                "Sec-Ch-Ua-Mobile": "?0",
                "Sec-Ch-Ua-Platform": '"Windows"',
                "Sec-Fetch-Dest": "document",
                "Sec-Fetch-Mode": "navigate",
                "Sec-Fetch-Site": "none",
                "Sec-Fetch-User": "?1",
                "Upgrade-Insecure-Requests": "1",
            }
            
            context = browser.new_context(
                user_agent=user_agent,
                viewport={"width": 1920, "height": 1080},
                locale="en-US",
                timezone_id="Asia/Kolkata",
                extra_http_headers=extra_headers,
                proxy=proxy,
            )
            page = context.new_page()
            stealth.apply_stealth_sync(page)

            page.goto(url, wait_until="load", timeout=settings.DEFAULT_TIMEOUT * 1000)
            # Give JS frameworks (Next.js/React) time to hydrate and render content
            try:
                page.wait_for_load_state("networkidle", timeout=8000)
            except Exception:
                pass  # networkidle timeout is OK, page still usable

            if wait_selector:
                try:
                    page.wait_for_selector(wait_selector, timeout=6000)
                except Exception:
                    pass

            if wait_ms > 0:
                page.wait_for_timeout(wait_ms)

            content = page.content()
            context.close()
            browser.close()
            return content
    except Exception as e:
        logger.error(f"Browser error fetching {url}: {str(e)}")
        return None

class BrowserManager:
    @classmethod
    async def fetch_page_content(cls, url: str, wait_selector: Optional[str] = None, wait_ms: int = 3500) -> Optional[str]:
        return await asyncio.to_thread(_fetch_page_sync, url, wait_selector, wait_ms)

    @classmethod
    async def close(cls):
        pass

browser_manager = BrowserManager
