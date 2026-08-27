<?php

namespace App\Models;

use CodeIgniter\Model;

class SellerModel extends Model
{
    protected $table = 'sellers';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['source_id', 'source_seller_id', 'name', 'type', 'contact_available'];
}
