<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table = 'categories';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['source_id', 'slug', 'name', 'parent_id'];
}
