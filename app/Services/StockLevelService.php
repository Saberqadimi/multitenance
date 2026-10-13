<?php

namespace App\Services;

use App\Http\Requests\StockLevelRequest;
use App\Http\Resources\StockLevelResource;
use App\Models\StockLevel;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockLevelService
{
    public function index(StockLevelRequest $request) : \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return StockLevelResource::collection(
            QueryBuilder::for(StockLevel::query())
                ->with(['product', 'warehouse'])
                ->allowedFilters(
                    AllowedFilter::exact('product_id'),
                    AllowedFilter::exact('warehouse_id'),
                )
                ->defaultSort('id')
                ->paginate($request->input('per_page', 15))
        );
    }
}