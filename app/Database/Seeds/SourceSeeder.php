<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        $exists = $this->db->table('sources')
            ->where('name', 'olx')
            ->where('country', 'IN')
            ->countAllResults();

        if ($exists > 0) {
            return;
        }

        $this->db->table('sources')->insert([
            'name'       => 'olx',
            'country'    => 'IN',
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
