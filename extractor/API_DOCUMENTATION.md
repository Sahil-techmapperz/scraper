# Marketplace Data Extractor — API & Technical Documentation

> **High-Performance Multi-Source Marketplace Scraper & Extractor Microservice**  
> Engine: **FastAPI (Python 3.10+)** | Async Architecture | Port: `8000`  
> Upstream Consumers: **CodeIgniter 4 API Gateway** (`:8085`) & **XYZFinders Next.js App** (`:3000`)

---

## 1. System Overview & Architecture

The **Marketplace Data Extractor** is an async Python microservice responsible for real-time scraping, extraction, and data normalization across four Indian online marketplaces:

```
┌────────────────────────────────────────────────────────┐
│                   XYZFinders Web / App                 │
│                 (Next.js / React Frontend)             │
└──────────────────────────┬─────────────────────────────┘
                           │ HTTP REST
┌──────────────────────────▼─────────────────────────────┐
│             CodeIgniter 4 API Gateway (:8085)          │
│        (Auth, Rate-Limiting, Caching, Logging)         │
└──────────────────────────┬─────────────────────────────┘
                           │ HTTP REST (Internal Network)
┌──────────────────────────▼─────────────────────────────┐
│          Marketplace Python Extractor (:8000)          │
│               [FastAPI + curl_cffi Engine]             │
├────────────────────────────────────────────────────────┤
│  ⚡ In-Memory TTL Cache (< 10ms repeat responses)       │
│  🔄 Persistent Async HTTP Connection Pool (Keep-Alive) │
│  🦥 Lazy-Loaded Playwright Stealth Browser (Fallback)  │
└──────┬───────────────┬────────────────┬──────────────┬─┘
       │               │                │              │
┌──────▼─────┐  ┌──────▼──────┐  ┌──────▼─────┐  ┌─────▼──────┐
│  OLX India │  │  CarDekho   │  │ Naukri.com │  │ Cashify.in │
│ (10 Active │  │  (Used &    │  │ (Jobs &    │  │(Refurbished│
│ Categories)│  │  New Cars)  │  │ Careers)   │  │  Gadgets)  │
└────────────┘  └─────────────┘  └────────────┘  └────────────┘
```

---

## 2. Active Marketplace Categories

### 🚗 2-in-1 Unified Automobile Category
Cars and Bikes are combined under a single **`automobile`** category:
- **`category=automobile`** (without subcategory): Concurrently fetches **Cars** (OLX ID `84`) and **Bikes** (OLX ID `81`) in parallel and interleaves them (`[Car 1, Bike 1, Car 2, Bike 2...]`) for a balanced mix.
- **`category=automobile&subcategory=cars`** or **`category=cars`**: Returns only Cars.
- **`category=automobile&subcategory=bikes`** or **`category=bikes`**: Returns only Motorcycles & Bikes.

### 📋 OLX India — 10 Active Categories
All 10 active categories use verified live OLX internal category IDs for high-speed direct JSON API extraction:

| Category Slug | OLX ID | Display Label | Sample Items |
|:---|:---:|:---|:---|
| **`automobile`** | `84` + `81` | Cars & Automobiles (Cars & Bikes) | Interleaved Cars & Motorcycles |
| **`cars`** | `84` | Cars | Mercedes-Benz, Swift, Creta, Fortuner |
| **`bikes`** | `81` | Motorcycles & Bikes | Royal Enfield, Pulsar, Apache, Ninja |
| **`properties`** | `3` | Properties & Real Estate | 2 BHK Flats, Houses, Lands & Plots |
| **`electronics`** | `99` | Electronics & Appliances | Washing Machines, Fridges, TVs, ACs |
| **`mobiles`** | `1411` | Mobiles & Tablets | iPhones, Samsung Galaxies, Tablets |
| **`jobs`** | `4` | Jobs & Employment | Office Assistant, Telecaller, Delivery |
| **`furniture`** | `628` | Furniture & Decor | Sofas, Double Beds, Almirahs, Dining |
| **`fashion`** | `87` | Fashion & Clothing | Watches, Shoes, Clothing, Belts |
| **`pets`** | `103` | Pets & Accessories | Dogs, Fish Aquariums, Pet Beds |
| **`services`** | `619` | Services | Packers & Movers, Repairs, Rentals |

> 🚫 **Excluded Categories:**  
> - **Commercial Vehicles & Spares** (`2207`) — Omitted  
> - **Books, Sports & Hobbies** (`767`) — Omitted  

---

## 3. Endpoints Reference

Base URL: `http://127.0.0.1:8000`

### 3.1 Health Check

```http
GET /health
```
**Response (200 OK):**
```json
{
  "status": "healthy",
  "service": "marketplace-python-extractor",
  "sources": ["olx", "cardekho", "naukri", "cashify"],
  "browser_fallback_enabled": true
}
```

---

### 3.2 OLX India Endpoints

#### Search Listings
```http
GET /olx/listings
GET /listings
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|:---|:---:|:---:|:---|
| `category` | string | `null` | Category slug (`automobile`, `cars`, `bikes`, `properties`, `electronics`, `mobiles`, `jobs`, `furniture`, `fashion`, `pets`, `services`) |
| `subcategory` | string | `null` | Optional subcategory filter (e.g. `cars`, `bikes`, `scooters`) |
| `keyword` | string | `null` | Free-text keyword search |
| `city` | string | `null` | City name (e.g. `Delhi`, `Bangalore`, `Mumbai`, `Kolkata`) |
| `state` | string | `null` | State name |
| `min_price` | integer | `null` | Minimum price in INR |
| `max_price` | integer | `null` | Maximum price in INR |
| `sort` | string | `"newest"` | Sorting: `newest`, `oldest`, `price_low_to_high`, `price_high_to_low` |
| `page` | integer | `1` | Page number (1-indexed) |
| `limit` | integer | `50` | Results limit (1 to 300) |
| `brand` | string | `null` | Vehicle or device brand filter |
| `model` | string | `null` | Model name filter |
| `fuel_type` | string | `null` | Fuel type: `petrol`, `diesel`, `cng`, `electric`, `hybrid` |
| `transmission`| string | `null` | Transmission: `manual`, `automatic` |
| `min_year` | integer | `null` | Manufacturing year min (e.g. `2018`) |
| `max_year` | integer | `null` | Manufacturing year max |
| `min_km` | integer | `null` | Minimum odometer reading |
| `max_km` | integer | `null` | Maximum odometer reading |
| `bhk` | integer | `null` | Number of bedrooms (e.g. `2`, `3`) |
| `url` | string | `null` | Direct OLX URL (single-item lookup shortcut) |

**Sample Request:**
```bash
curl -X GET "http://127.0.0.1:8000/olx/listings?category=automobile&city=Delhi&limit=10"
```

#### Get Listing Detail by ID
```http
GET /olx/listings/{listing_id}
GET /listings/{listing_id}
```
**Sample Request:**
```bash
curl -X GET "http://127.0.0.1:8000/olx/listings/1789234850"
```

---

### 3.3 CarDekho Endpoints

#### Search CarDekho Listings
```http
GET /cardekho/listings
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|:---|:---:|:---:|:---|
| `keyword` | string | `null` | Search query (e.g. `Swift`, `Creta`) |
| `city` | string | `null` | City name (e.g. `delhi`, `mumbai`, `bangalore`) |
| `brand` | string | `null` | Manufacturer (e.g. `maruti`, `hyundai`, `honda`) |
| `model` | string | `null` | Car model name |
| `min_price` | integer | `null` | Minimum price in INR |
| `max_price` | integer | `null` | Maximum price in INR |
| `min_year` | integer | `null` | Minimum manufacturing year |
| `max_year` | integer | `null` | Maximum manufacturing year |
| `fuel_type` | string | `null` | `petrol`, `diesel`, `cng`, `electric` |
| `transmission`| string | `null` | `manual`, `automatic` |
| `page` | integer | `1` | Page number |
| `limit` | integer | `20` | Results limit (1 to 300) |

**Sample Request:**
```bash
curl -X GET "http://127.0.0.1:8000/cardekho/listings?city=delhi&brand=hyundai&limit=10"
```

#### Get CarDekho Listing by ID or URL
```http
GET /cardekho/listings/{listing_id}
GET /cardekho/listing?url={encoded_cardekho_url}
```

---

### 3.4 Naukri.com Endpoints

#### Search Job Postings
```http
GET /naukri/listings
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|:---|:---:|:---:|:---|
| `keyword` | string | `null` | Tech stack, role, or title (e.g. `Python React`, `DevOps`) |
| `city` | string | `null` | Location (e.g. `Bangalore`, `Hyderabad`, `Pune`) |
| `experience` | integer | `null` | Years of experience (e.g. `0`, `3`, `5`) |
| `min_experience`| integer | `null` | Minimum experience (years) |
| `max_experience`| integer | `null` | Maximum experience (years) |
| `company` | string | `null` | Target hiring company |
| `skills` | string | `null` | Required skills |
| `page` | integer | `1` | Page number |
| `limit` | integer | `20` | Results limit |

**Sample Request:**
```bash
curl -X GET "http://127.0.0.1:8000/naukri/listings?keyword=python&city=Bangalore&limit=10"
```

---

### 3.5 Cashify.in Endpoints

#### Search Refurbished Gadgets
```http
GET /cashify/listings
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|:---|:---:|:---:|:---|
| `category` | string | `"mobiles"` | `smartphones`, `laptops`, `smartwatches`, `tablets`, `accessories` |
| `brand` | string | `null` | `apple`, `samsung`, `oneplus`, `xiaomi`, `google` |
| `keyword` | string | `null` | Product name (e.g. `iPhone 13`, `MacBook Air`) |
| `city` | string | `null` | City / location |
| `min_price` | integer | `null` | Minimum price in INR |
| `max_price` | integer | `null` | Maximum price in INR |
| `page` | integer | `1` | Page number |
| `limit` | integer | `20` | Results limit |

**Sample Request:**
```bash
curl -X GET "http://127.0.0.1:8000/cashify/listings?category=smartphones&brand=apple&limit=5"
```

---

## 4. Response Schemas

### 4.1 Search Response Schema (`SearchResponse`)

```json
{
  "data": [
    {
      "id": "1789234850",
      "title": "Mercedes-Benz E-Class E 220 d, 2018, Diesel",
      "description": "First owner, company serviced, pristine condition...",
      "url": "https://www.olx.in/item/iid-1789234850",
      "price": {
        "value": 2490000,
        "currency": "INR",
        "negotiable": true
      },
      "category": {
        "slug": "automobile",
        "name": "Automobiles"
      },
      "location": {
        "state": "Delhi",
        "city": "Delhi",
        "locality": "Vasant Vihar",
        "pincode": "110057"
      },
      "seller": {
        "name": "Rajesh Kumar",
        "type": "owner",
        "contact_available": true
      },
      "images": [
        {
          "url": "https://apollo.olx.in/v1/files/hot2v0h3zj7e3-PANAMERA/image;original=true",
          "position": 0
        }
      ],
      "created_at": "2026-09-12T10:30:00Z",
      "updated_at": "2026-09-14T06:15:00Z",
      "parameters": [
        {"key": "year", "value": "2018"},
        {"key": "fuel", "value": "Diesel"},
        {"key": "kms_driven", "value": "45000"}
      ]
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 50,
    "has_next": true,
    "total_records": 50
  }
}
```

---

## 5. Performance Benchmarks

All benchmarks measured against live endpoints with concurrent parallel fetching (`asyncio.gather`):

| Test Suite | Conditions | Extracted Items | Total Latency |
|:---|:---:|:---:|:---:|
| **OLX All 10 Categories** | 50 items/category, parallel | **500 items** | **885.6 ms** |
| **Combined 2-in-1 Automobile** | Cars + Bikes interleaved | **50 items** | **448.1 ms** |
| **OLX 300 Records (Max Bulk)** | 8 pages parallel | **300 items** | **825.3 ms** |
| **CarDekho 300 Records** | 15 pages parallel | **299 items** | **555.5 ms** |
| **Cashify Refurbished Gadgets** | Direct HTTP RSC parsing | **19 items** | **110.9 ms** |
| **Cache Hit (Repeat Search)** | In-Memory TTL Cache | **Same Dataset** | **8.06 ms** |

---

## 6. How to Run Locally

### Start Python Extractor
```powershell
cd d:\xyzfinders_web\scraper
.\extractor\.venv\Scripts\python.exe -m uvicorn extractor.main:app --host 127.0.0.1 --port 8000 --reload
```

### Start CodeIgniter 4 API Gateway
```powershell
cd d:\xyzfinders_web\scraper
php spark serve --port 8085
```

### Access Points
- **Extractor Live API:** `http://127.0.0.1:8000`
- **Swagger Interactive Docs:** `http://127.0.0.1:8000/docs`
- **ReDoc Interactive Docs:** `http://127.0.0.1:8000/redoc`
- **Visual API Overview Page:** `http://127.0.0.1:8000/api-docs`
- **CI4 Explorer Dashboard:** `http://localhost:8085/`
- **Next.js Frontend:** `http://localhost:3000/`
