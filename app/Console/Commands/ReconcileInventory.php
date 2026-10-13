<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileInventory extends Command
{
    protected $signature = 'inventory:reconcile {tenant}';

    protected $description = 'Reconcile stock levels against stock movements for a tenant';

    public function handle(): int
    {
        $tenant = $this->resolveTenant($this->argument('tenant'));

        if (!$tenant) {
            $this->error('Tenant موردنظر پیدا نشد.');

            return self::FAILURE;
        }

        // موجودی مورد انتظار از روی حرکات
        $expected = $this->expectedLevels($tenant->id);
        // موجودی فعلی ثبت‌شده
        $actual = $this->actualLevels($tenant->id);

        // مقایسه و گزارش مغایرت‌ها
        $discrepancies = $this->reportDiscrepancies(
            $tenant,
            $expected,
            $actual
        );

        $this->newLine();
        $this->line("Tenant: {$tenant->name} (ID: {$tenant->id})");
        $this->line('Product-warehouse combinations checked: ' . count(
                array_unique(array_merge(
                    array_keys($expected),
                    array_keys($actual)
                ))
            ));
        $this->line("Discrepancies found: {$discrepancies}");

        if ($discrepancies === 0) {
            $this->info('SUCCESS: All stock levels match stock movements.');
        } else {
            $this->error('WARNING: Stock discrepancies were found. Check the application log.');
        }

        return self::SUCCESS;
    }

    private function resolveTenant(string $key): ?Tenant
    {
        return Tenant::query()
            ->when(is_numeric($key), fn($q) => $q->where('id', $key))
            ->when(!is_numeric($key), fn($q) => $q->where('subdomain', $key))
            ->first();
    }

    private function expectedLevels(int $tenantId): array
    {
        $levels = [];

        StockMovement::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->select(['id', 'product_id', 'warehouse_id', 'type', 'quantity', 'meta'])
            ->chunkById(1000, function ($movements) use (&$levels) {
                foreach ($movements as $movement) {
                    $this->applyMovement($levels, $movement);
                }
            });

        return $levels;
    }

    private function applyMovement(array &$levels, StockMovement $movement): void
    {
        $productId = (int)$movement->product_id;
        $warehouseId = (int)$movement->warehouse_id;
        $quantity = (int)$movement->quantity;

        if ($movement->type === 'in') {
            $this->addQuantity($levels, $productId, $warehouseId, $quantity);

            return;
        }

        if ($movement->type === 'out') {
            $this->addQuantity($levels, $productId, $warehouseId, -$quantity);

            return;
        }

        if ($movement->type === 'transfer') {
            $this->applyTransfer($levels, $movement, $productId, $quantity);
        }
    }

    private function applyTransfer(array &$levels, StockMovement $movement, int $productId, int $quantity): void
    {
        $meta = $movement->meta ?? [];

        $fromWarehouseId = (int)($meta['from_warehouse_id'] ?? $movement->warehouse_id);
        $toWarehouseId = $meta['to_warehouse_id'] ?? null;

        if (!is_numeric($toWarehouseId) || (int)$toWarehouseId < 1) {
            Log::warning('Invalid transfer metadata', [
                'movement_id' => $movement->id,
                'product_id' => $productId,
            ]);

            return;
        }

        $this->addQuantity($levels, $productId, $fromWarehouseId, -$quantity);
        $this->addQuantity($levels, $productId, (int)$toWarehouseId, $quantity);
    }

    private function addQuantity(array &$levels, int $productId, int $warehouseId, int $quantity): void
    {
        $key = "{$productId}:{$warehouseId}";
        $keyLog = "PRODUCT_ID => {$productId} : WAREHOUSE_ID =>{$warehouseId}";
        Log::info('history each product in warehouse' , [$keyLog . "  QUANTITY => {$quantity}"]);

        $levels[$key] = ($levels[$key] ?? 0) + $quantity;
        Log::info('history each product after update in warehouse' , [$keyLog . "  QUANTITY => {$levels[$key]}"]);
    }

    private function actualLevels(int $tenantId): array
    {
        $levels = [];

        StockLevel::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->select(['id', 'product_id', 'warehouse_id', 'quantity'])
            ->chunkById(1000, function ($rows) use (&$levels) {
                foreach ($rows as $row) {
                    $key = "{$row->product_id}:{$row->warehouse_id}";
                    $levels[$key] = (int)$row->quantity;
                }
            });

        return $levels;
    }


    private function reportDiscrepancies(
        Tenant $tenant,
        array $expected,
        array $actual
    ): int {
        $allKeys = array_unique(array_merge(
            array_keys($expected),
            array_keys($actual)
        ));

        $productIds = [];
        $warehouseIds = [];

        foreach ($allKeys as $key) {
            [$productId, $warehouseId] = array_map(
                'intval',
                explode(':', $key)
            );

            $productIds[] = $productId;
            $warehouseIds[] = $warehouseId;
        }

        $products = Product::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', array_unique($productIds))
            ->get(['id', 'sku', 'name'])
            ->keyBy('id');

        $warehouses = Warehouse::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', array_unique($warehouseIds))
            ->get(['id', 'name', 'location'])
            ->keyBy('id');

        $rows = [];
        $count = 0;

        foreach ($allKeys as $key) {
            [$productId, $warehouseId] = array_map(
                'intval',
                explode(':', $key)
            );

            $expectedQty = $expected[$key] ?? 0;
            $actualQty = $actual[$key] ?? 0;
            $difference = $actualQty - $expectedQty;

            $product = $products->get($productId);
            $warehouse = $warehouses->get($warehouseId);

            $rows[] = [
                $product?->sku ?? "Product #{$productId}",
                $product?->name ?? '-',
                $warehouse?->name ?? "Warehouse #{$warehouseId}",
                $expectedQty,
                $actualQty,
                $difference,
            ];

            if ($difference === 0) {
                continue;
            }

            $count++;

            Log::warning('Stock discrepancy', [
                'tenant_id' => $tenant->id,
                'product_id' => $productId,
                'product_sku' => $product?->sku,
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $warehouse?->name,
                'expected' => $expectedQty,
                'actual' => $actualQty,
                'difference' => $difference,
            ]);
        }

        if (empty($rows)) {
            $this->warn('هیچ رکورد موجودی یا حرکت انباری برای بررسی پیدا نشد.');

            return 0;
        }

        $this->table(
            [
                'SKU',
                'نام محصول',
                'انبار',
                'انتظار',
                'فعلی',
                'اختلاف',
            ],
            $rows
        );

        return $count;
    }
}