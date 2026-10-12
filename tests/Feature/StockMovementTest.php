<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'ترب',
            'subdomain' => 'torob',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'TEST-001',
            'name' => 'ماشین حساب مدل 0096',
            'unit_price' => 100,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'انبار اصلی',
            'location' => 'اصفهان',
        ]);
    }

    public function test_stock_can_be_received(): void
    {
        dump($this->tenant->fresh()->subdomain);
        dump($this->tenant->subdomain);

        $response = $this
            ->withHeader('X-Tenant', (string) $this->tenant->subdomain)
            ->postJson('/api/stock-movements', [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'type' => 'in',
                'quantity' => 10,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('stock_levels', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'in',
            'quantity' => 10,
        ]);
    }

    public function test_stock_cannot_be_removed_below_zero(): void
    {
        $response = $this
            ->withHeader('X-Tenant', (string) $this->tenant->subdomain)
            ->postJson('/api/stock-movements', [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'type' => 'out',
                'quantity' => 5,
            ]);

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('stock_levels', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => -5,
        ]);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_stock_can_be_removed_when_enough_stock_exists(): void
    {
        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
        ]);

        $response = $this
            ->withHeader('X-Tenant', (string) $this->tenant->subdomain)
            ->postJson('/api/stock-movements', [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'type' => 'out',
                'quantity' => 4,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('stock_levels', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 6,
        ]);
    }

    public function test_stock_can_be_transferred_between_warehouses(): void
    {
        $destinationWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'انبار پشتیبان',
            'location' => 'تهران',
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 20,
        ]);

        $response = $this
            ->withHeader('X-Tenant', (string) $this->tenant->subdomain)
            ->postJson('/api/stock-movements', [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'type' => 'transfer',
                'quantity' => 8,
                'meta' => [
                    'from_warehouse_id' => $this->warehouse->id,
                    'to_warehouse_id' => $destinationWarehouse->id,
                ],
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('stock_levels', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 12,
        ]);

        $this->assertDatabaseHas('stock_levels', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $destinationWarehouse->id,
            'quantity' => 8,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'transfer',
            'quantity' => 8,
        ]);
    }

    public function test_transfer_fails_when_source_has_insufficient_stock(): void
    {
        $destinationWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'انبار پشتیبان',
            'location' => 'تهران',
        ]);

        StockLevel::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
        ]);

        $response = $this
            ->withHeader('X-Tenant', (string) $this->tenant->subdomain)
            ->postJson('/api/stock-movements', [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'type' => 'transfer',
                'quantity' => 10,
                'meta' => [
                    'from_warehouse_id' => $this->warehouse->id,
                    'to_warehouse_id' => $destinationWarehouse->id,
                ],
            ]);

        $response->assertUnprocessable();

        $this->assertDatabaseHas('stock_levels', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseMissing('stock_levels', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $destinationWarehouse->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseCount('stock_movements', 0);
    }
}