<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedPerformanceData extends Command
{
    protected $signature = 'performance:seed';

    protected $description = 'Create one million stock level records for performance testing';

    public function handle(): int
    {
//        $connectionName = 'mysql_concurrency_test';
        $connectionName = 'mysql';

        if (!config("database.connections.{$connectionName}")) {
            $this->error("Database connection [{$connectionName}] is not configured.");

            return self::FAILURE;
        }

        $connection = DB::connection($connectionName);

        // Check whether the performance tenant already exists.
        $existingTenant = $connection->table('tenants')
            ->where('subdomain', 'performance-test')
            ->first();

        if ($existingTenant) {
            $stockLevelCount = $connection->table('stock_levels')
                ->where('tenant_id', $existingTenant->id)
                ->count();

            if ($stockLevelCount === 1_000_000) {
                $this->info('One million stock levels already exist. Skipping seeding.');
                $this->info("Tenant ID: {$existingTenant->id}");
                $this->info("Connection: {$connectionName}");

                return self::SUCCESS;
            }

            $this->error(
                "Tenant already exists but has {$stockLevelCount} stock levels instead of 1,000,000."
            );

            $this->error('No data was changed. Please inspect the existing test data.');

            return self::FAILURE;
        }

        $now = now();

        // Create the performance tenant.
        $tenantId = $connection->table('tenants')->insertGetId([
            'name' => 'Performance Test',
            'subdomain' => 'performance-test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->info("Tenant created: {$tenantId}");
        $this->info("Connection: {$connectionName}");

        // Create 1,000 products in batches.
        for ($start = 1; $start <= 1000; $start += 500) {
            $products = [];

            for ($i = $start; $i < min($start + 500, 1001); $i++) {
                $products[] = [
                    'tenant_id' => $tenantId,
                    'sku' => sprintf('PERF-%04d', $i),
                    'name' => "Performance Product {$i}",
                    'unit_price' => 100,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $connection->table('products')->insert($products);
        }

        // Create 1,000 warehouses in batches.
        for ($start = 1; $start <= 1000; $start += 500) {
            $warehouses = [];

            for ($i = $start; $i < min($start + 500, 1001); $i++) {
                $warehouses[] = [
                    'tenant_id' => $tenantId,
                    'name' => "Performance Warehouse {$i}",
                    'location' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $connection->table('warehouses')->insert($warehouses);
        }

        // Retrieve IDs belonging only to the performance tenant.
        $productIds = $connection->table('products')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $warehouseIds = $connection->table('warehouses')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if (count($productIds) !== 1000 || count($warehouseIds) !== 1000) {
            $this->error('Expected 1,000 products and 1,000 warehouses.');
            $this->error('Performance data generation stopped.');

            return self::FAILURE;
        }

        $this->info('Creating 1,000,000 stock levels...');

        $bar = $this->output->createProgressBar(1_000_000);
        $bar->start();

        $batch = [];
        $inserted = 0;

        // 1,000 products × 1,000 warehouses = 1,000,000 combinations.
        foreach ($productIds as $productId) {
            foreach ($warehouseIds as $warehouseId) {
                $batch[] = [
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) === 2000) {
                    $connection->table('stock_levels')->insert($batch);

                    $inserted += count($batch);
                    $bar->advance(count($batch));

                    $batch = [];
                }
            }
        }

        // Insert any remaining records.
        if ($batch !== []) {
            $connection->table('stock_levels')->insert($batch);

            $inserted += count($batch);
            $bar->advance(count($batch));
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Performance data created successfully.');
        $this->info("Tenant ID: {$tenantId}");
        $this->info('Products: ' . count($productIds));
        $this->info('Warehouses: ' . count($warehouseIds));
        $this->info("Stock levels created: {$inserted}");

        return self::SUCCESS;
    }
}