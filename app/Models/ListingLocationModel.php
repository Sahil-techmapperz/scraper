<?php

namespace App\Models;

use CodeIgniter\Model;

class ListingLocationModel extends Model
{
    protected $table = 'listing_locations';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['state', 'city', 'locality', 'pincode', 'latitude', 'longitude'];
}
