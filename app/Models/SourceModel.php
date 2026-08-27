<?php

namespace App\Models;

use CodeIgniter\Model;

class SourceModel extends Model
{
    protected $table = 'sources';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'country', 'status'];
}
