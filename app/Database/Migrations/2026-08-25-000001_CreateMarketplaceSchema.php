<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMarketplaceSchema extends Migration
{
    public function up(): void
    {
        $this->createSources();
        $this->createCategories();
        $this->createSellers();
        $this->createListingLocations();
        $this->createListings();
        $this->createListingImages();
        $this->createListingAttributes();
        $this->createApiClients();
        $this->createApiKeys();
        $this->createApiUsageLogs();
        $this->createExtractionLogs();
    }

    public function down(): void
    {
        foreach ([
            'extraction_logs',
            'api_usage_logs',
            'api_keys',
            'api_clients',
            'listing_attributes',
            'listing_images',
            'listings',
            'listing_locations',
            'sellers',
            'categories',
            'sources',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function timestamps(): array
    {
        return [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];
    }

    private function createSources(): void
    {
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'    => ['type' => 'VARCHAR', 'constraint' => 64],
            'country' => ['type' => 'CHAR', 'constraint' => 2, 'default' => 'IN'],
            'status'  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'active'],
        ] + $this->timestamps());
        $this->forge->addKey('id', true);
        $this->forge->addKey(['name', 'country'], false, true);
        $this->forge->createTable('sources');
    }

    private function createCategories(): void
    {
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'source_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'slug'      => ['type' => 'VARCHAR', 'constraint' => 120],
            'name'      => ['type' => 'VARCHAR', 'constraint' => 160],
            'parent_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ] + $this->timestamps());
        $this->forge->addKey('id', true);
        $this->forge->addKey(['source_id', 'slug'], false, true);
        $this->forge->addKey('parent_id');
        $this->forge->createTable('categories');
    }

    private function createSellers(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'source_id'         => ['type' => 'INT', 'unsigned' => true],
            'source_seller_id'  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'name'              => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'type'              => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'contact_available' => ['type' => 'BOOLEAN', 'default' => false],
        ] + $this->timestamps());
        $this->forge->addKey('id', true);
        $this->forge->addKey(['source_id', 'source_seller_id']);
        $this->forge->createTable('sellers');
    }

    private function createListingLocations(): void
    {
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'state'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'city'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'locality'  => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'pincode'   => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'latitude'  => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'longitude' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
        ] + $this->timestamps());
        $this->forge->addKey('id', true);
        $this->forge->addKey(['city', 'locality']);
        $this->forge->createTable('listing_locations');
    }

    private function createListings(): void
    {
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'source_id'          => ['type' => 'INT', 'unsigned' => true],
            'source_listing_id'  => ['type' => 'VARCHAR', 'constraint' => 120],
            'source_url'         => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'category_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'title'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'description'        => ['type' => 'TEXT', 'null' => true],
            'price'              => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'currency'           => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'INR'],
            'seller_id'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'location_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'listing_date'       => ['type' => 'DATE', 'null' => true],
            'last_source_update' => ['type' => 'DATETIME', 'null' => true],
            'first_collected_at' => ['type' => 'DATETIME', 'null' => true],
            'last_collected_at'  => ['type' => 'DATETIME', 'null' => true],
            'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'available'],
            'raw_data'           => ['type' => 'LONGTEXT', 'null' => true],
        ] + $this->timestamps());
        $this->forge->addKey('id', true);
        $this->forge->addKey(['source_id', 'source_listing_id'], false, true);
        $this->forge->addKey('category_id');
        $this->forge->addKey('seller_id');
        $this->forge->addKey('location_id');
        $this->forge->createTable('listings');
    }

    private function createListingImages(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'listing_id' => ['type' => 'INT', 'unsigned' => true],
            'url'        => ['type' => 'VARCHAR', 'constraint' => 500],
            'position'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
        ] + $this->timestamps());
        $this->forge->addKey('id', true);
        $this->forge->addKey(['listing_id', 'position']);
        $this->forge->createTable('listing_images');
    }

    private function createListingAttributes(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'listing_id'      => ['type' => 'INT', 'unsigned' => true],
            'attribute_group' => ['type' => 'VARCHAR', 'constraint' => 64],
            'attribute_key'   => ['type' => 'VARCHAR', 'constraint' => 120],
            'attribute_value' => ['type' => 'TEXT', 'null' => true],
        ] + $this->timestamps());
        $this->forge->addKey('id', true);
        $this->forge->addKey(['listing_id', 'attribute_group', 'attribute_key']);
        $this->forge->createTable('listing_attributes');
    }

    private function createApiClients(): void
    {
        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'                  => ['type' => 'VARCHAR', 'constraint' => 160],
            'status'                => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'active'],
            'monthly_quota'         => ['type' => 'INT', 'unsigned' => true, 'default' => 10000],
            'requests_used'         => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'usage_period'          => ['type' => 'VARCHAR', 'constraint' => 7, 'default' => '1970-01'],
            'rate_limit_per_minute' => ['type' => 'INT', 'unsigned' => true, 'default' => 100],
            'is_admin'              => ['type' => 'BOOLEAN', 'default' => false],
            'expires_at'            => ['type' => 'DATETIME', 'null' => true],
        ] + $this->timestamps());
        $this->forge->addKey('id', true);
        $this->forge->createTable('api_clients');
    }

    private function createApiKeys(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'client_id'    => ['type' => 'INT', 'unsigned' => true],
            'key_hash'     => ['type' => 'CHAR', 'constraint' => 64],
            'key_prefix'   => ['type' => 'VARCHAR', 'constraint' => 12],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'active'],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'expires_at'   => ['type' => 'DATETIME', 'null' => true],
        ] + $this->timestamps());
        $this->forge->addKey('id', true);
        $this->forge->addKey('client_id');
        $this->forge->addKey('key_hash', false, true);
        $this->forge->createTable('api_keys');
    }

    private function createApiUsageLogs(): void
    {
        $this->forge->addField([
            'id'                          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'client_id'                   => ['type' => 'INT', 'unsigned' => true],
            'endpoint'                    => ['type' => 'VARCHAR', 'constraint' => 190],
            'request_method'              => ['type' => 'VARCHAR', 'constraint' => 12],
            'request_params'              => ['type' => 'TEXT', 'null' => true],
            'http_status'                 => ['type' => 'SMALLINT', 'unsigned' => true],
            'response_time_ms'            => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'records_returned'            => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'cache_hit'                   => ['type' => 'BOOLEAN', 'default' => false],
            'source_extraction_attempted' => ['type' => 'BOOLEAN', 'default' => false],
            'source_extraction_success'   => ['type' => 'BOOLEAN', 'default' => false],
            'ip_address'                  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'user_agent'                  => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at'                  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['client_id', 'created_at']);
        $this->forge->createTable('api_usage_logs');
    }

    private function createExtractionLogs(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'source_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'source_name'       => ['type' => 'VARCHAR', 'constraint' => 64],
            'operation'         => ['type' => 'VARCHAR', 'constraint' => 64],
            'request_params'    => ['type' => 'TEXT', 'null' => true],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 32],
            'error_code'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'error_message'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'response_time_ms'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'records_collected' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at'        => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['source_name', 'created_at']);
        $this->forge->createTable('extraction_logs');
    }
}
