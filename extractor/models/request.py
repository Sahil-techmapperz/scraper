from typing import Optional
from pydantic import BaseModel, Field

class SearchQueryParams(BaseModel):
    category: Optional[str] = None
    subcategory: Optional[str] = None
    keyword: Optional[str] = None
    state: Optional[str] = None
    city: Optional[str] = None
    locality: Optional[str] = None
    pincode: Optional[str] = None
    min_price: Optional[int] = Field(default=None, alias="min_price")
    max_price: Optional[int] = Field(default=None, alias="max_price")
    sort: Optional[str] = "newest"
    page: int = Field(default=1, ge=1)
    limit: int = Field(default=50, ge=1, le=300)
    
    # Category-specific filters
    brand: Optional[str] = None
    model: Optional[str] = None
    variant: Optional[str] = None
    vehicle_type: Optional[str] = None
    min_year: Optional[int] = None
    max_year: Optional[int] = None
    fuel_type: Optional[str] = None
    transmission: Optional[str] = None
    min_km: Optional[int] = None
    max_km: Optional[int] = None
    owner_count: Optional[int] = None
    condition: Optional[str] = None
    min_storage: Optional[int] = None
    max_storage: Optional[int] = None
    bhk: Optional[int] = None
    property_type: Optional[str] = None
    listing_type: Optional[str] = None
    furnishing: Optional[str] = None
    posted_by: Optional[str] = None
    
    # Job-specific filters (Naukri)
    experience: Optional[int] = None
    min_experience: Optional[int] = None
    max_experience: Optional[int] = None
    skills: Optional[str] = None
    company: Optional[str] = None
    salary_range: Optional[str] = None
    job_type: Optional[str] = None
    industry: Optional[str] = None

    class Config:
        populate_by_name = True
