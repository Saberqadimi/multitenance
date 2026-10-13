<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductHistoryController extends Controller
{
    public function __invoke(Request $request, string $sku)
    {
        $product = Product::query()->where('sku', $sku)->firstOrFail();

        $history = $product->stockMovements()
            ->with(['warehouse'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 15));

        return response()->json($history);
    }
}
