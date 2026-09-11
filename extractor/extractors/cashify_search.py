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

CATEGORY_MAP = {
    "phones": "buy-refurbished-mobile-phones",
    "mobile-phones": "buy-refurbished-mobile-phones",
    "mobiles": "buy-refurbished-mobile-phones",
    "smartphones": "buy-refurbished-mobile-phones",
    "laptops": "buy-refurbished-laptops",
    "laptop": "buy-refurbished-laptops",
    "smartwatches": "buy-refurbished-smart-watches",
    "smart-watches": "buy-refurbished-smart-watches",
    "tablets": "buy-refurbished-tablets",
    "tablet": "buy-refurbished-tablets",
    "gaming-consoles": "buy-refurbished-gaming-consoles",
    "gaming": "buy-refurbished-gaming-consoles",
    "audio": "buy-openbox-audio-devices",
    "accessories": "buy-openbox-accessories",
    "cameras": "buy-refurbished-dslr-cameras",
    "gadgets": "buy-refurbished-gadgets",
}

BRAND_MAP = {
    "apple": "apple",
    "samsung": "samsung",
    "xiaomi": "xiaomi",
    "redmi": "xiaomi",
    "mi": "xiaomi",
    "oneplus": "oneplus",
    "google": "google",
    "pixel": "google",
    "oppo": "oppo",
    "vivo": "vivo",
    "realme": "realme",
    "motorola": "motorola",
    "moto": "motorola",
    "nokia": "nokia",
    "poco": "poco",
    "dell": "dell",
    "hp": "hp-compaq",
    "lenovo": "lenovo",
    "asus": "asus",
    "acer": "acer",
    "sony": "sony",
    "boat": "boat",
    "noise": "noise",
    "amazfit": "amazfit",
}

class CashifySearchExtractor:
    def build_url(self, params: SearchQueryParams, page_override: Optional[int] = None) -> str:
        base = settings.CASHIFY_BASE_URL.rstrip("/")
        page_num = page_override or params.page

        cat_raw = (params.subcategory or params.category or "").strip().lower()
        category_segment = CATEGORY_MAP.get(cat_raw, "buy-refurbished-mobile-phones")

        brand_raw = (params.brand or "").strip().lower()
        brand_segment = BRAND_MAP.get(brand_raw, "")

        if not brand_segment and params.keyword:
            # Check if keyword matches a known brand
            kw_clean = params.keyword.strip().lower()
            for b_name, b_slug in BRAND_MAP.items():
                if b_name in kw_clean:
                    brand_segment = b_slug
                    break

        if brand_segment:
            url_path = f"{base}/{category_segment}/{brand_segment}"
        else:
            url_path = f"{base}/{category_segment}"

        query_dict: Dict[str, Any] = {}
        if params.keyword and not brand_segment:
            query_dict["search"] = params.keyword
        if page_num > 1:
            query_dict["page"] = page_num

        if query_dict:
            return f"{url_path}?{urlencode(query_dict)}"
        return url_path

    async def fetch_single_page(self, url: str) -> List[ListingItem]:
        html_content = None
        response = await http_client.get(url)
        if response and response.status_code == 200:
            html_content = response.text

        if not html_content or "productList" not in html_content:
            if settings.ENABLE_BROWSER_FALLBACK:
                logger.info(f"Falling back to stealth browser for Cashify: {url}")
                html_content = await browser_manager.fetch_page_content(url)

        if not html_content:
            return []

        # 1. Primary: Parse productList from Next.js RSC (__next_f)
        items = self._extract_items_from_rsc(html_content)
        # 2. Secondary: Fallback to Selectolax DOM parsing if needed
        if not items:
            items = self._extract_items_from_dom(html_content)

        await self._enrich_missing_images(items)

        return items

    async def search(self, params: SearchQueryParams) -> SearchResponse:
        limit = min(300, max(1, params.limit))
        start_page = max(1, params.page)

        # Cashify yields ~10-24 products per brand/category page
        pages_needed = max(1, math.ceil(limit / 16))
        
        urls = [self.build_url(params, page_override=p) for p in range(start_page, start_page + pages_needed)]
        
        # If user did not specify brand and category is mobile-phones or gadgets, also gather from top brands to fill limit
        if not params.brand and not params.keyword and limit > 15:
            base = settings.CASHIFY_BASE_URL.rstrip("/")
            cat_seg = CATEGORY_MAP.get((params.subcategory or params.category or "").lower(), "buy-refurbished-mobile-phones")
            extra_brands = ["apple", "samsung", "oneplus", "xiaomi", "google"]
            for b in extra_brands:
                if len(urls) >= 8:
                    break
                extra_url = f"{base}/{cat_seg}/{b}"
                if extra_url not in urls:
                    urls.append(extra_url)

        logger.info(f"Extracting {len(urls)} Cashify page(s) for limit={limit}: {urls[0]}")

        results = await asyncio.gather(*[self.fetch_single_page(u) for u in urls], return_exceptions=True)

        all_items: List[ListingItem] = []
        seen_ids = set()

        for page_result in results:
            if isinstance(page_result, list):
                for item in page_result:
                    if item.id and item.id not in seen_ids:
                        seen_ids.add(item.id)
                        all_items.append(item)

        # In-memory filter for price, brand, keywords
        filtered = self._filter_items(all_items, params)
        paged_items = filtered[:limit]

        # Enrich missing images asynchronously
        await self._enrich_missing_images(paged_items)

        return SearchResponse(
            data=paged_items,
            pagination=PaginationModel(
                page=params.page,
                limit=limit,
                has_next=len(filtered) >= limit,
                total_records=len(filtered),
            ),
        )

    def _extract_items_from_rsc(self, html_content: str) -> List[ListingItem]:
        matches = re.findall(r'self\.__next_f\.push\(\[1,"(.*?)"\]\)', html_content)
        if not matches:
            return []

        combined = "".join(matches).replace('\\"', '"').replace('\\\\', '\\')
        
        pos = combined.find('"productList":[')
        if pos == -1:
            return []

        start = pos + len('"productList":')
        open_brackets = 0
        end = -1
        for i in range(start, len(combined)):
            if combined[i] == '[':
                open_brackets += 1
            elif combined[i] == ']':
                open_brackets -= 1
                if open_brackets == 0:
                    end = i + 1
                    break

        if end == -1:
            return []

        json_array_str = combined[start:end]
        try:
            raw_items = json.loads(json_array_str)
            items = []
            for row in raw_items:
                if isinstance(row, dict):
                    normalized = self.normalize_raw_item(row)
                    if normalized:
                        items.append(normalized)
            return items
        except Exception as e:
            logger.warning(f"Error parsing Cashify RSC JSON array: {e}")
            return []

    def _extract_best_image_from_dom_node(self, a) -> Optional[str]:
        imgs = a.css("img")
        if not imgs and a.parent:
            imgs = a.parent.css("img")
            
        EXCLUDE_KEYWORDS = [
            "star", "rating", "assured", "gold-icon", "check", "logo", "builder", 
            "delivery", "strip", "arrow", "badge"
        ]
        
        candidate_urls = []
        for img in imgs:
            attrs = dict(img.attributes)
            alt = attrs.get("alt", "").lower()
            cls = attrs.get("class", "").lower()
            style = attrs.get("style", "").lower()
            
            # Exclude background overlays
            if "absolute left-0 top-0" in cls or ("aspect-ratio:2/1" in style and "w-full" in cls):
                continue
                
            raw_url = attrs.get("data-src") or attrs.get("src") or attrs.get("data-original-src") or attrs.get("data-lazy-src") or ""
            if not raw_url and "http" in alt:
                m = re.search(r"https?://[^\s\"']+", alt)
                if m:
                    raw_url = m.group(0)
                    
            if not raw_url:
                continue
                
            combined_meta = f"{alt} {cls} {raw_url}".lower()
            if any(b in combined_meta for b in EXCLUDE_KEYWORDS):
                continue
                
            clean_url = re.sub(r"\?w=\d+&blur=\d+", "", raw_url)
            clean_url = re.sub(r"([?&])blur=\d+(&|$)", r"\1", clean_url).rstrip("?&")
            if clean_url.startswith("//"):
                clean_url = "https:" + clean_url
                
            is_high_res_product = "product/img" in clean_url or "cashify/store" in clean_url or "aspect-square" in cls or "object-contain" in cls
            candidate_urls.append((clean_url, is_high_res_product))
            
        for url, is_product in candidate_urls:
            if is_product:
                return url
        if candidate_urls:
            return candidate_urls[0][0]
            
        return None

    def _extract_items_from_dom(self, html_content: str) -> List[ListingItem]:
        items: List[ListingItem] = []
        parser = HTMLParser(html_content)

        for a in parser.css("a[href*='/buy-refurbished-'], a[href*='/buy-openbox-']"):
            href = a.attributes.get("href", "")
            if not href or href.endswith("/sale") or href.count("/") < 3:
                continue

            title_elem = a.css_first("span[class*='subtitle'], span[class*='title'], h2, h3, h4, div[class*='name'], div[class*='title'], p[class*='name'], p[class*='title']")
            raw_title = title_elem.text(strip=True) if title_elem else a.text(strip=True)
            if not raw_title:
                continue

            clean_title = re.sub(r"^\d+%\s*OFF\s*", "", raw_title).strip()
            clean_title = re.sub(r"₹\s*[\d,]+.*$", "", clean_title).strip()
            clean_title = re.sub(r"\(?\s*\d+\s*(?:Reviews?|Ratings?)\s*\)?.*$", "", clean_title, flags=re.IGNORECASE).strip()

            if not clean_title or len(clean_title) < 5 or clean_title.lower() in ["view all", "view more", "see all", "see more"]:
                continue

            price_match = re.search(r"₹\s*([\d,]+)", a.text())
            price_val = int(price_match.group(1).replace(",", "")) if price_match else None

            img_url = self._extract_best_image_from_dom_node(a)

            listing_id = "cashify-" + re.sub(r"[^a-zA-Z0-9_-]", "-", href.split("/")[-1])
            full_url = f"{settings.CASHIFY_BASE_URL.rstrip('/')}{href}" if href.startswith("/") else href

            cat_info = self._resolve_category_from_text(clean_title, href)

            items.append(ListingItem(
                id=listing_id,
                title=clean_title,
                description=f"{clean_title} available at Cashify Store with warranty.",
                url=full_url,
                price=PriceModel(value=price_val, currency="INR") if price_val else None,
                category=cat_info["category"],
                subcategory=cat_info["subcategory"],
                images=[ImageModel(url=img_url, position=0)] if img_url else [],
                parameters=[
                    ParameterModel(key="brand", value=self._detect_brand(clean_title)),
                    ParameterModel(key="condition", value="Refurbished"),
                    ParameterModel(key="warranty", value="6 Months Cashify Warranty"),
                ],
                seller=SellerModel(name="Cashify Verified Store", type="store", contact_available=True),
            ))

        return items

    def _resolve_category_from_text(self, text: str, href: str = "") -> Dict[str, Any]:
        combined = f"{text} {href}".lower()
        if any(w in combined for w in ["laptop", "macbook", "notebook", "thinkpad"]):
            return {
                "category": {"id": "electronics", "name": "Electronics", "slug": "electronics"},
                "subcategory": {"id": "laptops", "name": "Refurbished Laptops", "slug": "laptops"}
            }
        elif any(w in combined for w in ["watch", "smartwatch", "fitbit"]):
            return {
                "category": {"id": "electronics", "name": "Electronics", "slug": "electronics"},
                "subcategory": {"id": "smartwatches", "name": "Refurbished Smartwatches", "slug": "smartwatches"}
            }
        elif any(w in combined for w in ["headphone", "earbud", "airpod", "audio", "speaker"]):
            return {
                "category": {"id": "electronics", "name": "Electronics", "slug": "electronics"},
                "subcategory": {"id": "audio", "name": "Refurbished Audio", "slug": "audio"}
            }
        elif any(w in combined for w in ["tablet", "ipad"]):
            return {
                "category": {"id": "electronics", "name": "Electronics", "slug": "electronics"},
                "subcategory": {"id": "tablets", "name": "Refurbished Tablets", "slug": "tablets"}
            }
        return {
            "category": {"id": "mobiles", "name": "Mobiles", "slug": "mobiles"},
            "subcategory": {"id": "smartphones", "name": "Refurbished Smartphones", "slug": "smartphones"}
        }

    async def _fetch_item_image_from_detail(self, item: ListingItem):
        if not item.url:
            return
        try:
            res = await http_client.get(item.url)
            if not res or res.status_code != 200:
                return

            # 1. Search RSC for defaultImage or image
            m = re.search(r'"defaultImage":"(https?:[^"\\]+)"', res.text)
            if not m:
                m = re.search(r'"image":"(https?:[^"\\]+)"', res.text)
            if m:
                img_url = m.group(1).replace("\\/", "/")
                img_url = re.sub(r"\?w=\d+&blur=\d+", "", img_url)
                img_url = re.sub(r"([?&])blur=\d+(&|$)", r"\1", img_url).rstrip("?&")
                item.images = [ImageModel(url=img_url, position=0)]
                return

            # 2. Search DOM
            parser = HTMLParser(res.text)
            for img in parser.css("img"):
                alt = img.attributes.get("alt", "").lower()
                src = img.attributes.get("data-src") or img.attributes.get("src") or ""
                if "pd image" in alt or ("product" in src and "xxhdpi" in src):
                    clean_src = re.sub(r"\?w=\d+&blur=\d+", "", src)
                    clean_src = re.sub(r"([?&])blur=\d+(&|$)", r"\1", clean_src).rstrip("?&")
                    item.images = [ImageModel(url=clean_src, position=0)]
                    return
        except Exception as e:
            logger.warning(f"Failed to enrich image for {item.id}: {e}")

    async def _enrich_missing_images(self, items: List[ListingItem]):
        missing = [it for it in items if not it.images or len(it.images) == 0]
        if not missing:
            return

        # Concurrently enrich up to 6 missing items from their detail page
        tasks = [self._fetch_item_image_from_detail(it) for it in missing[:6]]
        await asyncio.gather(*tasks, return_exceptions=True)

        fallback_map = {
            "laptop": "https://s3ng.cashify.in/estore/7f86cd7124e849139c62e3ab6556f097.png",
            "watch": "https://s3n.cashify.in/cashify/product/img/xxhdpi/44dd46d3-de53.jpg",
            "audio": "https://s3ng.cashify.in/estore/b78ee8065a8f4ec8b8b6469d75f720d6.jpg",
            "tablet": "https://s3n.cashify.in/cashify/product/img/xxhdpi/71f845d4-4693.jpg",
            "phone": "https://s3n.cashify.in/cashify/product/img/xxhdpi/d197ee88-ccff.jpg",
            "mobile": "https://s3n.cashify.in/cashify/product/img/xxhdpi/d197ee88-ccff.jpg",
        }
        for it in items:
            if not it.images or len(it.images) == 0:
                text = f"{it.title} {(it.subcategory or {}).get('name', '')}".lower()
                matched_fb = fallback_map["mobile"]
                for k, fb_url in fallback_map.items():
                    if k in text:
                        matched_fb = fb_url
                        break
                it.images = [ImageModel(url=matched_fb, position=0)]

    def normalize_raw_item(self, row: Dict[str, Any]) -> Optional[ListingItem]:
        product_id = str(row.get("productId") or row.get("productDiscoveryId") or row.get("id") or "")
        title = row.get("productName") or row.get("name") or row.get("title")
        if not title:
            return None

        # Clean ID
        clean_id = "cashify-" + re.sub(r"[^\w\-]", "", product_id.replace("|", "-")) if product_id else f"cashify-{abs(hash(title)) % 1000000}"

        sale_price = row.get("salePrice") or row.get("effectivePrice") or row.get("channelPrice")
        mrp = row.get("mrp")
        try:
            sale_price_val = int(sale_price) if sale_price is not None else None
        except (ValueError, TypeError):
            sale_price_val = None

        try:
            mrp_val = int(mrp) if mrp is not None else None
        except (ValueError, TypeError):
            mrp_val = None

        slug = row.get("slug") or ""
        listing_url = f"{settings.CASHIFY_BASE_URL.rstrip('/')}{slug}" if slug.startswith("/") else slug

        # Collect image candidates across all possible schema fields
        img_candidates = [
            row.get("image"),
            row.get("defaultImage"),
            row.get("defaultProductImg"),
            row.get("productImage"),
            row.get("imageUrl"),
            row.get("img"),
            row.get("thumbnail"),
            row.get("thumbnailUrl"),
            row.get("mediaUrl"),
            (row.get("deviceVideo") or {}).get("thumbnailUrl"),
            (row.get("saleData") or {}).get("image"),
            (row.get("saleData") or {}).get("variantImage"),
        ]

        # Also check array fields
        for arr_key in ["images", "pictures", "photos"]:
            val = row.get(arr_key)
            if isinstance(val, list):
                for item_val in val:
                    if isinstance(item_val, str):
                        img_candidates.append(item_val)
                    elif isinstance(item_val, dict):
                        img_candidates.append(item_val.get("url") or item_val.get("src") or item_val.get("image"))

        # Find first non-empty valid image URL
        img_url = None
        for candidate in img_candidates:
            if candidate and isinstance(candidate, str) and candidate.strip() and not candidate.startswith("$"):
                clean_cand = candidate.strip()
                if clean_cand.startswith("//"):
                    clean_cand = "https:" + clean_cand
                if clean_cand.startswith("http"):
                    clean_cand = re.sub(r"\?w=\d+&blur=\d+", "", clean_cand)
                    clean_cand = re.sub(r"([?&])blur=\d+(&|$)", r"\1", clean_cand).rstrip("?&")
                    img_url = clean_cand
                    break

        images = []
        if img_url:
            images.append(ImageModel(url=str(img_url), position=0))

        product_type = row.get("productType") or "Refurbished Gadget"
        rating = row.get("averageRating")
        total_ratings = row.get("totalRatings")
        inventory = row.get("inventory")
        emi_amount = row.get("emiAmount")
        warranty_list = row.get("warrantyType") or ["Cashify Warranty"]
        warranty_str = ", ".join(warranty_list) if isinstance(warranty_list, list) else str(warranty_list)
        assured_by = row.get("assuredBy") or "Cashify Assured"

        # Resolve brand and model from title
        brand = self._detect_brand(title)

        params_list = [
            ParameterModel(key="brand", value=brand),
            ParameterModel(key="condition", value="Refurbished"),
            ParameterModel(key="warranty", value=warranty_str),
            ParameterModel(key="assured_by", value=assured_by),
        ]
        if rating is not None:
            params_list.append(ParameterModel(key="rating", value=str(rating)))
        if total_ratings is not None:
            params_list.append(ParameterModel(key="total_reviews", value=str(total_ratings)))
        if inventory is not None:
            params_list.append(ParameterModel(key="inventory", value=str(inventory)))
        if emi_amount is not None:
            params_list.append(ParameterModel(key="emi_amount", value=str(emi_amount)))
        if mrp_val is not None:
            params_list.append(ParameterModel(key="original_price", value=str(mrp_val)))

        # Extract RAM / Storage / Specs from title if present
        ram_match = re.search(r"\b(\d+)\s*GB\s*RAM\b", title, re.IGNORECASE)
        if ram_match:
            params_list.append(ParameterModel(key="ram", value=f"{ram_match.group(1)} GB"))

        storage_match = re.search(r"\b(16|32|64|128|256|512)\s*(?:GB|TB)\b", title, re.IGNORECASE) or re.search(r"\b(1|2)\s*TB\b", title, re.IGNORECASE)
        if storage_match:
            params_list.append(ParameterModel(key="storage", value=storage_match.group(0).upper()))

        # Determine Category & Subcategory
        type_lower = product_type.lower() + " " + title.lower()
        if any(w in type_lower for w in ["phone", "mobile", "iphone", "galaxy"]):
            cat_id = "mobiles"
            cat_name = "Mobiles"
            subcat_id = "smartphones"
            subcat_name = "Refurbished Smartphones"
        elif any(w in type_lower for w in ["laptop", "macbook", "notebook"]):
            cat_id = "electronics"
            cat_name = "Electronics"
            subcat_id = "laptops"
            subcat_name = "Refurbished Laptops"
        elif any(w in type_lower for w in ["watch", "smartwatch"]):
            cat_id = "electronics"
            cat_name = "Electronics"
            subcat_id = "smartwatches"
            subcat_name = "Refurbished Smartwatches"
        elif any(w in type_lower for w in ["tablet", "ipad"]):
            cat_id = "electronics"
            cat_name = "Electronics"
            subcat_id = "tablets"
            subcat_name = "Refurbished Tablets"
        else:
            cat_id = "electronics"
            cat_name = "Electronics"
            subcat_id = "accessories"
            subcat_name = "Refurbished Accessories"

        description = f"{title} - Cashify certified refurbished device. 32-point quality check, {warranty_str}, tested and verified with warranty support."

        seller = SellerModel(
            name="Cashify Store Verified",
            type="verified_store",
            contact_available=True,
        )

        location = LocationModel(
            city="Pan India",
            state="All India",
            locality="Cashify Certified Store",
        )

        raw_payload = dict(row)
        raw_payload["source"] = "cashify"
        raw_payload["brand"] = brand
        raw_payload["original_price"] = mrp_val
        raw_payload["sale_price"] = sale_price_val

        return ListingItem(
            id=clean_id,
            title=title,
            description=description,
            url=listing_url,
            price=PriceModel(value=sale_price_val, currency="INR") if sale_price_val else None,
            category={"id": cat_id, "name": cat_name, "slug": cat_id},
            subcategory={"id": subcat_id, "name": subcat_name, "slug": subcat_id},
            location=location,
            seller=seller,
            images=images,
            parameters=params_list,
            raw_data=raw_payload,
        )

    def _detect_brand(self, title: str) -> str:
        title_lower = title.lower()
        for b_name, b_slug in BRAND_MAP.items():
            if b_name in title_lower:
                return b_name.capitalize()
        return "Generic"

    def _filter_items(self, items: List[ListingItem], params: SearchQueryParams) -> List[ListingItem]:
        filtered = []
        for item in items:
            # Price filters
            if item.price and item.price.value:
                if params.min_price and item.price.value < params.min_price:
                    continue
                if params.max_price and item.price.value > params.max_price:
                    continue

            # Brand filter
            if params.brand:
                brand_match = False
                for p in item.parameters:
                    if p.key == "brand" and params.brand.lower() in str(p.value).lower():
                        brand_match = True
                        break
                if not brand_match and params.brand.lower() not in (item.title or "").lower():
                    continue

            # Keyword filter
            if params.keyword:
                kw = params.keyword.lower()
                combined_txt = f"{item.title or ''} {item.description or ''}".lower()
                if kw not in combined_txt:
                    continue

            filtered.append(item)

        # Sorting
        if params.sort == "price_low_to_high":
            filtered.sort(key=lambda x: (x.price.value if x.price and x.price.value else float("inf")))
        elif params.sort == "price_high_to_low":
            filtered.sort(key=lambda x: (x.price.value if x.price and x.price.value else 0), reverse=True)

        return filtered

cashify_search_extractor = CashifySearchExtractor()
