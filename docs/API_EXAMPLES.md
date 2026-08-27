# Marketplace & Job Live Data Extraction API Documentation

Unified REST API for real-time normalized data extraction from **OLX India** (Classifieds, Electronics, Real Estate), **CarDekho** (Used Cars & Vehicles), and **Naukri.com** (IT & Corporate Jobs).

---

## 1. Quick Access & Interactive Docs

- **Web UI Dashboard**: [http://localhost:8085/](http://localhost:8085/)
- **Swagger / OpenAPI UI**: [http://localhost:8085/api/docs](http://localhost:8085/api/docs)
- **OpenAPI 3.1 Spec JSON**: [http://localhost:8085/api/openapi.json](http://localhost:8085/api/openapi.json)

### Authentication
All protected endpoints require an API Key supplied in either:
- Header: `X-API-Key: dev-local-api-key`
- Header: `Authorization: Bearer dev-local-api-key`

---

## 2. Naukri.com Job Search Endpoints

### A. Search Live Tech & Corporate Jobs
```bash
curl -H "X-API-Key: dev-local-api-key" \
  "http://localhost:8085/api/v1/naukri/listings?city=Bangalore&category=jobs&limit=50&sort=newest"
```

### B. Filter by Specific Job Role / Domain
Available roles: `software-engineer`, `data-scientist`, `devops-engineer`, `product-manager`, `frontend-developer`, `backend-developer`, `full-stack-developer`, `qa-engineer`, `marketing`, `sales`, `hr`, `finance`.

```bash
curl -H "X-API-Key: dev-local-api-key" \
  "http://localhost:8085/api/v1/naukri/listings?category=software-engineer&city=Pune&limit=25"
```

### C. Filter for Fresher / 0 Yrs Experience
```bash
curl -H "X-API-Key: dev-local-api-key" \
  "http://localhost:8085/api/v1/naukri/listings?city=Bangalore&experience=0&limit=20"
```

### D. Filter by Experience & Strict Salary Range (in INR)
Example: 2 Years Experience, Salary between ₹6,00,000 (6 LPA) and ₹20,00,000 (20 LPA):

```bash
curl -H "X-API-Key: dev-local-api-key" \
  "http://localhost:8085/api/v1/naukri/listings?keyword=python&city=Bangalore&experience=2&min_price=600000&max_price=2000000&limit=15"
```

### E. Job Detail by Listing ID
```bash
curl -H "X-API-Key: dev-local-api-key" \
  "http://localhost:8085/api/v1/naukri/listings/naukri-260826009946"
```

### F. Job Detail by Direct Naukri URL
```bash
curl -G -H "X-API-Key: dev-local-api-key" \
  --data-urlencode "url=https://www.naukri.com/job-listings-python-developer-cgi-bengaluru-4-to-5-years-260826018602" \
  "http://localhost:8085/api/v1/naukri/listing"
```

---

## 3. CarDekho Used Car Endpoints

### A. Search Used Cars by City & Brand
```bash
curl -H "X-API-Key: dev-local-api-key" \
  "http://localhost:8085/api/v1/cardekho/listings?city=delhi-ncr&brand=maruti&model=swift&limit=20"
```

### B. Filter by Price, Year, Fuel & Transmission
```bash
curl -H "X-API-Key: dev-local-api-key" \
  "http://localhost:8085/api/v1/cardekho/listings?city=mumbai&fuel_type=petrol&transmission=automatic&min_price=500000&max_price=1200000&limit=20"
```

### C. Car Detail by Direct URL
```bash
curl -G -H "X-API-Key: dev-local-api-key" \
  --data-urlencode "url=https://www.cardekho.com/used-car-details/used-Maruti-Swift-VXI-cars-Delhi-NCR_1823627.htm" \
  "http://localhost:8085/api/v1/cardekho/listing"
```

---

## 4. OLX India Classifieds Endpoints

### A. Search Classifieds (Cars, Bikes, Mobile Phones, Real Estate)
```bash
# Mobile Phones in Mumbai
curl -H "X-API-Key: dev-local-api-key" \
  "http://localhost:8085/api/v1/olx/listings?category=mobile-phones&city=Mumbai&keyword=iPhone&limit=50"

# Real Estate in Bangalore
curl -H "X-API-Key: dev-local-api-key" \
  "http://localhost:8085/api/v1/olx/listings?category=real-estate&city=Bangalore&limit=50"
```

### B. OLX Item Detail Lookup
```bash
curl -H "X-API-Key: dev-local-api-key" \
  "http://localhost:8085/api/v1/olx/listings/1823627262"
```

---

## 5. Cache Control (`fresh=1`)

By default, responses are cached in Redis (TTL: 300 seconds) for ultra-fast latency (1–3ms). To bypass the cache and extract fresh live data in real time, append `fresh=1` or `fresh=true`:

```bash
curl -H "X-API-Key: dev-local-api-key" \
  "http://localhost:8085/api/v1/naukri/listings?city=Bangalore&keyword=python&fresh=1"
```

---

## 6. Health & System Metrics

### Health Check (No Auth Required)
```bash
curl "http://localhost:8085/api/v1/health"
```

### Admin Usage & Rate Limits (Requires Admin API Key)
```bash
curl -H "X-API-Key: dev-local-api-key" \
  "http://localhost:8085/api/v1/admin/usage?limit=50"
```

---

## 7. Standard JSON Response Format

```json
{
  "success": true,
  "source": {
    "platform": "naukri",
    "country": "IN"
  },
  "request": {
    "category": "jobs",
    "city": "Bangalore",
    "experience": 0,
    "min_price": 300000,
    "max_price": 600000,
    "limit": 10,
    "page": 1
  },
  "pagination": {
    "page": 1,
    "limit": 10,
    "has_next": false
  },
  "data": [
    {
      "listing_id": "naukri-260826009946",
      "listing_url": "https://www.naukri.com/job-listings-...",
      "title": "Fresher International Voice Process",
      "description": "...",
      "category": "jobs",
      "subcategory": "tech-jobs",
      "price": {
        "amount": 325000,
        "currency": "INR",
        "negotiable": false
      },
      "seller": {
        "name": "Conduent",
        "type": "employer",
        "contact_available": true
      },
      "location": {
        "city": "Bengaluru",
        "locality": "Bengaluru"
      },
      "job": {
        "company_name": "Conduent",
        "experience_required": "0-2 Yrs",
        "salary_text": "3.25-4.5 Lacs PA",
        "skills": ["Voice Process", "BPO", "Customer Support"],
        "company_rating": 3.4,
        "posted_age": "1 day ago",
        "apply_url": "https://www.naukri.com/job-listings-..."
      }
    }
  ]
}
```
