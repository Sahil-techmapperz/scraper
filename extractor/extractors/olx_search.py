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
    # 1. Cars (parent: 5, child: 84)
    "cars":                  "cars_c84",
    "automobile":            "cars_c84",
    "car":                   "cars_c84",

    # 2. Bikes (parent: 2198, children: 81, 1413, 1415)
    "bikes":                 "motorcycles_c81",
    "motorcycles":           "motorcycles_c81",
    "bike":                  "motorcycles_c81",
    "scooters":              "scooters_c1413",
    "scooter":               "scooters_c1413",
    "bicycles":              "bicycles_c1415",
    "bicycle":               "bicycles_c1415",

    # 3. Properties (parent: 3, children: 1725, 1723, 1729, 301, 1731, 1733, 1449)
    "properties":            "properties_c3",
    "property":              "properties_c3",
    "real-estate":           "properties_c3",
    "for-sale-houses":       "for-sale-houses-apartments_c1725",
    "apartments":            "for-sale-houses-apartments_c1725",
    "for-rent-houses":       "for-rent-houses-apartments_c1723",
    "rent":                  "for-rent-houses-apartments_c1723",
    "lands-plots":           "lands-plots_c1729",
    "land":                  "lands-plots_c1729",
    "plots":                 "lands-plots_c1729",
    "pg-guest-houses":       "pg-guest-houses_c1449",

    # 4. Electronics & Appliances (parent: 99, children: 1523, 1417, 1505, 1517, 93, 1617, 1619, 1615)
    "electronics":           "electronics-appliances_c99",
    "appliances":            "electronics-appliances_c99",
    "electronics-appliances":"electronics-appliances_c99",
    "tv":                    "tvs-video-audio_c1523",
    "tvs":                   "tvs-video-audio_c1523",
    "audio":                 "tvs-video-audio_c1523",
    "kitchen-appliances":    "kitchen-other-appliances_c1417",
    "computers":             "computers-laptops_c1505",
    "laptops":               "computers-laptops_c1505",
    "cameras":               "cameras-lenses_c1517",
    "gaming":                "games-entertainment_c93",
    "fridges":               "fridges_c1617",
    "refrigerators":         "fridges_c1617",
    "ac":                    "acs_c1619",
    "acs":                   "acs_c1619",
    "washing-machines":      "washing-machines_c1615",

    # 5. Mobiles (parent: 1411, children: 1453, 1457, 1455)
    "mobiles":               "mobiles_c1411",
    "mobile":                "mobiles_c1411",
    "mobile-phones":         "mobile-phones_c1453",
    "phones":                "mobile-phones_c1453",
    "phone":                 "mobile-phones_c1453",
    "mobile-accessories":    "accessories_c1457",
    "tablets":               "tablets_c1455",
    "tablet":                "tablets_c1455",

    # 6. Jobs (parent: 4, children: 1737, 62, 164, 2206, 2201, 2205, 53, 2202, 2204, 731, 56, 1439, 1441, 401, 2203, 411, 65)
    "jobs":                  "jobs_c4",
    "job":                   "jobs_c4",
    "employment":            "jobs_c4",
    "hiring":                "jobs_c4",
    "data-entry":            "data-entry-back-office_c1737",
    "sales":                 "sales-marketing_c62",
    "bpo":                   "bpo-telecaller_c164",
    "driver":                "driver_c2206",
    "delivery":              "delivery-collection_c2205",
    "teacher":               "teacher_c53",
    "developer":             "it-engineer-developer_c56",
    "accountant":            "accountant_c1441",

    # 7. Furniture (parent: 628, children: 1593, 1591, 575, 231, 293)
    "furniture":             "furniture_c628",
    "sofa":                  "sofa-dining_c1593",
    "dining":                "sofa-dining_c1593",
    "beds":                  "beds-wardrobes_c1591",
    "wardrobes":             "beds-wardrobes_c1591",
    "home-decor":            "home-decor-garden_c575",
    "decor":                 "home-decor-garden_c575",

    # 8. Fashion (parent: 87, children: 1793, 1795, 235)
    "fashion":               "fashion_c87",
    "clothes":               "fashion_c87",
    "clothing":              "fashion_c87",
    "men-fashion":           "men_c1793",
    "women-fashion":         "women_c1795",
    "kids-fashion":          "kids_c235",

    # 9. Pets (parent: 103, children: 1293, 175, 139, 140)
    "pets":                  "pets_c103",
    "pet":                   "pets_c103",
    "dogs":                  "dogs_c139",
    "dog":                   "dogs_c139",
    "fishes":                "fishes-aquarium_c1293",
    "aquarium":              "fishes-aquarium_c1293",
    "pet-food":              "pet-food-accessories_c175",

    # 10. Services (parent: 619, children: 1429, 356, 523, 741, 1301, 1302, 1303, 1304, 625)
    "services":              "services_c619",
    "service":               "services_c619",
    "packers-movers":        "packers-movers_c1304",
    "repairs":               "electronics-repair-services_c523",
    "renovation":            "home-renovation-repair_c1301",
    "cleaning":              "cleaning-pest-control_c1302",
    "legal":                 "legal-documentation-services_c1303",
    "education":             "education-classes_c1429",
}

# Categories whose IDs are confirmed to work with the OLX JSON API
API_SUPPORTED_CATEGORIES = {
    # Parents
    "5", "2198", "3", "99", "1411", "4", "628", "87", "103", "619",
    # Children
    "84", "81", "1413", "1415", "1725", "1723", "1729", "301", "1731", "1733", "1449",
    "1523", "1417", "1505", "1517", "93", "1617", "1515", "1509", "1619", "1615",
    "1453", "1457", "1455",
    "1737", "62", "164", "2206", "2201", "2205", "53", "2202", "2204", "731", "56", "1439", "1441", "401", "2203", "411", "65",
    "1593", "1591", "575", "231", "293",
    "1793", "1795", "235",
    "1293", "175", "139", "140",
    "1429", "356", "523", "741", "1301", "1302", "1303", "1304", "625",
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

    def build_api_url(self, params: SearchQueryParams, page_override: Optional[int] = None, category_id_override: Optional[str] = None) -> str:
        api_base = f"{settings.OLX_IN_BASE_URL.rstrip('/')}/api/relevance/v4/search"
        query_dict: Dict[str, Any] = {
            "facet_limit": 100,
            "size": 40,
        }
        page_num = (page_override or params.page) - 1
        if page_num > 0:
            query_dict["page"] = page_num

        if params.city:
            clean_city = params.city.strip().lower()
            slug = CITY_SLUGS.get(clean_city, "")
            m = re.search(r"_g(\d+)", slug)
            if m:
                query_dict["location"] = m.group(1)

        if category_id_override:
            query_dict["category"] = category_id_override
        else:
            cat_key = (params.subcategory or params.category or "").strip().lower()
            if cat_key:
                c_slug = CATEGORY_SLUGS.get(cat_key, "")
                m = re.search(r"_c(\d+)", c_slug)
                if m:
                    query_dict["category"] = m.group(1)

        if params.keyword:
            query_dict["query"] = params.keyword

        if params.min_price is not None:
            query_dict["price_min"] = params.min_price
        if params.max_price is not None:
            query_dict["price_max"] = params.max_price

        if params.sort:
            sort_map = {
                "newest": "created_at:desc",
                "oldest": "created_at:asc",
                "price_low_to_high": "price:asc",
                "price_high_to_low": "price:desc",
            }
            if params.sort in sort_map:
                query_dict["sorting"] = sort_map[params.sort]

        return f"{api_base}?{urlencode(query_dict)}"

    async def fetch_api_page(self, api_url: str) -> List[ListingItem]:
        headers = {
            "Accept": "application/json, text/plain, */*",
            "Referer": "https://www.olx.in/",
            "Origin": "https://www.olx.in",
        }
        response = await http_client.get(api_url, headers=headers)
        if response and response.status_code == 200:
            try:
                json_data = response.json()
                raw_list = json_data.get("data")
                if isinstance(raw_list, list) and raw_list:
                    items = []
                    for row in raw_list:
                        if isinstance(row, dict):
                            item = self.normalize_raw_item(row)
                            if item:
                                items.append(item)
                    if items:
                        logger.info(f"OLX API returned {len(items)} listings")
                        return items
            except Exception as e:
                logger.debug(f"Error parsing OLX API response: {e}")
        return []

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
        limit = min(300, max(1, params.limit))
        pages_needed = math.ceil(limit / 40)
        start_page = max(1, params.page)

        all_items: List[ListingItem] = []
        seen_ids = set()

        # Resolve whether this category is supported by the OLX JSON API
        cat_key = (params.subcategory or params.category or "").strip().lower()
        c_slug = CATEGORY_SLUGS.get(cat_key, "")
        m = re.search(r"_c(\d+)", c_slug)
        cat_id_str = m.group(1) if m else ""
        use_api = not cat_key or cat_id_str in API_SUPPORTED_CATEGORIES

        # Check if this is the combined 2-in-1 "automobile" category (both Cars and Bikes)
        is_combined_automobile = cat_key in ("automobile", "automobiles", "vehicles") and not params.subcategory

        if is_combined_automobile:
            # 2 in 1 Automobile: fetch Cars (id: 84) and Bikes (id: 81) concurrently in parallel!
            half_pages = max(1, math.ceil((limit / 2) / 40))
            car_urls = [self.build_api_url(params, page_override=p, category_id_override="84") for p in range(start_page, start_page + half_pages)]
            bike_urls = [self.build_api_url(params, page_override=p, category_id_override="81") for p in range(start_page, start_page + half_pages)]

            logger.info(f"Extracting combined Automobile (Cars + Bikes) {len(car_urls) + len(bike_urls)} page(s) for limit={limit}")
            all_api_urls = car_urls + bike_urls
            results = await asyncio.gather(*[self.fetch_api_page(u) for u in all_api_urls], return_exceptions=True)

            car_items: List[ListingItem] = []
            bike_items: List[ListingItem] = []
            for idx, res in enumerate(results):
                if isinstance(res, list):
                    target = car_items if idx < len(car_urls) else bike_items
                    for item in res:
                        if item.id and item.id not in seen_ids:
                            seen_ids.add(item.id)
                            target.append(item)

            # Interleave car and bike listings (Car 1, Bike 1, Car 2, Bike 2...)
            interleaved: List[ListingItem] = []
            max_len = max(len(car_items), len(bike_items))
            for i in range(max_len):
                if i < len(car_items):
                    interleaved.append(car_items[i])
                if i < len(bike_items):
                    interleaved.append(bike_items[i])
            all_items = interleaved

        elif use_api:
            # 1. Fast path: OLX JSON API (works for single category: Mobiles, Properties, Jobs, etc.)
            api_urls = [self.build_api_url(params, page_override=p) for p in range(start_page, start_page + pages_needed)]
            logger.info(f"Extracting OLX API {len(api_urls)} page(s) for limit={limit}: {api_urls[0]}")
            api_results = await asyncio.gather(*[self.fetch_api_page(u) for u in api_urls], return_exceptions=True)

            for res in api_results:
                if isinstance(res, list):
                    for item in res:
                        if item.id and item.id not in seen_ids:
                            seen_ids.add(item.id)
                            all_items.append(item)

        # 2. URL scraping: for categories not supported by API (Electronics, Furniture, Fashion, Pets, Services)
        #    Also used as fallback if API returned nothing
        if not all_items:
            urls = [self.build_url(params, page_override=p) for p in range(start_page, start_page + pages_needed)]
            logger.info(f"{'Falling back to' if use_api else 'Using'} OLX web extraction: {urls[0]}")

            results = await asyncio.gather(*[self.fetch_single_page(u) for u in urls], return_exceptions=True)
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
