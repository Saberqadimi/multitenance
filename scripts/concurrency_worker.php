<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.default' => 'mysql_concurrency_test']);

$productId = (int) ($argv[1] ?? 0);
$warehouseId = (int) ($argv[2] ?? 0);
$workerId = (int) ($argv[3] ?? 0);

$request = Request::create(
    '/api/stock-movements',
    'POST',
    [
        'product_id' => $productId,
        'warehouse_id' => $warehouseId,
        'type' => 'out',
        'quantity' => 1,
        'reference' => 'worker-' . $workerId,
    ],
    server: [
        'HTTP_X_TENANT' => 'concurrency-test',
        'HTTP_ACCEPT' => 'application/json',
    ]
);

$kernel = $app->make(Kernel::class);

try {
    $response = $kernel->handle($request);

    $status = $response->getStatusCode();
    $body = $response->getContent();
} catch (Throwable $e) {
    $response = null;
    $status = 500;
    $body = $e->getMessage();
}

if ($response !== null) {
    $kernel->terminate($request, $response);
}

echo json_encode([
    'worker' => $workerId,
    'status' => $status,
    'body' => $body,
], JSON_UNESCAPED_UNICODE) . "\n";
