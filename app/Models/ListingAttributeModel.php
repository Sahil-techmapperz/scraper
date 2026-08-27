<?php

namespace App\Models;

use CodeIgniter\Model;

class ListingAttributeModel extends Model
{
    protected $table = 'listing_attributes';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['listing_id', 'attribute_group', 'attribute_key', 'attribute_value'];
}
