from typing import Any, List, Optional
from pydantic import BaseModel, Field

class PriceModel(BaseModel):
    value: Optional[int] = None
    currency: str = "INR"
    negotiable: Optional[bool] = None

class LocationModel(BaseModel):
    state: Optional[str] = None
    city: Optional[str] = None
    locality: Optional[str] = None
    pincode: Optional[str] = None

class SellerModel(BaseModel):
    name: Optional[str] = None
    type: Optional[str] = None
    contact_available: bool = False

class ImageModel(BaseModel):
    url: str
    position: Optional[int] = 0

class ParameterModel(BaseModel):
    key: str
    value: Any

class ListingItem(BaseModel):
    id: str
    title: Optional[str] = None
    description: Optional[str] = None
    url: Optional[str] = None
    price: Optional[PriceModel] = None
    category: Optional[dict] = None
    subcategory: Optional[dict] = None
    location: Optional[LocationModel] = None
    seller: Optional[SellerModel] = None
    images: List[ImageModel] = Field(default_factory=list)
    created_at: Optional[str] = None
    updated_at: Optional[str] = None
    parameters: List[ParameterModel] = Field(default_factory=list)
    raw_data: Optional[dict] = None

class PaginationModel(BaseModel):
    page: int = 1
    limit: int = 50
    has_next: bool = False
    total_records: Optional[int] = None

class SearchResponse(BaseModel):
    data: List[ListingItem] = Field(default_factory=list)
    pagination: PaginationModel

class DetailResponse(BaseModel):
    data: Optional[ListingItem] = None
