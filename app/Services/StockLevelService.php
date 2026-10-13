<?php

namespace App\Services;

use App\Http\Requests\StockLevelRequest;
use App\Http\Resources\StockLevelResource;
use App\Models\StockLevel;
use Illuminate\Support\Facades\Cache;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockLevelService
{
    private const CACHE_TTL = 300;

    public function index(StockLevelRequest $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $cacheKey = $this->buildCacheKey($request);

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($request) {
                return StockLevelResource::collection(
                    QueryBuilder::for(StockLevel::query())
                        ->with(['product', 'warehouse'])
                        ->allowedFilters(
                            AllowedFilter::exact('product_id'),
                            AllowedFilter::exact('warehouse_id'),
                        )
                        ->defaultSort('id')
                        ->simplePaginate($request->input('per_page', 15))
                );
            }
        );
    }

    private function buildCacheKey(StockLevelRequest $request): string
    {
        return 'stock_levels:' . md5(json_encode([
                'product_id'   => $request->input('filter.product_id'),
                'warehouse_id' => $request->input('filter.warehouse_id'),
                'per_page'     => $request->input('per_page', 15),
                'page'         => $request->input('page', 1),
            ]));
    }
}