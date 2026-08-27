<?php

namespace App\Models;

use CodeIgniter\Model;

class ListingImageModel extends Model
{
    protected $table = 'listing_images';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['listing_id', 'url', 'position'];
}
