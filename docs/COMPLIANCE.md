# Compliance, Data Governance & Source Access

This document outlines the data access mechanisms, compliance considerations, and operational governance for the OLX India Data Extraction API.

---

## 1. Operating Modes

The system provides flexible connectors configured via `OLX_CONNECTOR_MODE` in [`.env`](file:///e:/scraper/.env):

| Connector Mode | Mechanism | Intended Use Case |
| :--- | :--- | :--- |
| `authorized_http` *(Default)* | Connects to the high-performance Python Extractor Microservice (`http://127.0.0.1:8000`) or an enterprise licensed feed. | Real-time live extraction and production workloads. |
| `fixture` | Reads pre-recorded mock payloads from `tests/fixtures/olx/search.json`. | Automated unit tests, CI pipelines, and offline local development. |
| `disabled` | Returns `502 SOURCE_NOT_CONFIGURED`. | Safe fallback state when extraction is disabled. |

---

## 2. Responsible Data Extraction & Caching

To ensure responsible extraction and avoid unnecessary load on upstream platforms:

1. **Mandatory Caching**:
   * The API enforces deterministic SHA-256 query parameter caching via [`RequestCache`](file:///e:/scraper/app/Services/RequestCache.php) (`OLX_CACHE_TTL=300` seconds default).
   * Identical search queries return instantly from memory/cache without triggering upstream network calls.

2. **Client Rate Limiting & Quotas**:
   * Multi-tenant rate limits (`API_RATE_LIMIT=100` requests/minute) and monthly quotas (`API_MONTHLY_QUOTA=10000`) prevent runaway traffic spikes.

3. **Proxy Rotation for Large-Scale Ingestion**:
   * When performing large-scale historical indexing across multiple Indian states, configure a rotating residential proxy in `extractor/config.py` (`EXTRACTOR_PROXY_URL=...`) to distribute network requests.

---

## 3. Privacy & Data Protection (DPDP Act Compliance)

Under India's Digital Personal Data Protection (DPDP) Act and international data privacy frameworks:

* **Contact Information**: Personal telephone numbers and private email addresses should not be harvested, redistributed, or stored without explicit consent.
* **Public Domain Data**: Only publicly listed vehicle, real estate, electronic item specifications, asking prices, and general city/locality descriptions are standardized into the normalized database schema.
* **Retention Policies**: Configure regular purging of old logs and historical records according to your project's data retention guidelines.
