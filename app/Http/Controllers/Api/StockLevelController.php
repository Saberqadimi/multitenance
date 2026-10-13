<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockLevelRequest;
use App\Models\StockLevel;
use Illuminate\Http\JsonResponse;

class StockLevelController extends Controller
{
    public function __invoke(StockLevelRequest $request): JsonResponse
    {
        $stockLevels = StockLevel::query()
            ->with(['product', 'warehouse'])
            ->when(
                $request->filled('product_id'),
                fn ($query) => $query->where('product_id', $request->product_id)
            )
            ->when(
                $request->filled('warehouse_id'),
                fn ($query) => $query->where('warehouse_id', $request->warehouse_id)
            )
            ->orderBy('id')
            ->paginate($request->input('per_page', 15));

        return response()->json($stockLevels);
    }
}