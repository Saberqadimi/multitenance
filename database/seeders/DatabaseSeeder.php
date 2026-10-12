<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedDigikalaTenant();
            $this->seedDivarTenant();
        });
    }

    private function seedDigikalaTenant(): void
    {
        $digikalaTenant = Tenant::create([
            'name'      => 'دیجی‌ کالا',
            'subdomain' => 'digikala',
        ]);

        $digikalaProducts = [
            [
                'sku'        => 'LAPTOP-001',
                'name'       => 'لپ‌تاپ لنوو مدل ThinkPad',
                'unit_price' => 45000000,
            ],
            [
                'sku'        => 'MOUSE-001',
                'name'       => 'ماوس بی‌سیم لاجیتک',
                'unit_price' => 850000,
            ],
            [
                'sku'        => 'CHAIR-001',
                'name'       => 'صندلی اداری چرخ‌دار',
                'unit_price' => 12500000,
            ],
        ];

        $digikalaWarehouses = [
            [
                'name'     => 'انبار مرکزی',
                'location' => 'تهران - جاده مخصوص',
            ],
            [
                'name'     => 'انبار پشتیبان',
                'location' => 'کرج - شهرک صنعتی',
            ],
        ];

        $digikalaTenant->products()->createMany($digikalaProducts);
        $digikalaTenant->warehouses()->createMany($digikalaWarehouses);
    }

    private function seedDivarTenant(): void
    {
        $divarTenant = Tenant::create([
            'name'      => 'دیوار',
            'subdomain' => 'divar',
        ]);

        $divarProducts = [
            [
                'sku'        => 'LAPTOP-001',
                'name'       => 'لپ‌تاپ ایسوس مدل VivoBook',
                'unit_price' => 52000000,
            ],
            [
                'sku'        => 'FRIDGE-001',
                'name'       => 'یخچال ساید بای ساید سامسونگ',
                'unit_price' => 180000000,
            ],
        ];

        $divarWarehouses = [
            [
                'name'     => 'انبار اصلی',
                'location' => 'اصفهان - شهرک صنعتی',
            ],
        ];

        $divarTenant->products()->createMany($divarProducts);
        $divarTenant->warehouses()->createMany($divarWarehouses);
    }
}