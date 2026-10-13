<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductHistoryResource;
use App\Services\ProductHistoryService;
use Illuminate\Http\Request;

class ProductHistoryController extends Controller
{
    public function __construct(
        private readonly ProductHistoryService $service,
    ) {}

    public function __invoke(Request $request, string $sku) : \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return $this->service->getHistoryForSku($sku, $request);
    }
}
