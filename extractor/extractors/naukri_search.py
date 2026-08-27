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
    "noida": "noida",
    "gurgaon": "gurgaon",
    "gurugram": "gurgaon",
    "bangalore": "bangalore",
    "bengaluru": "bangalore",
    "hyderabad": "hyderabad",
    "chennai": "chennai",
    "mumbai": "mumbai",
    "navi mumbai": "mumbai",
    "pune": "pune",
    "kolkata": "kolkata",
    "ahmedabad": "ahmedabad",
    "jaipur": "jaipur",
    "chandigarh": "chandigarh",
    "kochi": "kochi",
    "indore": "indore",
    "lucknow": "lucknow",
    "remote": "work-from-home",
    "wfh": "work-from-home",
}

ROLE_SLUGS = {
    "software-engineer": "software-engineer",
    "python": "python-developer",
    "python-developer": "python-developer",
    "java": "java-developer",
    "java-developer": "java-developer",
    "full-stack": "full-stack-developer",
    "full-stack-developer": "full-stack-developer",
    "frontend": "frontend-developer",
    "frontend-developer": "frontend-developer",
    "backend": "backend-developer",
    "backend-developer": "backend-developer",
    "data-scientist": "data-scientist",
    "data-analyst": "data-analyst",
    "devops": "devops-engineer",
    "devops-engineer": "devops-engineer",
    "qa": "qa-engineer",
    "product-manager": "product-manager",
    "marketing": "marketing",
    "sales": "sales",
    "hr": "hr",
    "finance": "finance",
}

class NaukriSearchExtractor:
    def build_url(self, params: SearchQueryParams, page_override: Optional[int] = None) -> str:
        base = settings.NAUKRI_BASE_URL.rstrip("/")
        page_num = page_override or params.page

        # Resolve keywords / role slug
        kw = (params.keyword or params.subcategory or params.category or "").strip().lower()
        kw_slug = ROLE_SLUGS.get(kw, kw.replace(" ", "-")) if kw and kw != "jobs" else ""

        # Resolve location slug
        loc_slug = ""
        if params.city:
            clean_city = params.city.strip().lower()
            loc_slug = CITY_SLUGS.get(clean_city, clean_city.replace(" ", "-"))

        # Handle Fresher (experience=0) URL routing on Naukri
        if params.experience == 0 or params.min_experience == 0:
            if kw_slug:
                kw_slug = f"fresher-{kw_slug}"
            else:
                kw_slug = "fresher"

        if kw_slug and loc_slug:
            url_path = f"{base}/{kw_slug}-jobs-in-{loc_slug}"
        elif kw_slug:
            url_path = f"{base}/{kw_slug}-jobs"
        elif loc_slug:
            url_path = f"{base}/jobs-in-{loc_slug}"
        else:
            url_path = f"{base}/jobs-in-india"

        query_dict: Dict[str, Any] = {}
        if params.keyword and not kw_slug:
            query_dict["k"] = params.keyword
        if page_num > 1:
            query_dict["pageNo"] = page_num
        if params.experience is not None and params.experience > 0:
            query_dict["experience"] = params.experience
        elif params.min_experience is not None and params.min_experience > 0:
            query_dict["experience"] = params.min_experience

        if query_dict:
            return f"{url_path}?{urlencode(query_dict)}"
        return url_path

    async def fetch_single_page(self, url: str) -> List[ListingItem]:
        html_content = None
        headers = {
            "appid": "109",
            "systemid": "Naukri",
            "clientid": "d3wt17vis001",
        }
        try:
            response = await http_client.get(url, headers=headers)
            if response and response.status_code == 200:
                html_content = response.text
        except Exception as e:
            logger.warning(f"HTTP fetch failed for Naukri {url}: {str(e)}")

        items: List[ListingItem] = []
        if html_content:
            # 1. State Extraction
            items = self._extract_items_from_initial_state(html_content)
            # 2. JSON-LD Extraction
            if not items:
                items = self._extract_items_from_json_ld(html_content)
            # 3. DOM Extraction
            if not items:
                items = self._extract_items_from_dom(html_content)

        if not items and settings.ENABLE_BROWSER_FALLBACK:
            logger.info(f"Triggering stealth browser fallback for Naukri URL: {url}")
            browser_html = await browser_manager.fetch_page_content(
                url,
                wait_selector="div.srp-jobtuple-wrapper, div.cust-job-tuple, article.jobTuple, div[data-job-id]",
                wait_ms=3500
            )
            if browser_html:
                items = self._extract_items_from_dom(browser_html)
                if not items:
                    items = self._extract_items_from_initial_state(browser_html)

        return items

    async def search(self, params: SearchQueryParams) -> SearchResponse:
        # Naukri serves ~20 items per page
        limit = min(300, max(1, params.limit))
        pages_needed = math.ceil(limit / 20)
        start_page = max(1, params.page)

        urls = [self.build_url(params, page_override=p) for p in range(start_page, start_page + pages_needed)]
        logger.info(f"Extracting Naukri {len(urls)} pages for limit={limit}: {urls[0]}")

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
        match = re.search(r"window\.(?:__INITIAL_STATE__|initialState)\s*=\s*(\{.+?\});\s*(?:window\.|\n|<)", html_content, re.DOTALL)
        if not match:
            match = re.search(r"window\.(?:__INITIAL_STATE__|initialState)\s*=\s*(\{.+?\});", html_content, re.DOTALL)

        if not match:
            return []

        try:
            state = json.loads(match.group(1))
            job_details = state.get("jobDetails", []) or state.get("jobs", []) or state.get("searchResult", {}).get("jobDetails", [])
            if isinstance(job_details, list):
                for row in job_details:
                    if isinstance(row, dict):
                        item = self.normalize_raw_job(row)
                        if item:
                            items.append(item)
        except Exception as e:
            logger.warning(f"Failed parsing Naukri state: {str(e)}")

        return items

    def _extract_items_from_json_ld(self, html_content: str) -> List[ListingItem]:
        items: List[ListingItem] = []
        parser = HTMLParser(html_content)

        for script in parser.css('script[type="application/ld+json"]'):
            try:
                data = json.loads(script.text())
                if isinstance(data, dict) and data.get("@type") == "JobPosting":
                    item = self.normalize_raw_job(data)
                    if item:
                        items.append(item)
                elif isinstance(data, list):
                    for entry in data:
                        if isinstance(entry, dict) and entry.get("@type") == "JobPosting":
                            item = self.normalize_raw_job(entry)
                            if item:
                                items.append(item)
            except Exception:
                continue

        return items

    def _extract_items_from_dom(self, html_content: str) -> List[ListingItem]:
        items: List[ListingItem] = []
        parser = HTMLParser(html_content)

        # Select all job tuple wrappers
        tuples = parser.css("div.srp-jobtuple-wrapper, div.cust-job-tuple, article.jobTuple, div[data-job-id]")
        for idx, node in enumerate(tuples):
            try:
                title_node = node.css_first("a.title, a.job-title, [class*='title']")
                title = title_node.text().strip() if title_node else "Job Posting"
                job_url = title_node.attributes.get("href", "") if title_node else ""
                if job_url and job_url.startswith("/"):
                    job_url = f"{settings.NAUKRI_BASE_URL.rstrip('/')}{job_url}"

                job_id = node.attributes.get("data-job-id") or ""
                if not job_id and job_url:
                    id_m = re.search(r"-(\d+)\?", job_url) or re.search(r"(\d{6,})", job_url)
                    if id_m:
                        job_id = id_m.group(1)
                if not job_id:
                    job_id = f"naukri-{idx+1}"

                comp_node = node.css_first("a.comp-name, a.company-name, [class*='comp-name'], [class*='companyName']")
                company = comp_node.text().strip() if comp_node else "Hiring Company"

                rating_node = node.css_first("span.rating, [class*='rating'], span.main-2")
                rating = rating_node.text().strip() if rating_node else None

                reviews_node = node.css_first("a.reviewCount, [class*='reviewCount'], span.review-count")
                reviews = reviews_node.text().strip() if reviews_node else None

                exp_node = node.css_first("span.expwdth, span.ni-job-tuple-icon-srp-experience + span, span.exp-wrap, span.experience")
                exp_text = exp_node.text().strip() if exp_node else ""
                
                months = ["jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec"]
                if not exp_text or any(m in exp_text.lower() for m in months) or not any(c.isdigit() or "fresh" in exp_text.lower() for c in exp_text):
                    for s in node.css("span"):
                        st = s.text().strip()
                        if ("yr" in st.lower() or "fresher" in st.lower()) and not any(m in st.lower() for m in months):
                            exp_text = st
                            break
                if not exp_text:
                    exp_text = "0-1 Yrs"

                sal_node = node.css_first("span.sal-wrap, [class*='salary'], [class*='sal']")
                salary_text = sal_node.text().strip() if sal_node else "Not Disclosed"

                loc_node = node.css_first("span.locWdth, [class*='loc'], span.loc-wrap, [class*='location']")
                location_text = loc_node.text().strip() if loc_node else "India"

                desc_node = node.css_first("span.job-desc, [class*='job-desc'], div.row4, [class*='description']")
                desc = desc_node.text().strip() if desc_node else f"{title} at {company} ({location_text})."

                tags = []
                for tag_node in node.css("ul.tags-gt li, [class*='tags'] li, [class*='tag']"):
                    tag_t = tag_node.text().strip()
                    if tag_t and tag_t not in tags:
                        tags.append(tag_t)

                date_node = node.css_first("span.date, [class*='job-post-day'], span.post-age, [class*='date']")
                posted_date = date_node.text().strip() if date_node else "Recently"

                # Parse salary numbers
                salary_num = None
                sal_m = re.findall(r"(\d+(?:\.\d+)?)", salary_text)
                if sal_m:
                    salary_num = int(float(sal_m[0]) * 100000) if "Lac" in salary_text or "Lacs" in salary_text or "PA" in salary_text else int(float(sal_m[0]))

                params_list = [
                    ParameterModel(key="company", value=company),
                    ParameterModel(key="experience", value=exp_text),
                    ParameterModel(key="salary_text", value=salary_text),
                    ParameterModel(key="location", value=location_text),
                    ParameterModel(key="posted_age", value=posted_date),
                    ParameterModel(key="skills", value=", ".join(tags) if tags else ""),
                ]
                if rating:
                    params_list.append(ParameterModel(key="rating", value=rating))
                if reviews:
                    params_list.append(ParameterModel(key="reviews", value=reviews))

                raw_payload = {
                    "source": "naukri",
                    "job_id": job_id,
                    "title": title,
                    "company": company,
                    "experience": exp_text,
                    "salary": salary_text,
                    "location": location_text,
                    "skills": tags,
                    "rating": rating,
                    "reviews": reviews,
                    "posted_date": posted_date,
                    "job_url": job_url,
                    "job": {
                        "company_name": company,
                        "experience_required": exp_text,
                        "salary_text": salary_text,
                        "skills": tags,
                        "rating": float(rating) if rating and re.match(r"^\d+(\.\d+)?$", rating) else None,
                        "reviews": reviews,
                        "posted_age": posted_date,
                        "apply_url": job_url,
                    },
                }

                items.append(ListingItem(
                    id=f"naukri-{job_id}",
                    title=title,
                    description=desc,
                    url=job_url or f"{settings.NAUKRI_BASE_URL}/job-listings-{job_id}",
                    price=PriceModel(
                        value=salary_num,
                        currency="INR",
                        negotiable=False,
                    ),
                    category={"id": "jobs", "slug": "jobs", "name": "Jobs"},
                    subcategory={"id": "tech", "slug": "tech-jobs", "name": "Technology Jobs"},
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
                ))
            except Exception as e:
                logger.warning(f"Error parsing Naukri DOM card: {str(e)}")
                continue

        return items

    def normalize_raw_job(self, row: dict) -> Optional[ListingItem]:
        job_id = str(row.get("jobId") or row.get("id") or row.get("identifier", {}).get("value") or "")
        title = row.get("title") or row.get("jobTitle") or ""
        if not title:
            return None

        company = row.get("companyName") or row.get("hiringOrganization", {}).get("name") or row.get("company", {}).get("name") or "Hiring Company"
        exp_text = str(row.get("experience") or row.get("experienceText") or "0-3 Yrs")
        salary_text = str(row.get("salary") or row.get("salaryText") or "Not Disclosed")
        
        loc_data = row.get("location") or row.get("jobLocation", {})
        if isinstance(loc_data, dict):
            loc_str = loc_data.get("label") or loc_data.get("address", {}).get("addressLocality") or "India"
        elif isinstance(loc_data, list):
            loc_str = ", ".join([str(l.get("label") or l) for l in loc_data if l])
        else:
            loc_str = str(loc_data or "India")

        job_url = row.get("jobUrl") or row.get("url") or row.get("applyUrl") or ""
        if job_url and job_url.startswith("/"):
            job_url = f"{settings.NAUKRI_BASE_URL.rstrip('/')}{job_url}"

        tags = row.get("tagsAndSkills") or row.get("skills") or row.get("keySkills") or []
        if isinstance(tags, str):
            tags = [t.strip() for t in tags.split(",") if t.strip()]

        desc = row.get("jobDescription") or row.get("description") or f"{title} at {company}."
        clean_desc = re.sub(r"<[^>]+>", " ", desc).strip()

        rating = str(row.get("companyRating") or row.get("rating") or "")
        reviews = str(row.get("companyReviews") or row.get("reviews") or "")
        posted_date = str(row.get("createdDate") or row.get("datePosted") or row.get("postedDate") or "Recently")

        salary_num = None
        sal_m = re.findall(r"(\d+(?:\.\d+)?)", salary_text)
        if sal_m:
            salary_num = int(float(sal_m[0]) * 100000) if "Lac" in salary_text or "Lacs" in salary_text else int(float(sal_m[0]))

        params_list = [
            ParameterModel(key="company", value=company),
            ParameterModel(key="experience", value=exp_text),
            ParameterModel(key="salary_text", value=salary_text),
            ParameterModel(key="location", value=loc_str),
            ParameterModel(key="posted_age", value=posted_date),
            ParameterModel(key="skills", value=", ".join(tags) if tags else ""),
        ]
        if rating:
            params_list.append(ParameterModel(key="rating", value=rating))
        if reviews:
            params_list.append(ParameterModel(key="reviews", value=reviews))

        raw_payload = {
            "source": "naukri",
            "job_id": job_id,
            "title": title,
            "company": company,
            "experience": exp_text,
            "salary": salary_text,
            "location": loc_str,
            "skills": tags,
            "rating": rating,
            "reviews": reviews,
            "posted_date": posted_date,
            "job_url": job_url,
            "job": {
                "company_name": company,
                "experience_required": exp_text,
                "salary_text": salary_text,
                "skills": tags,
                "rating": float(rating) if rating and re.match(r"^\d+(\.\d+)?$", rating) else None,
                "reviews": reviews,
                "posted_age": posted_date,
                "apply_url": job_url,
            },
        }

        return ListingItem(
            id=f"naukri-{job_id}" if job_id else f"naukri-job-{abs(hash(title+company)) % 1000000}",
            title=title,
            description=clean_desc,
            url=job_url or f"{settings.NAUKRI_BASE_URL}/job-listings-{job_id}",
            price=PriceModel(
                value=salary_num,
                currency="INR",
                negotiable=False,
            ),
            category={"id": "jobs", "slug": "jobs", "name": "Jobs"},
            subcategory={"id": "corporate", "slug": "corporate-jobs", "name": "Corporate Jobs"},
            location=LocationModel(
                city=loc_str.split(",")[0].strip() if loc_str else "India",
                locality=loc_str,
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

    def _filter_items(self, items: List[ListingItem], params: SearchQueryParams) -> List[ListingItem]:
        filtered = []
        kw = params.keyword.lower().strip() if params.keyword else None
        comp_kw = params.company.lower().strip() if params.company else None
        skill_kw = params.skills.lower().strip() if params.skills else None
        
        req_exp = params.experience if params.experience is not None else params.min_experience
        min_req_exp = params.min_experience
        max_req_exp = params.max_experience
        min_price = params.min_price
        max_price = params.max_price

        for it in items:
            job_info = (it.raw_data or {}).get("job", {})

            # 1. Keyword search (Title, Description, Company, Skills)
            if kw:
                skills_text = " ".join([str(s) for s in job_info.get("skills", [])])
                haystack = f"{it.title} {it.description or ''} {job_info.get('company_name', '')} {skills_text}".lower()
                if kw not in haystack:
                    continue

            # 2. Company search
            if comp_kw:
                comp_val = str(job_info.get("company_name") or (it.seller.name if it.seller else "")).lower()
                if comp_kw not in comp_val:
                    continue

            # 3. Skills search
            if skill_kw:
                skills_list = [str(s).lower() for s in job_info.get("skills", [])]
                if not any(skill_kw in s for s in skills_list):
                    continue

            # 4. Experience Filtering
            exp_text = str(job_info.get("experience_required") or "").strip()
            exp_text_lower = exp_text.lower()
            if req_exp is not None or min_req_exp is not None or max_req_exp is not None:
                is_fresher_job = "fresher" in exp_text_lower or "fresher" in it.title.lower() or "entry level" in exp_text_lower
                nums = [int(n) for n in re.findall(r"\b(\d+)\b", exp_text)]
                
                if req_exp == 0:
                    # Strict validation for 0 Yrs / Fresher:
                    # Minimum experience must be 0 (e.g. 0-1 Yrs, 0-2 Yrs, 0 Yrs) or explicitly contain "fresher"
                    # Listings requiring 1-3 Yrs, 2-5 Yrs, 5-10 Yrs, 4-9 Yrs are strictly rejected
                    if nums:
                        job_min_e = nums[0]
                        if job_min_e > 0 and not is_fresher_job:
                            continue
                    elif not is_fresher_job and not ("0" in exp_text):
                        continue
                else:
                    if nums:
                        if len(nums) == 1:
                            job_min_e = nums[0]
                            job_max_e = 99 if "+" in exp_text else nums[0]
                        else:
                            job_min_e = min(nums[0], nums[1])
                            job_max_e = max(nums[0], nums[1])

                        if req_exp is not None:
                            if not (job_min_e <= req_exp <= job_max_e):
                                continue
                        if min_req_exp is not None and job_max_e < min_req_exp:
                            continue
                        if max_req_exp is not None and job_min_e > max_req_exp:
                            continue
                    elif not is_fresher_job:
                        pass

            # 5. Salary Range (Min Price / Max Price) Filtering
            if min_price is not None or max_price is not None:
                sal_text = str(job_info.get("salary_text") or "").strip()
                sal_text_clean = sal_text.lower().replace(",", "")
                if not sal_text or "not disclose" in sal_text_clean:
                    continue

                raw_nums = re.findall(r"(\d+(?:\.\d+)?)", sal_text_clean)
                if raw_nums:
                    is_lacs_unit = any(term in sal_text_clean for term in ["lac", "lakh", "pa", "p.a.", "lpa"])
                    parsed_salaries = []
                    for n_str in raw_nums:
                        n_val = float(n_str)
                        if n_val < 100 and is_lacs_unit:
                            parsed_salaries.append(int(n_val * 100000))
                        else:
                            parsed_salaries.append(int(n_val))

                    if parsed_salaries:
                        job_min_sal = min(parsed_salaries)
                        job_max_sal = max(parsed_salaries)

                        if min_price is not None and job_min_sal < min_price:
                            continue
                        if max_price is not None and job_max_sal > max_price:
                            continue
                    else:
                        continue
                else:
                    continue

            filtered.append(it)

        if params.sort:
            if params.sort == "newest":
                filtered.sort(key=lambda x: str((x.raw_data or {}).get("job", {}).get("posted_age", "")), reverse=True)
            elif params.sort in ["price_high_to_low", "price_desc"]:
                filtered.sort(key=lambda x: (x.price is None or x.price.value is None, -(x.price.value if (x.price and x.price.value) else 0)))
            elif params.sort in ["price_low_to_high", "price_asc"]:
                filtered.sort(key=lambda x: (x.price is None or x.price.value is None, (x.price.value if (x.price and x.price.value) else float('inf'))))

        return filtered

naukri_search_extractor = NaukriSearchExtractor()
