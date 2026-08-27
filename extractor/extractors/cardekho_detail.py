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

class CardekhoDetailExtractor:
    async def get_detail(self, listing_id: str, listing_url: Optional[str] = None) -> DetailResponse:
        clean_id = listing_id.replace("cardekho-", "").strip()
        target_url = listing_url

        if not target_url:
            if clean_id:
                target_url = f"{settings.CARDEKHO_BASE_URL.rstrip('/')}/used-car-details/{clean_id}.htm"

        if not target_url:
            return DetailResponse(data=None)

        logger.info(f"Fetching CarDekho detail for ID '{clean_id}' from URL: {target_url}")

        html_content = None
        try:
            response = await http_client.get(target_url)
            if response and response.status_code == 200:
                html_content = response.text
        except Exception as e:
            logger.warning(f"HTTP fetch failed for CarDekho detail {target_url}: {str(e)}")

        if not html_content or "__INITIAL_STATE__" not in html_content:
            if settings.ENABLE_BROWSER_FALLBACK:
                logger.info(f"Triggering browser fallback for CarDekho detail URL: {target_url}")
                html_content = await browser_manager.fetch_page_content(target_url)

        if not html_content:
            return DetailResponse(data=None)

        item = self._extract_detail_from_initial_state(html_content, clean_id, target_url)
        if not item:
            item = self._extract_detail_from_dom(html_content, clean_id, target_url)

        return DetailResponse(data=item)

    def _extract_detail_from_initial_state(self, html_content: str, clean_id: str, url: str) -> Optional[ListingItem]:
        match = re.search(r"window\.__INITIAL_STATE__\s*=\s*(\{.+?\});\s*(?:window\.|\n|<)", html_content, re.DOTALL)
        if not match:
            match = re.search(r"window\.__INITIAL_STATE__\s*=\s*(\{.+?\});", html_content, re.DOTALL)

        if not match:
            return None

        try:
            state = json.loads(match.group(1))
            item_data = state.get("item", {})
            if not item_data and "carDetails" in state:
                item_data = state

            if not item_data:
                return None

            car_details = item_data.get("carDetails", {})
            car_overview = item_data.get("carOverview", {})
            gallery_dto = item_data.get("galleryDto", {})
            seo = item_data.get("seo", {})

            car_id = str(item_data.get("usedCarId") or clean_id or item_data.get("usedCarSkuId") or "detail")

            oem = car_details.get("oem") or item_data.get("oem") or ""
            model_name = car_details.get("modelName") or car_details.get("model") or ""
            variant_name = car_details.get("variantName") or ""
            myear = car_details.get("modelYear") or item_data.get("myear")

            title_parts = []
            if myear:
                title_parts.append(str(myear))
            if oem and not model_name.startswith(oem):
                title_parts.append(oem)
            if model_name:
                title_parts.append(model_name)
            if variant_name and variant_name not in model_name:
                title_parts.append(variant_name)

            title = " ".join(title_parts).strip() if title_parts else (seo.get("title") or f"CarDekho Car #{car_id}")

            price_val = 0
            raw_price = item_data.get("price") or item_data.get("pu") or car_details.get("price")
            if isinstance(raw_price, (int, float)):
                price_val = int(raw_price)
            elif isinstance(raw_price, str):
                digits = re.sub(r"[^\d]", "", raw_price)
                if digits:
                    price_val = int(digits)

            formatted_price = item_data.get("pn") or car_details.get("price") or (f"₹ {price_val:,}" if price_val else "")

            km_str = str(car_details.get("km") or item_data.get("km") or "")
            km_digits = re.sub(r"[^\d]", "", km_str)
            km_val = int(km_digits) if km_digits else None

            fuel = car_details.get("ft") or item_data.get("fuelType")
            transmission = car_details.get("transmission") or item_data.get("transmission")
            body_type = car_details.get("bt") or item_data.get("bodyType")
            owner_raw = car_details.get("owner") or item_data.get("owner") or "1st Owner"
            rto = car_details.get("rtoCode") or item_data.get("rto")

            city = item_data.get("cityName") or item_data.get("city")
            locality = item_data.get("locality") or item_data.get("loc")

            images: List[ImageModel] = []
            seen_urls = set()

            primary_url = item_data.get("pi")
            if primary_url:
                images.append(ImageModel(url=str(primary_url), position=0))
                seen_urls.add(primary_url)

            if isinstance(gallery_dto, dict):
                tabs = gallery_dto.get("tabs", [])
                for tab in tabs:
                    img_list = tab.get("list", [])
                    if isinstance(img_list, list):
                        for idx, img_entry in enumerate(img_list):
                            img_url = img_entry if isinstance(img_entry, str) else img_entry.get("url")
                            if img_url and img_url not in seen_urls:
                                seen_urls.add(img_url)
                                images.append(ImageModel(url=img_url, position=len(images)))

            parameters: List[ParameterModel] = []
            if myear:
                parameters.append(ParameterModel(key="brand", value=str(oem)))
                parameters.append(ParameterModel(key="model", value=str(model_name)))
                parameters.append(ParameterModel(key="year", value=str(myear)))
            if km_str:
                parameters.append(ParameterModel(key="km", value=str(km_val) if km_val else km_str))
            if fuel:
                parameters.append(ParameterModel(key="fuel_type", value=str(fuel)))
            if transmission:
                parameters.append(ParameterModel(key="transmission", value=str(transmission)))
            if owner_raw:
                parameters.append(ParameterModel(key="owner", value=str(owner_raw)))
            if body_type:
                parameters.append(ParameterModel(key="body_type", value=str(body_type)))
            if rto:
                parameters.append(ParameterModel(key="rto", value=str(rto)))
            if formatted_price:
                parameters.append(ParameterModel(key="formatted_price", value=formatted_price))

            if isinstance(car_overview, dict):
                top_items = car_overview.get("top", [])
                for top in top_items:
                    k = top.get("key")
                    v = top.get("value")
                    if k and v:
                        parameters.append(ParameterModel(key=str(k), value=str(v)))

            dealer_id = item_data.get("dlId") or item_data.get("dealerId") or item_data.get("storeId")
            seller = SellerModel(
                name=item_data.get("franchiseName") or "CarDekho Verified Seller" if dealer_id else "Verified Car Owner",
                type="dealer" if dealer_id else "individual",
                contact_available=bool(item_data.get("leadForm") or dealer_id),
            )

            desc_lines = [
                f"{title} in {city or 'India'}.",
                f"Price: {formatted_price}",
                f"Driven: {km_str} km | Fuel: {fuel} | Transmission: {transmission} | Ownership: {owner_raw}",
            ]
            if locality:
                desc_lines.append(f"Location: {locality}, {city}")
            if rto:
                desc_lines.append(f"RTO: {rto}")
            if item_data.get("overAllScore"):
                desc_lines.append(f"Inspection Score: {item_data.get('overAllScore')}/10")

            description = "\n".join(desc_lines)

            raw_payload = dict(item_data)
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
                "rto": rto,
                "formatted_price": formatted_price,
            }

            return ListingItem(
                id=f"cardekho-{car_id}",
                title=title,
                description=description,
                url=url,
                price=PriceModel(
                    value=price_val,
                    currency="INR",
                    negotiable=False,
                ),
                category={"id": "automobile", "slug": "cars", "name": "Cars"},
                subcategory={"id": "cars", "slug": "used-cars", "name": "Used Cars"},
                location=LocationModel(
                    city=city,
                    locality=locality,
                    state=None,
                    pincode=None,
                ),
                seller=seller,
                images=images,
                parameters=parameters,
                raw_data=raw_payload,
            )
        except Exception as e:
            logger.warning(f"Error parsing CarDekho detail state: {str(e)}")
            return None

    def _extract_detail_from_dom(self, html_content: str, clean_id: str, url: str) -> Optional[ListingItem]:
        parser = HTMLParser(html_content)
        title_node = parser.css_first("h1") or parser.css_first(".heading") or parser.css_first("title")
        title = title_node.text().strip() if title_node else f"CarDekho Car #{clean_id}"

        price_node = parser.css_first(".price") or parser.css_first(".amount") or parser.css_first(".priceSaving")
        price_text = price_node.text().strip() if price_node else ""
        price_val = int(re.sub(r"[^\d]", "", price_text)) if re.search(r"\d", price_text) else 0

        images = []
        for idx, img in enumerate(parser.css("img")):
            src = img.attributes.get("src") or img.attributes.get("data-src")
            if src and "gaadi.com" in src and "usedcar" in src:
                images.append(ImageModel(
                    url=src,
                    position=idx,
                ))

        return ListingItem(
            id=f"cardekho-{clean_id}",
            title=title,
            description=f"{title} on CarDekho.",
            url=url,
            price=PriceModel(
                value=price_val,
                currency="INR",
                negotiable=False,
            ),
            category={"id": "automobile", "slug": "cars", "name": "Cars"},
            subcategory={"id": "cars", "slug": "used-cars", "name": "Used Cars"},
            location=LocationModel(city=None, locality=None, state=None, pincode=None),
            seller=SellerModel(name="CarDekho Seller", type="dealer", contact_available=True),
            images=images,
            parameters=[],
            raw_data={"source": "cardekho"},
        )

cardekho_detail_extractor = CardekhoDetailExtractor()
