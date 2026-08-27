import asyncio
import logging
from typing import Optional
from playwright.sync_api import sync_playwright
from playwright_stealth import Stealth
from extractor.config import settings
from extractor.utils.user_agents import get_random_user_agent

logger = logging.getLogger(__name__)

def _fetch_page_sync(url: str, wait_selector: Optional[str] = None, wait_ms: int = 3500) -> Optional[str]:
    stealth = Stealth()
    try:
        with sync_playwright() as p:
            args = [
                "--disable-blink-features=AutomationControlled",
                "--no-sandbox",
                "--disable-setuid-sandbox",
                "--disable-dev-shm-usage",
                "--disable-infobars",
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
            
            context = browser.new_context(
                user_agent=user_agent,
                viewport={"width": 1920, "height": 1080},
                locale="en-US",
                timezone_id="Asia/Kolkata",
                proxy=proxy,
            )
            page = context.new_page()
            stealth.apply_stealth_sync(page)

            # Block heavy media resources for speed while keeping necessary scripts
            page.route("**/*", lambda route: (
                route.abort() if route.request.resource_type in ["image", "media", "font"]
                else route.continue_()
            ))

            page.goto(url, wait_until="domcontentloaded", timeout=settings.DEFAULT_TIMEOUT * 1000)

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
