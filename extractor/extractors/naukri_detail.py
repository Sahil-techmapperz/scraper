import json
import re
import logging
from typing import Optional, Dict, Any, List
from selectolax.parser import HTMLParser

from extractor.config import settings
from extractor.models.request import SearchQueryParams
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
from extractor.extractors.naukri_search import naukri_search_extractor

logger = logging.getLogger(__name__)

class NaukriDetailExtractor:
    async def get_detail(self, listing_id: str, listing_url: Optional[str] = None) -> DetailResponse:
        clean_id = listing_id.replace("naukri-", "").strip()
        target_url = listing_url

        if not target_url:
            if clean_id:
                target_url = f"{settings.NAUKRI_BASE_URL.rstrip('/')}/job-listings-{clean_id}"

        if not target_url:
            return DetailResponse(data=None)

        logger.info(f"Fetching Naukri detail for ID '{clean_id}' from URL: {target_url}")

        html_content = None
        headers = {
            "appid": "109",
            "systemid": "Naukri",
            "clientid": "d3wt17vis001",
        }
        try:
            response = await http_client.get(target_url, headers=headers)
            if response and response.status_code == 200:
                html_content = response.text
        except Exception as e:
            logger.warning(f"HTTP fetch failed for Naukri detail {target_url}: {str(e)}")

        if not html_content or ("styles_jdr__" not in html_content and "__INITIAL_STATE__" not in html_content and "initialState" not in html_content and "job-desc" not in html_content):
            if settings.ENABLE_BROWSER_FALLBACK:
                logger.info(f"Triggering browser fallback for Naukri detail URL: {target_url}")
                html_content = await browser_manager.fetch_page_content(target_url)

        item = None
        if html_content:
            item = self._extract_detail_from_initial_state(html_content, clean_id, target_url)
            if not item:
                item = self._extract_detail_from_dom(html_content, clean_id, target_url)

        return DetailResponse(data=item)

    def _extract_detail_from_initial_state(self, html_content: str, clean_id: str, url: str) -> Optional[ListingItem]:
        match = re.search(r"window\.(?:__INITIAL_STATE__|initialState)\s*=\s*(\{.+?\});\s*(?:window\.|\n|<)", html_content, re.DOTALL)
        if not match:
            match = re.search(r"window\.(?:__INITIAL_STATE__|initialState)\s*=\s*(\{.+?\});", html_content, re.DOTALL)

        if not match:
            return None

        try:
            state = json.loads(match.group(1))
            job_details = state.get("jobDetails") or state.get("jobDetail") or state.get("job")
            if isinstance(job_details, dict):
                return naukri_search_extractor.normalize_raw_job(job_details)
        except Exception as e:
            logger.warning(f"Error parsing Naukri detail state: {str(e)}")

        return None

    def _extract_detail_from_dom(self, html_content: str, clean_id: str, url: str) -> Optional[ListingItem]:
        parser = HTMLParser(html_content)
        title_node = parser.css_first("h1.styles_jd-header-title__, h1.title, h1")
        title = title_node.text().strip() if title_node else f"Naukri Job #{clean_id}"

        comp_node = parser.css_first("div.styles_jd-header-comp-name__, a.comp-name, [class*='company-name']")
        company = comp_node.text().strip() if comp_node else "Hiring Company"

        exp_node = parser.css_first("div.styles_jhc__exp__, [class*='experience'], [class*='exp']")
        exp_text = exp_node.text().strip() if exp_node else "0-5 Yrs"

        sal_node = parser.css_first("div.styles_jhc__salary__, [class*='salary'], [class*='sal']")
        salary_text = sal_node.text().strip() if sal_node else "Not Disclosed"

        loc_node = parser.css_first("div.styles_jhc__loc__, [class*='location'], [class*='loc']")
        location_text = loc_node.text().strip() if loc_node else "India"

        desc_node = parser.css_first("div.styles_JDC__desc__, [class*='job-desc'], [class*='description'], section.job-desc")
        desc = desc_node.text().strip() if desc_node else f"{title} at {company} in {location_text}."

        tags = []
        for tag_node in parser.css("a.styles_chip__, [class*='key-skill'] a, ul.tags-gt li"):
            tag_t = tag_node.text().strip()
            if tag_t and tag_t not in tags:
                tags.append(tag_t)

        salary_num = None
        sal_m = re.findall(r"(\d+(?:\.\d+)?)", salary_text)
        if sal_m:
            salary_num = int(float(sal_m[0]) * 100000) if "Lac" in salary_text or "Lacs" in salary_text else int(float(sal_m[0]))

        params_list = [
            ParameterModel(key="company", value=company),
            ParameterModel(key="experience", value=exp_text),
            ParameterModel(key="salary_text", value=salary_text),
            ParameterModel(key="location", value=location_text),
            ParameterModel(key="skills", value=", ".join(tags) if tags else ""),
        ]

        raw_payload = {
            "source": "naukri",
            "job_id": clean_id,
            "title": title,
            "company": company,
            "experience": exp_text,
            "salary": salary_text,
            "location": location_text,
            "skills": tags,
            "job_url": url,
            "job": {
                "company_name": company,
                "experience_required": exp_text,
                "salary_text": salary_text,
                "skills": tags,
                "apply_url": url,
            },
        }

        return ListingItem(
            id=f"naukri-{clean_id}" if clean_id else "naukri-job-detail",
            title=title,
            description=desc,
            url=url,
            price=PriceModel(
                value=salary_num,
                currency="INR",
                negotiable=False,
            ),
            category={"id": "jobs", "slug": "jobs", "name": "Jobs"},
            subcategory={"id": "corporate", "slug": "corporate-jobs", "name": "Corporate Jobs"},
            location=LocationModel(
                city=location_text.split(",")[0].strip() if location_text else "India",
                locality=location_text,
            ),
            seller=SellerModel(
                name=company,
                type="employer",
                contact_available=True,
            ),
            images=[],
            parameters=params_list,
            raw_data=raw_payload,
        )

naukri_detail_extractor = NaukriDetailExtractor()
