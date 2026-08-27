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
    "delhi": "delhi-ncr",
    "delhi ncr": "delhi-ncr",
    "delhi-ncr": "delhi-ncr",
    "noida": "delhi-ncr",
    "gurgaon": "delhi-ncr",
    "gurugram": "delhi-ncr",
    "faridabad": "delhi-ncr",
    "ghaziabad": "delhi-ncr",
    "mumbai": "mumbai",
    "navi mumbai": "mumbai",
    "thane": "mumbai",
    "bangalore": "bangalore",
    "bengaluru": "bangalore",
    "hyderabad": "hyderabad",
    "chennai": "chennai",
    "kolkata": "kolkata",
    "calcutta": "kolkata",
    "pune": "pune",
    "ahmedabad": "ahmedabad",
    "jaipur": "jaipur",
    "lucknow": "lucknow",
    "chandigarh": "chandigarh",
    "indore": "indore",
    "kochi": "kochi",
    "cochin": "kochi",
    "patna": "patna",
    "bhopal": "bhopal",
    "nagpur": "nagpur",
    "surat": "surat",
    "vadodara": "vadodara",
    "ludhiana": "ludhiana",
    "coimbatore": "coimbatore",
    "visakhapatnam": "visakhapatnam",
    "vizag": "visakhapatnam",
    "agra": "agra",
    "kanpur": "kanpur",
    "varanasi": "varanasi",
    "nashik": "nashik",
    "rajkot": "rajkot",
    "dehradun": "dehradun",
    "ranchi": "ranchi",
    "guwahati": "guwahati",
    "bhubaneswar": "bhubaneswar",
}

class CardekhoSearchExtractor:
    def build_url(self, params: SearchQueryParams, page_override: Optional[int] = None) -> str:
        base = settings.CARDEKHO_BASE_URL.rstrip("/")
        page_num = page_override or params.page

        # Resolve location slug
        location_slug = "india"
        if params.city:
            clean_city = params.city.strip().lower()
            location_slug = CITY_SLUGS.get(clean_city, clean_city.replace(" ", "-"))

        # Resolve brand/model slug
        brand_slug = ""
        model_slug = ""
        if params.brand:
            brand_slug = params.brand.strip().lower().replace(" ", "-")
        if params.model:
            model_slug = params.model.strip().lower().replace(" ", "-")
            if brand_slug and model_slug.startswith(brand_slug + "-"):
                model_slug = model_slug[len(brand_slug) + 1:]

        if brand_slug and model_slug:
            segment = f"used-{brand_slug}-{model_slug}-cars"
        elif brand_slug:
            segment = f"used-{brand_slug}-cars"
        else:
            segment = "used-cars"

        url_path = f"{base}/{segment}+in+{location_slug}"

        if page_num > 1:
            url_path += f"/page-{page_num}"

        return url_path

    async def fetch_single_page(self, url: str) -> List[ListingItem]:
        html_content = None
        try:
            response = await http_client.get(url)
            if response and response.status_code == 200:
                html_content = response.text
        except Exception as e:
            logger.warning(f"HTTP fetch failed for {url}: {str(e)}")

        if not html_content or "__INITIAL_STATE__" not in html_content:
            if settings.ENABLE_BROWSER_FALLBACK:
                logger.info(f"Triggering browser fallback for CarDekho URL: {url}")
                html_content = await browser_manager.fetch_page_content(url)

        if not html_content:
            return []

        items = self._extract_items_from_initial_state(html_content)

        if not items:
            items = self._extract_items_from_json_ld(html_content)

        return items

    async def search(self, params: SearchQueryParams) -> SearchResponse:
        limit = min(300, max(1, params.limit))
        pages_needed = math.ceil(limit / 20)
        start_page = max(1, params.page)

        urls = [self.build_url(params, page_override=p) for p in range(start_page, start_page + pages_needed)]
        logger.info(f"Extracting CarDekho {len(urls)} pages for limit={limit}: {urls[0]}")

        results = await asyncio.gather(*[self.fetch_single_page(u) for u in urls], return_exceptions=True)

        all_items: List[ListingItem] = []
        seen_ids = set()

        for page_result in results:
            if isinstance(page_result, list):
                for item in page_result:
                    if item.id and item.id not in seen_ids:
                        seen_ids.add(item.id)
                        all_items.append(item)

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

    def _extract_items_from_initial_state(self, html_content: str) -> List[ListingItem]:
        items: List[ListingItem] = []
        match = re.search(r"window\.__INITIAL_STATE__\s*=\s*(\{.+?\});\s*(?:window\.|\n|<)", html_content, re.DOTALL)
        if not match:
            match = re.search(r"window\.__INITIAL_STATE__\s*=\s*(\{.+?\});", html_content, re.DOTALL)

        if not match:
            return []

        try:
            state = json.loads(match.group(1))
            raw_cars = state.get("cars", [])
            if isinstance(raw_cars, list):
                for row in raw_cars:
                    if isinstance(row, dict):
                        item = self.normalize_raw_car(row)
                        if item:
                            items.append(item)
        except Exception as e:
            logger.warning(f"Failed parsing CarDekho __INITIAL_STATE__: {str(e)}")

        return items

    def _extract_items_from_json_ld(self, html_content: str) -> List[ListingItem]:
        items: List[ListingItem] = []
        parser = HTMLParser(html_content)

        for script in parser.css('script[type="application/ld+json"]'):
            try:
                data = json.loads(script.text())
                if isinstance(data, dict) and data.get("@type") == "ItemList":
                    elements = data.get("itemListElement", [])
                    for elem in elements:
                        item_data = elem.get("item") if isinstance(elem, dict) else elem
                        if isinstance(item_data, dict):
                            item = self.normalize_raw_car(item_data)
                            if item:
                                items.append(item)
            except Exception:
                continue

        return items

    def normalize_raw_car(self, row: dict) -> Optional[ListingItem]:
        car_id = str(row.get("usedCarId") or row.get("ucid") or row.get("id") or row.get("usedCarSkuId") or "")
        if not car_id:
            return None

        oem = row.get("oem") or ""
        model_name = row.get("modelName") or row.get("model") or ""
        variant_name = row.get("variantName") or ""
        myear = row.get("myear") or row.get("modelYear")

        title_parts = []
        if myear:
            title_parts.append(str(myear))
        if oem and not model_name.startswith(oem):
            title_parts.append(oem)
        if model_name:
            title_parts.append(model_name)
        if variant_name and variant_name not in model_name:
            title_parts.append(variant_name)

        title = " ".join(title_parts).strip() if title_parts else (row.get("vid") or row.get("title") or f"Car #{car_id}")

        price_val = 0
        raw_price = row.get("price")
        if isinstance(raw_price, (int, float)):
            price_val = int(raw_price)
        elif isinstance(raw_price, str):
            clean_digits = re.sub(r"[^\d]", "", raw_price)
            if clean_digits:
                price_val = int(clean_digits)

        formatted_price = row.get("formattedPrice") or (f"₹ {price_val:,}" if price_val else "")

        km_str = str(row.get("km") or "")
        km_digits = re.sub(r"[^\d]", "", km_str)
        km_val = int(km_digits) if km_digits else None

        fuel = row.get("ft") or row.get("fuelType")
        transmission = row.get("tt") or row.get("transmission")
        body_type = row.get("bt") or row.get("bodyType")
        owner_raw = row.get("owner") or row.get("ownerNo") or "1st Owner"

        city = row.get("city") or row.get("cityName")
        locality = row.get("loc") or row.get("locality")

        primary_img = row.get("pi") or row.get("image")
        images = []
        if primary_img:
            images.append(ImageModel(
                url=str(primary_img),
                position=0,
            ))

        vlink = row.get("vlink") or ""
        listing_url = f"{settings.CARDEKHO_BASE_URL.rstrip('/')}{vlink}" if vlink.startswith("/") else vlink

        params_list = [
            ParameterModel(key="brand", value=oem),
            ParameterModel(key="model", value=model_name),
            ParameterModel(key="variant", value=variant_name),
            ParameterModel(key="year", value=str(myear) if myear else ""),
            ParameterModel(key="km", value=str(km_val) if km_val else km_str),
            ParameterModel(key="fuel_type", value=str(fuel) if fuel else ""),
            ParameterModel(key="transmission", value=str(transmission) if transmission else ""),
            ParameterModel(key="owner", value=str(owner_raw) if owner_raw else ""),
            ParameterModel(key="formatted_price", value=formatted_price),
        ]
        if body_type:
            params_list.append(ParameterModel(key="body_type", value=str(body_type)))
        if row.get("cdCertified"):
            params_list.append(ParameterModel(key="certified", value="true"))

        dealer_id = row.get("dealerId") or row.get("dlId")
        seller = SellerModel(
            name="CarDekho Verified Seller" if dealer_id else "Car Owner",
            type="dealer" if dealer_id else "individual",
            contact_available=bool(row.get("leadForm") or dealer_id),
        )

        description = f"{title} available in {city or 'India'}. Driven {km_str or 'N/A'} km, {fuel or 'Petrol'} ({transmission or 'Manual'}), {owner_raw}."

        category_obj = {"id": "automobile", "slug": "cars", "name": "Cars"}
        subcategory_obj = {"id": "cars", "slug": "used-cars", "name": "Used Cars"}

        raw_payload = dict(row)
        raw_payload["source"] = "cardekho"
        raw_payload["automobile"] = {
            "brand": oem or None,
            "model": model_name or None,
            "variant": variant_name or None,
            "year": int(myear) if myear else None,
            "kilometers": km_val,
            "fuel_type": str(fuel) if fuel else None,
            "transmission": str(transmission) if transmission else None,
            "body_type": str(body_type) if body_type else None,
            "number_of_owners": 1 if "1" in str(owner_raw) else (2 if "2" in str(owner_raw) else 1),
            "rto": row.get("rtoCode") or row.get("rto"),
            "formatted_price": formatted_price,
        }

        return ListingItem(
            id=f"cardekho-{car_id}",
            title=title,
            description=description,
            url=listing_url,
            price=PriceModel(
                value=price_val,
                currency="INR",
                negotiable=False,
            ),
            category=category_obj,
            subcategory=subcategory_obj,
            location=LocationModel(
                city=city,
                locality=locality,
                state=None,
                pincode=None,
            ),
            seller=seller,
            images=images,
            parameters=params_list,
            raw_data=raw_payload,
        )

    def _filter_items(self, items: List[ListingItem], params: SearchQueryParams) -> List[ListingItem]:
        filtered = []
        kw = params.keyword.lower().strip() if params.keyword else None
        brand_kw = params.brand.lower().strip() if params.brand else None
        model_kw = params.model.lower().strip() if params.model else None
        fuel_kw = params.fuel_type.lower().strip() if params.fuel_type else None
        trans_kw = params.transmission.lower().strip() if params.transmission else None

        for it in items:
            auto = (it.raw_data or {}).get("automobile", {})
            if kw:
                haystack = f"{it.title} {it.description or ''}".lower()
                if kw not in haystack:
                    continue

            if brand_kw and auto:
                brand_val = str(auto.get("brand") or "").lower()
                if brand_kw not in brand_val and brand_kw not in (it.title or "").lower():
                    continue

            if model_kw and auto:
                model_val = str(auto.get("model") or "").lower()
                if model_kw not in model_val and model_kw not in (it.title or "").lower():
                    continue

            if fuel_kw and auto:
                fuel_val = str(auto.get("fuel_type") or "").lower()
                if fuel_kw not in fuel_val:
                    continue

            if trans_kw and auto:
                trans_val = str(auto.get("transmission") or "").lower()
                if trans_kw not in trans_val:
                    continue

            if auto.get("year"):
                yr = auto["year"]
                if params.min_year and yr < params.min_year:
                    continue
                if params.max_year and yr > params.max_year:
                    continue

            if auto.get("kilometers") is not None:
                km = auto["kilometers"]
                if params.min_km and km < params.min_km:
                    continue
                if params.max_km and km > params.max_km:
                    continue

            if it.price and it.price.value:
                if params.min_price and it.price.value < params.min_price:
                    continue
                if params.max_price and it.price.value > params.max_price:
                    continue

            filtered.append(it)

        if params.sort:
            if params.sort == "price_low_to_high":
                filtered.sort(key=lambda x: (x.price is None or x.price.value is None, x.price.value if (x.price and x.price.value) else 0))
            elif params.sort == "price_high_to_low":
                filtered.sort(key=lambda x: (x.price is None or x.price.value is None, -(x.price.value if (x.price and x.price.value) else 0)))
            elif params.sort == "newest":
                filtered.sort(key=lambda x: ((x.raw_data or {}).get("automobile", {}).get("year", 0), str(x.created_at or "")), reverse=True)
            elif params.sort == "oldest":
                filtered.sort(key=lambda x: ((x.raw_data or {}).get("automobile", {}).get("year", 9999), str(x.created_at or "")))

        return filtered

cardekho_search_extractor = CardekhoSearchExtractor()
