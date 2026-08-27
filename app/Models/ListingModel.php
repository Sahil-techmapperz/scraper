<?php

namespace App\Models;

use CodeIgniter\Model;

class ListingModel extends Model
{
    protected $table = 'listings';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'source_id',
        'source_listing_id',
        'source_url',
        'category_id',
        'title',
        'description',
        'price',
        'currency',
        'seller_id',
        'location_id',
        'listing_date',
        'last_source_update',
        'first_collected_at',
        'last_collected_at',
        'status',
        'raw_data',
    ];
}
