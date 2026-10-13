<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockLevelRequest;
use App\Services\StockLevelService;

class StockLevelController extends Controller
{
    public function __construct(
        private readonly StockLevelService $service,
    ) {}

    public function __invoke(StockLevelRequest $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return $this->service->index($request);
    }
}