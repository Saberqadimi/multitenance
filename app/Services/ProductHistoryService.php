<?php

namespace App\Services;

use App\Http\Resources\ProductHistoryResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class ProductHistoryService
{
    public function getHistoryForSku(string $sku, Request $request) : \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $product = Product::query()
            ->where('sku', $sku)
            ->firstOrFail();

        return ProductHistoryResource::collection(
            QueryBuilder::for($product->stockMovements())
                ->with('warehouse')
                ->defaultSort('-created_at', '-id')
                ->paginate($request->input('per_page', 15))
        );
    }
}