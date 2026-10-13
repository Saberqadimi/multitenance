<?php

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const WORKERS = 100;
const STARTING_STOCK = 50;

$db = DB::connection('mysql_concurrency_test');

$tenant = $db->table('tenants')
    ->where('subdomain', 'concurrency-test')
    ->first();

if (! $tenant) {
    exit("tenant پیدا نشد. اول اجرا کن: php artisan db:seed --class=ConcurrencyTestSeeder\n");
}

$product = $db->table('products')
    ->where('tenant_id', $tenant->id)
    ->where('sku', 'CONCURRENCY')
    ->first();

$warehouse = $db->table('warehouses')
    ->where('tenant_id', $tenant->id)
    ->where('name', 'Concurrency Test Warehouse')
    ->first();

if (! $product || ! $warehouse) {
    exit("product یا warehouse پیدا نشد. اول اجرا کن: php artisan db:seed --class=ConcurrencyTestSeeder\n");
}

$stockLevel = fn () => $db->table('stock_levels')
    ->where('tenant_id', $tenant->id)
    ->where('product_id', $product->id)
    ->where('warehouse_id', $warehouse->id);

$db->table('stock_movements')
    ->where('tenant_id', $tenant->id)
    ->where('product_id', $product->id)
    ->delete();

$stockLevel()->update([
    'quantity' => STARTING_STOCK,
    'updated_at' => now(),
]);

if ((int) $stockLevel()->value('quantity') !== STARTING_STOCK) {
    exit("reset کردن موجودی جواب نداد.\n");
}

echo "موجودی اولیه: " . STARTING_STOCK . "\n";
echo "روشن کردن " . WORKERS . " ورکر...\n";

$workers = [];

for ($i = 1; $i <= WORKERS; $i++) {
    $process = new Process([
        PHP_BINARY,
        __DIR__ . '/concurrency_worker.php',
        (string) $product->id,
        (string) $warehouse->id,
        (string) $i,
    ], dirname(__DIR__));

    $process->setTimeout(120);
    $process->start();

    $workers[$i] = $process;
}

$created = 0;
$rejected = 0;
$unexpected = [];

foreach ($workers as $id => $process) {
    $process->wait();

    $result = json_decode($process->getOutput(), true);

    if (! $process->isSuccessful() || ! is_array($result)) {
        $unexpected['process error'] = ($unexpected['process error'] ?? 0) + 1;
        echo "ورکر {$id} کرش کرد: " . trim($process->getErrorOutput()) . "\n";
        continue;
    }

    $status = (int) $result['status'];

    if ($status === 201) {
        $created++;
    } elseif ($status === 422) {
        $rejected++;
    } else {
        $unexpected[$status] = ($unexpected[$status] ?? 0) + 1;
        echo "ورکر {$id}: HTTP {$status} - "
            . substr($result['body'] ?? '', 0, 300) . "\n";
    }
}

$finalStock = $stockLevel()->value('quantity');

$movementCount = $db->table('stock_movements')
    ->where('tenant_id', $tenant->id)
    ->where('product_id', $product->id)
    ->where('type', 'out')
    ->count();

$passed = $created === STARTING_STOCK
    && $rejected === WORKERS - STARTING_STOCK
    && empty($unexpected)
    && (int) $finalStock === 0
    && $movementCount === STARTING_STOCK;

echo "\n========== RESULTS ==========\n";
echo "ثبت‌شده (201):     {$created}\n";
echo "ردشده (422):       {$rejected}\n";
echo "نامرتب:            " . json_encode($unexpected) . "\n";
echo "گردش‌های ثبت‌شده:  {$movementCount}\n";
echo "موجودی نهایی:      {$finalStock}\n";

echo $passed ? "\nPASS\n" : "\nFAIL\n";

exit($passed ? 0 : 1);
