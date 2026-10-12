<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementController extends Controller
{
    public function __invoke(
        StoreStockMovementRequest $request
    ): JsonResponse {
        $tenantId = (int) $request->attributes->get('tenant')->id;
        $data = $request->validated();

        $movement = DB::transaction(function () use (
            $data,
            $tenantId
        ) {
            if ($data['type'] === 'transfer') {
                return $this->transferStock($data, $tenantId);
            }

            $level = $this->lockStockLevel(
                $tenantId,
                (int) $data['product_id'],
                (int) $data['warehouse_id']
            );

            $quantity = (int) $data['quantity'];

            if (
                $data['type'] === 'out'
                && $level->quantity < $quantity
            ) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        'موجودی انبار برای این خروج کافی نیست.',
                    ],
                ]);
            }

            if ($data['type'] === 'in') {
                $level->quantity += $quantity;
            } else {
                $level->quantity -= $quantity;
            }

            $level->save();

            return StockMovement::create([
                'tenant_id' => $tenantId,
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'type' => $data['type'],
                'quantity' => $quantity,
                'reference' => $data['reference'] ?? null,
            ]);
        }, 3);

        return response()->json([
            'message' => 'گردش موجودی با موفقیت ثبت شد.',
            'data' => $movement,
        ], 201);
    }

    private function transferStock(
        array $data,
        int $tenantId
    ): StockMovement {
        $quantity = (int) $data['quantity'];

        $fromWarehouseId = (int) $data['meta']['from_warehouse_id'];
        $toWarehouseId = (int) $data['meta']['to_warehouse_id'];

        $warehouseIds = [
            $fromWarehouseId,
            $toWarehouseId,
        ];

        sort($warehouseIds);

        $levels = [];

        foreach ($warehouseIds as $warehouseId) {
            $levels[$warehouseId] = $this->lockStockLevel(
                $tenantId,
                (int) $data['product_id'],
                $warehouseId
            );
        }

        $source = $levels[$fromWarehouseId];
        $destination = $levels[$toWarehouseId];

        if ($source->quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => [
                    'موجودی انبار مبدأ برای انتقال کافی نیست.',
                ],
            ]);
        }

        $source->quantity -= $quantity;
        $destination->quantity += $quantity;

        $source->save();
        $destination->save();

        return StockMovement::create([
            'tenant_id' => $tenantId,
            'product_id' => $data['product_id'],
            'warehouse_id' => $fromWarehouseId,
            'type' => 'transfer',
            'quantity' => $quantity,
            'reference' => $data['reference'] ?? null,
            'meta' => [
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
            ],
        ]);
    }

    private function lockStockLevel(
        int $tenantId,
        int $productId,
        int $warehouseId
    ): StockLevel {
        $now = now();
        StockLevel::query()->insertOrIgnore([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return StockLevel::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}