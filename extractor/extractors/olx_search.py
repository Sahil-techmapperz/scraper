import json
import re
import math
import asyncio
import logging
from typing import Optional, Dict, Any, List
from urllib.parse import urlencode, quote
from selectolax.parser import HTMLParser

from extractor.config import settings
from extractor.models.request import SearchQueryParams
from extractor.models.response import (
    SearchResponse,
    ListingItem,
    PriceModel,
    LocationModel,
    SellerModel,
    ImageModel,
    ParameterModel,
    PaginationModel,
)
from extractor.engine.http_client import http_client
from extractor.engine.browser_pool import browser_manager

logger = logging.getLogger(__name__)

CITY_SLUGS = {
    "kolkata": "kolkata_g4058877",
    "mumbai": "mumbai_g4058997",
    "delhi": "delhi_g4058659",
    "bangalore": "bangalore_g4058803",
    "bengaluru": "bangalore_g4058803",
    "hyderabad": "hyderabad_g4058526",
    "chennai": "chennai_g4059162",
    "pune": "pune_g4059014",
    "ahmedabad": "ahmedabad_g4058499",
    "jaipur": "jaipur_g4059121",
    "lucknow": "lucknow_g4059187",
    "chandigarh": "chandigarh_g4058580",
    "kochi": "kochi_g4058872",
    "kozhikode": "kozhikode_g4058878",
    "indore": "indore_g4058941",
    "patna": "patna_g4058561",
    "bhopal": "bhopal_g4058933",
    "nagpur": "nagpur_g4059002",
    "visakhapatnam": "visakhapatnam_g4058546",
    "surat": "surat_g4058514",
}

CATEGORY_SLUGS = {
    "cars": "cars_c84",
    "automobile": "cars_c84",
    "bikes": "motorcycles_c81",
    "motorcycles": "motorcycles_c81",
    "scooters": "scooters_c1413",
    "mobile-phones": "mobile-phones_c1453",
    "mobiles": "mobile-phones_c1453",
    "phones": "mobile-phones_c1453",
    "real-estate": "for-sale-houses-apartments_c1725",
    "properties": "for-sale-houses-apartments_c1725",
    "apartments": "for-sale-houses-apartments_c1725",
    "commercial-property": "commercial-office-space_c1737",
    "land-plots": "land-plots_c1729",
}

class OlxSearchExtractor:
    def build_url(self, params: SearchQueryParams, page_override: Optional[int] = None) -> str:
        base = settings.OLX_IN_BASE_URL
        page_num = page_override or params.page

        # If free-text keyword search without category/city
        if params.keyword and not params.category and not params.city:
            query = {"q": params.keyword}
            if page_num > 1:
                query["page"] = page_num
            return f"{base}/all-results?{urlencode(query)}"

        # Resolve location slug
        location_slug = "india"
        if params.city:
            clean_city = params.city.strip().lower()
            location_slug = CITY_SLUGS.get(clean_city, clean_city.replace(" ", "-"))

        # Resolve category slug
        category_slug = ""
        cat_key = (params.subcategory or params.category or "").strip().lower()
        if cat_key:
            category_slug = CATEGORY_SLUGS.get(cat_key, cat_key.replace(" ", "-"))

        url_path = f"{base}/{location_slug}"
        if category_slug:
            url_path += f"/{category_slug}"

        query_dict: Dict[str, Any] = {}
        if params.keyword:
            query_dict["q"] = params.keyword
        if page_num > 1:
            query_dict["page"] = page_num

        if params.sort:
            sort_map = {
                "newest": "created_at:desc",
                "oldest": "created_at:asc",
                "price_low_to_high": "price:asc",
                "price_high_to_low": "price:desc",
            }
            if params.sort in sort_map:
                query_dict["sorting"] = sort_map[params.sort]

        if query_dict:
            return f"{url_path}?{urlencode(query_dict)}"
        return url_path

    async def fetch_single_page(self, url: str) -> List[ListingItem]:
        html_content = None
        response = await http_client.get(url)
        if response and response.status_code == 200:
            html_content = response.text

        if not html_content or ("window.__APP" not in html_content and "__NEXT_DATA__" not in html_content):
            if settings.ENABLE_BROWSER_FALLBACK:
                html_content = await browser_manager.fetch_page_content(url)

        if not html_content:
            return []

        # 1. Primary: window.__APP state
        items = self._extract_items_from_window_app(html_content)
        # 2. Secondary: Next.js state
        if not items:
            items = self._extract_items_from_next_data(html_content)
        # 3. DOM fallback
        if not items:
            parser = HTMLParser(html_content)
            items = self._extract_items_from_dom(parser)

        return items

    async def search(self, params: SearchQueryParams) -> SearchResponse:
        # Determine number of pages to fetch (40 items per page on OLX)
        # For limit=300: ceil(300 / 40) = 8 pages
        limit = min(300, max(1, params.limit))
        pages_needed = math.ceil(limit / 40)
        start_page = max(1, params.page)
        
        urls = [self.build_url(params, page_override=p) for p in range(start_page, start_page + pages_needed)]
        logger.info(f"Extracting {len(urls)} pages for limit={limit}: {urls[0]}")

        # Fetch all pages concurrently
        results = await asyncio.gather(*[self.fetch_single_page(u) for u in urls], return_exceptions=True)

        all_items: List[ListingItem] = []
        seen_ids = set()

        for page_result in results:
            if isinstance(page_result, list):
                for item in page_result:
                    if item.id and item.id not in seen_ids:
                        seen_ids.add(item.id)
                        all_items.append(item)

        # In-memory filter if price/keyword constraints are passed
        filtered = self._filter_items(all_items, params)
        paged_items = filtered[:limit]

        return SearchResponse(
            data=paged_items,
            pagination=PaginationModel(
                page=params.page,
                limit=limit,
                has_next=len(filtered) >= limit,
                total_records=len(filtered),
            ),
        )

    def _extract_items_from_window_app(self, html_content: str) -> List[ListingItem]:
        items: List[ListingItem] = []
        parser = HTMLParser(html_content)

        for s in parser.css("script"):
            text = s.text()
            if "window.__APP" in text and "states" in text:
                states_match = re.search(r"states:\s*(\{.+?\})(?:,\s*[a-zA-Z0-9_]+:|\s*\};)", text, re.DOTALL)
                if not states_match:
                    continue
                try:
                    states = json.loads(states_match.group(1))
                    elements = states.get("items", {}).get("elements", {})
                    element_list = list(elements.values()) if isinstance(elements, dict) else elements

                    if not element_list:
                        ads_dict = states.get("entities", {}).get("ads", {}).get("byId", {})
                        element_list = list(ads_dict.values()) if isinstance(ads_dict, dict) else []

                    for row in element_list:
                        if isinstance(row, dict):
                            item = self.normalize_raw_item(row)
                            if item:
                                items.append(item)
                    if items:
                        return items
                except Exception as e:
                    logger.warning(f"Failed parsing window.__APP state: {str(e)}")
        return items

    def _extract_items_from_next_data(self, html_content: str) -> List[ListingItem]:
        items: List[ListingItem] = []
        parser = HTMLParser(html_content)
        next_node = parser.css_first("script#__NEXT_DATA__")
        if not next_node:
            return []

        try:
            data = json.loads(next_node.text())
            page_props = data.get("props", {}).get("pageProps", {})
            raw_list = page_props.get("data") or page_props.get("ads") or page_props.get("items")
            if isinstance(raw_list, list):
                for row in raw_list:
                    if isinstance(row, dict):
                        item = self.normalize_raw_item(row)
                        if item:
                            items.append(item)
        except Exception as e:
            logger.warning(f"Failed parsing __NEXT_DATA__: {str(e)}")
        return items

    def normalize_raw_item(self, row: dict) -> Optional[ListingItem]:
        item_id = str(row.get("id") or row.get("listing_id") or row.get("ad_id") or "")
        if not item_id:
            return None

        title = row.get("title") or row.get("subject") or row.get("name")
        description = row.get("description") or row.get("body")

        # Price parsing
        price_val = None
        raw_price = row.get("price")
        if isinstance(raw_price, dict):
            val_obj = raw_price.get("value")
            if isinstance(val_obj, dict):
                price_val = val_obj.get("raw")
            elif isinstance(val_obj, (int, float)):
                price_val = int(val_obj)
            else:
                price_val = raw_price.get("amount")
        elif isinstance(raw_price, (int, float)):
            price_val = int(raw_price)
        elif isinstance(raw_price, str):
            clean_digits = re.sub(r"[^\d]", "", raw_price)
            if clean_digits:
                price_val = int(clean_digits)

        # Locations
        loc_res = row.get("locations_resolved") or row.get("location") or {}
        state = loc_res.get("ADMIN_LEVEL_1_name") or loc_res.get("state") or loc_res.get("state_name")
        city = loc_res.get("ADMIN_LEVEL_3_name") or loc_res.get("city") or loc_res.get("city_name")
        locality = loc_res.get("SUBLOCALITY_LEVEL_1_name") or loc_res.get("locality") or loc_res.get("neighborhood")
        pincode = loc_res.get("pincode")

        # Images
        images = []
        raw_images = row.get("images") or row.get("photos") or []
        for idx, img in enumerate(raw_images):
            if isinstance(img, dict) and img.get("url"):
                images.append(ImageModel(url=img["url"], position=idx))
            elif isinstance(img, str):
                images.append(ImageModel(url=img, position=idx))

        # Seller
        user = row.get("user") or row.get("seller") or {}
        seller_name = user.get("name") or row.get("seller_name")
        seller_type = user.get("type") or row.get("user_type") or "owner"

        # Parameters
        parameters = []
        raw_params = row.get("parameters") or row.get("attributes") or []
        if isinstance(raw_params, list):
            for p in raw_params:
                if isinstance(p, dict) and "key" in p:
                    val = p.get("formatted_value") or p.get("value_name") or p.get("value")
                    parameters.append(ParameterModel(key=str(p["key"]), value=val))

        # Category
        cat_data = row.get("category")
        if isinstance(cat_data, dict):
            category = {"slug": cat_data.get("slug"), "name": cat_data.get("name")}
        elif isinstance(cat_data, str):
            category = {"slug": cat_data, "name": cat_data.replace("-", " ").title()}
        else:
            category = None

        return ListingItem(
            id=item_id,
            title=title,
            description=description,
            url=row.get("url") or row.get("share_url") or f"{settings.OLX_IN_BASE_URL}/item/iid-{item_id}",
            price=PriceModel(value=price_val, currency="INR"),
            category=category,
            location=LocationModel(state=state, city=city, locality=locality, pincode=pincode),
            seller=SellerModel(name=seller_name, type=seller_type, contact_available=bool(user)),
            images=images,
            created_at=row.get("created_at_first") or row.get("created_at") or row.get("date"),
            updated_at=row.get("updated_at"),
            parameters=parameters,
            raw_data=row,
        )

    def _extract_items_from_dom(self, parser: HTMLParser) -> List[ListingItem]:
        items: List[ListingItem] = []
        nodes = parser.css('li[data-aut-id="itemBox"], article, div[data-aut-id="itemBox"]')
        for idx, node in enumerate(nodes):
            link = node.css_first("a")
            href = link.attributes.get("href", "") if link else ""
            title_node = node.css_first('span[data-aut-id="itemTitle"], h2, [data-aut-id="itemTitle"]')
            price_node = node.css_first('span[data-aut-id="itemPrice"], [data-aut-id="itemPrice"]')
            loc_node = node.css_first('span[data-aut-id="item-location"], [data-aut-id="itemLocation"]')
            img_node = node.css_first("img")

            title = title_node.text(strip=True) if title_node else None
            price_str = price_node.text(strip=True) if price_node else None
            price_val = int(re.sub(r"[^\d]", "", price_str)) if price_str and re.sub(r"[^\d]", "", price_str) else None
            img_url = img_node.attributes.get("src") or img_node.attributes.get("data-src") if img_node else None

            item_id = ""
            if href:
                id_match = re.search(r"iid-(\d+)", href) or re.search(r"/item/.*?(\d+)$", href)
                if id_match:
                    item_id = id_match.group(1)
            if not item_id:
                item_id = f"dom_item_{idx+1}"

            items.append(ListingItem(
                id=item_id,
                title=title,
                url=f"{settings.OLX_IN_BASE_URL}{href}" if href.startswith("/") else href,
                price=PriceModel(value=price_val, currency="INR"),
                location=LocationModel(locality=loc_node.text(strip=True) if loc_node else None),
                images=[ImageModel(url=img_url)] if img_url else [],
            ))
        return items

    def _filter_items(self, items: List[ListingItem], params: SearchQueryParams) -> List[ListingItem]:
        filtered = []
        for item in items:
            if params.min_price is not None:
                if not item.price or item.price.value is None or item.price.value < params.min_price:
                    continue
            if params.max_price is not None:
                if not item.price or item.price.value is None or item.price.value > params.max_price:
                    continue
            if params.keyword:
                kw = params.keyword.lower()
                title_match = item.title and kw in item.title.lower()
                desc_match = item.description and kw in item.description.lower()
                if not title_match and not desc_match:
                    continue
            filtered.append(item)

        if params.sort:
            if params.sort == "price_low_to_high":
                filtered.sort(key=lambda x: (x.price is None or x.price.value is None, x.price.value if (x.price and x.price.value) else 0))
            elif params.sort == "price_high_to_low":
                filtered.sort(key=lambda x: (x.price is None or x.price.value is None, -(x.price.value if (x.price and x.price.value) else 0)))
            elif params.sort == "newest":
                filtered.sort(key=lambda x: str(x.created_at or x.updated_at or ""), reverse=True)
            elif params.sort == "oldest":
                filtered.sort(key=lambda x: (x.created_at is None and x.updated_at is None, str(x.created_at or x.updated_at or "")))

        return filtered

olx_search_extractor = OlxSearchExtractor()
