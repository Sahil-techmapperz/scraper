import json
import re
import logging
from typing import Optional
from selectolax.parser import HTMLParser

from extractor.config import settings
from extractor.models.response import DetailResponse, ListingItem
from extractor.engine.http_client import http_client
from extractor.engine.browser_pool import browser_manager
from extractor.extractors.olx_search import olx_search_extractor

logger = logging.getLogger(__name__)

class OlxDetailExtractor:
    async def get_detail(self, listing_id: str, listing_url: Optional[str] = None) -> DetailResponse:
        url = listing_url
        if not url:
            clean_id = re.sub(r"[^\d]", "", listing_id)
            url = f"{settings.OLX_IN_BASE_URL}/item/iid-{clean_id}"

        logger.info(f"Extracting OLX Detail URL: {url}")

        html_content = None
        response = await http_client.get(url)
        if response and response.status_code == 200:
            html_content = response.text

        # Fallback to Playwright if needed
        if not html_content or ("window.__APP" not in html_content and "__NEXT_DATA__" not in html_content):
            if settings.ENABLE_BROWSER_FALLBACK:
                logger.info(f"Triggering Playwright fallback for detail URL: {url}")
                html_content = await browser_manager.fetch_page_content(url)

        if not html_content:
            logger.error(f"Failed to fetch content for detail URL: {url}")
            return DetailResponse(data=None)

        return self.parse_detail_html(html_content, listing_id, url)

    def parse_detail_html(self, html_content: str, listing_id: str, url: str) -> DetailResponse:
        parser = HTMLParser(html_content)

        # 1. Primary: Extract from window.__APP state
        for s in parser.css("script"):
            text = s.text()
            if "window.__APP" in text and "states" in text:
                states_match = re.search(r"states:\s*(\{.+?\})(?:,\s*[a-zA-Z0-9_]+:|\s*\};)", text, re.DOTALL)
                if not states_match:
                    continue
                try:
                    states = json.loads(states_match.group(1))
                    
                    # Check items.elements
                    elements = states.get("items", {}).get("elements", {})
                    if isinstance(elements, dict):
                        clean_id = re.sub(r"[^\d]", "", listing_id)
                        if clean_id in elements:
                            normalized = olx_search_extractor.normalize_raw_item(elements[clean_id])
                            if normalized:
                                return DetailResponse(data=normalized)
                        elif elements:
                            # If only one item is on page or first item matches
                            first_item = next(iter(elements.values()))
                            normalized = olx_search_extractor.normalize_raw_item(first_item)
                            if normalized:
                                return DetailResponse(data=normalized)

                    # Check adpv / entities
                    ads_dict = states.get("entities", {}).get("ads", {}).get("byId", {})
                    if isinstance(ads_dict, dict) and ads_dict:
                        first_ad = next(iter(ads_dict.values()))
                        normalized = olx_search_extractor.normalize_raw_item(first_ad)
                        if normalized:
                            return DetailResponse(data=normalized)
                except Exception as e:
                    logger.warning(f"Failed parsing detail window.__APP state: {str(e)}")

        # 2. Secondary: Extract from Next.js state
        next_node = parser.css_first("script#__NEXT_DATA__")
        if next_node:
            try:
                data = json.loads(next_node.text())
                page_props = data.get("props", {}).get("pageProps", {})
                item_data = page_props.get("item") or page_props.get("ad") or page_props.get("data")
                if isinstance(item_data, dict):
                    normalized = olx_search_extractor.normalize_raw_item(item_data)
                    if normalized:
                        return DetailResponse(data=normalized)
            except Exception as e:
                logger.warning(f"Failed parsing detail __NEXT_DATA__: {str(e)}")

        # 3. DOM Fallback
        title_node = parser.css_first("h1, [data-aut-id='itemTitle']")
        desc_node = parser.css_first("[data-aut-id='itemDescription'], .itemDescription, p")
        price_node = parser.css_first("[data-aut-id='itemPrice'], span[class*='price']")

        title = title_node.text(strip=True) if title_node else None
        description = desc_node.text(strip=True) if desc_node else None

        if title or description:
            item = ListingItem(
                id=listing_id,
                title=title,
                description=description,
                url=url,
            )
            return DetailResponse(data=item)

        return DetailResponse(data=None)

olx_detail_extractor = OlxDetailExtractor()
