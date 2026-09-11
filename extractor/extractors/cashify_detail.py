import json
import re
import logging
from typing import Optional, Dict, Any, List
from selectolax.parser import HTMLParser

from extractor.config import settings
from extractor.models.response import (
    DetailResponse,
    ListingItem,
    PriceModel,
    LocationModel,
    SellerModel,
    ImageModel,
    ParameterModel,
)
from extractor.engine.http_client import http_client
from extractor.engine.browser_pool import browser_manager

logger = logging.getLogger(__name__)

class CashifyDetailExtractor:
    async def get_detail(self, listing_id: str, listing_url: Optional[str] = None) -> DetailResponse:
        clean_id = listing_id.replace("cashify-", "").strip()
        target_url = listing_url

        if not target_url:
            if clean_id:
                if clean_id.startswith("http"):
                    target_url = clean_id
                else:
                    target_url = f"{settings.CASHIFY_BASE_URL.rstrip('/')}/buy-refurbished-mobile-phones/{clean_id}"

        if not target_url:
            return DetailResponse(data=None)

        logger.info(f"Fetching Cashify detail for ID '{clean_id}' from URL: {target_url}")

        html_content = None
        try:
            response = await http_client.get(target_url)
            if response and response.status_code == 200:
                html_content = response.text
        except Exception as e:
            logger.warning(f"HTTP fetch failed for Cashify detail {target_url}: {str(e)}")

        if not html_content:
            if settings.ENABLE_BROWSER_FALLBACK:
                logger.info(f"Triggering browser fallback for Cashify detail URL: {target_url}")
                html_content = await browser_manager.fetch_page_content(target_url)

        if not html_content:
            return DetailResponse(data=None)

        item = self._extract_detail_from_rsc(html_content, clean_id, target_url)
        if not item:
            item = self._extract_detail_from_dom(html_content, clean_id, target_url)

        return DetailResponse(data=item)

    def _extract_detail_from_rsc(self, html_content: str, clean_id: str, url: str) -> Optional[ListingItem]:
        matches = re.findall(r'self\.__next_f\.push\(\[1,"(.*?)"\]\)', html_content)
        if not matches:
            return None

        combined = "".join(matches).replace('\\"', '"').replace('\\\\', '\\')

        # Find product name
        name_match = re.search(r'"productName":\s*"([^"]+)"', combined)
        title = name_match.group(1) if name_match else None
        if not title:
            # Check for generic name
            name_match2 = re.search(r'"name":\s*"([^"]+)"', combined)
            title = name_match2.group(1) if name_match2 else None

        if not title:
            return None

        # Prices
        sale_match = re.search(r'"minSalePrice":\s*(\d+)', combined) or re.search(r'"salePrice":\s*(\d+)', combined) or re.search(r'"effectivePrice":\s*(\d+)', combined)
        sale_price = int(sale_match.group(1)) if sale_match else None

        mrp_match = re.search(r'"mrp":\s*(\d+)', combined) or re.search(r'"originalPrice":\s*(\d+)', combined)
        mrp_price = int(mrp_match.group(1)) if mrp_match else None

        # Images
        images: List[ImageModel] = []
        img_matches = re.findall(r'"(?:defaultProductImg|defaultImage|imageUrl|imgUrl)":\s*"([^"]+)"', combined)
        seen_imgs = set()
        for img in img_matches:
            if img.startswith("http") and img not in seen_imgs and not img.endswith(".svg"):
                seen_imgs.add(img)
                images.append(ImageModel(url=img, position=len(images)))

        # Variant / Grade info
        variant_match = re.search(r'"variantName":\s*"([^"]+)"', combined)
        variant_name = variant_match.group(1) if variant_match else None

        grade_match = re.search(r'"grade":\s*"([^"]+)"', combined)
        grade = grade_match.group(1) if grade_match else "Superb"

        warranty_match = re.search(r'"warrantyDuration":\s*(\d+)', combined)
        warranty_months = int(warranty_match.group(1)) if warranty_match else 6

        rating_match = re.search(r'"averageRating":\s*"([0-9.]+)"', combined) or re.search(r'"averageRating":\s*([0-9.]+)', combined)
        rating = str(rating_match.group(1)) if rating_match else "4.5"

        params = [
            ParameterModel(key="condition", value="Refurbished"),
            ParameterModel(key="grade", value=grade),
            ParameterModel(key="warranty", value=f"{warranty_months} Months Cashify Warranty"),
            ParameterModel(key="rating", value=rating),
        ]
        if variant_name:
            params.append(ParameterModel(key="variant", value=variant_name))
        if mrp_price:
            params.append(ParameterModel(key="original_price", value=str(mrp_price)))

        # Extract RAM & Storage
        ram_match = re.search(r"\b(\d+)\s*GB\s*RAM\b", title + " " + (variant_name or ""), re.I)
        if ram_match:
            params.append(ParameterModel(key="ram", value=f"{ram_match.group(1)} GB"))

        storage_match = re.search(r"\b(16|32|64|128|256|512)\s*(?:GB|TB)\b", title + " " + (variant_name or ""), re.I) or re.search(r"\b(1|2)\s*TB\b", title + " " + (variant_name or ""), re.I)
        if storage_match:
            params.append(ParameterModel(key="storage", value=storage_match.group(0).upper()))

        description = f"{title} ({variant_name or grade}) - Fully tested and inspected with 32 quality checks. Comes with {warranty_months} months Cashify warranty and pan-India delivery."

        return ListingItem(
            id=f"cashify-{clean_id}" if clean_id else f"cashify-{abs(hash(title)) % 1000000}",
            title=title,
            description=description,
            url=url,
            price=PriceModel(value=sale_price, currency="INR") if sale_price else None,
            category={"id": "mobiles", "name": "Mobiles"},
            subcategory={"id": "smartphones", "name": "Refurbished Smartphones"},
            location=LocationModel(city="Pan India", state="All India", locality="Cashify Certified Store"),
            seller=SellerModel(name="Cashify Verified Store", type="verified_store", contact_available=True),
            images=images,
            parameters=params,
            raw_data={"source": "cashify", "title": title, "sale_price": sale_price, "mrp": mrp_price, "variant": variant_name, "grade": grade},
        )

    def _extract_detail_from_dom(self, html_content: str, clean_id: str, url: str) -> Optional[ListingItem]:
        parser = HTMLParser(html_content)
        h1 = parser.css_first("h1")
        if not h1:
            return None

        title = h1.text(strip=True)
        price_elem = parser.css_first("div[class*='price'], span[class*='price']")
        price_val = None
        if price_elem:
            p_match = re.search(r"₹\s*([\d,]+)", price_elem.text())
            if p_match:
                price_val = int(p_match.group(1).replace(",", ""))

        images: List[ImageModel] = []
        for img in parser.css("img[src*='cashify'], img[src*='estore']"):
            src = img.attributes.get("src")
            if src and src.startswith("http") and not src.endswith(".svg"):
                images.append(ImageModel(url=src, position=len(images)))

        return ListingItem(
            id=f"cashify-{clean_id}" if clean_id else f"cashify-{abs(hash(title)) % 1000000}",
            title=title,
            description=f"{title} refurbished gadget from Cashify Store.",
            url=url,
            price=PriceModel(value=price_val, currency="INR") if price_val else None,
            category={"id": "mobiles", "name": "Mobiles"},
            subcategory={"id": "smartphones", "name": "Refurbished Smartphones"},
            location=LocationModel(city="Pan India", state="All India", locality="Cashify Certified Store"),
            seller=SellerModel(name="Cashify Verified Store", type="verified_store", contact_available=True),
            images=images[:5],
            parameters=[ParameterModel(key="condition", value="Refurbished")],
            raw_data={"source": "cashify", "title": title, "sale_price": price_val},
        )

cashify_detail_extractor = CashifyDetailExtractor()
