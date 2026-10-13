<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConcurrencyTestSeeder extends Seeder
{
    private const SUBDOMAIN = 'concurrency-test';

    public function run(): void
    {
        $db = DB::connection('mysql_concurrency_test');

        $db->transaction(function () use ($db) {
            $this->cleanup($db);

            $tenantId = $db->table('tenants')->insertGetId([
                'name'       => 'Concurrency Test',
                'subdomain'  => self::SUBDOMAIN,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productId = $db->table('products')->insertGetId([
                'tenant_id'  => $tenantId,
                'sku'        => 'CONCURRENCY',
                'name'       => 'Concurrency Test Product',
                'unit_price' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $warehouseId = $db->table('warehouses')->insertGetId([
                'tenant_id'  => $tenantId,
                'name'       => 'Concurrency Test Warehouse',
                'location'   => 'Test',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $db->table('stock_levels')->insert([
                'tenant_id'    => $tenantId,
                'product_id'   => $productId,
                'warehouse_id' => $warehouseId,
                'quantity'     => 50,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $this->command?->info(
                "Tenant: {$tenantId} | Product: {$productId} | Warehouse: {$warehouseId} | Stock: 50"
            );
        });
    }

    private function cleanup($db): void
    {
        $tenant = $db->table('tenants')
            ->where('subdomain', self::SUBDOMAIN)
            ->first();

        if (!$tenant) {
            $this->command?->info('No previous test data found. Starting clean.');
            return;
        }

        $tenantId = $tenant->id;

        $db->table('stock_movements')
            ->where('tenant_id', $tenantId)
            ->delete();

        $db->table('stock_levels')
            ->where('tenant_id', $tenantId)
            ->delete();

        $db->table('warehouses')
            ->where('tenant_id', $tenantId)
            ->delete();

        $db->table('products')
            ->where('tenant_id', $tenantId)
            ->delete();

        $db->table('tenants')
            ->where('id', $tenantId)
            ->delete();

        $this->command?->info("Previous test data for tenant {$tenantId} removed.");
    }
}