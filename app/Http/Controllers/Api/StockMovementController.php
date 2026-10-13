<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockMovementRequest;
use App\Services\StockMovementService;
use Illuminate\Http\JsonResponse;

class StockMovementController extends Controller
{
    public function __construct(
        private readonly StockMovementService $service,
    ) {}

    public function __invoke(
        StoreStockMovementRequest $request
    ): JsonResponse {
        $movement = $this->service->store($request);

        return response()->json([
            'message' => 'گردش موجودی با موفقیت ثبت شد.',
            'data' => $movement,
        ], 201);
    }
}