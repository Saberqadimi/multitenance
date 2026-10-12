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
    public function __invoke(StoreStockMovementRequest $request): JsonResponse {
        $tenantId = (int) $request->header('X-Tenant-ID');

        $data = $request->validated();

        $movement = DB::transaction(function () use (
            $data,
            $tenantId
        ) {
            if ($data['type'] === 'transfer') {
                return $this->transferStock($data, $tenantId);
            }

            $level = StockLevel::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $data['product_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->lockForUpdate()
                ->first();

            if (! $level) {
                $level = StockLevel::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $data['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'quantity' => 0,
                ]);

                $level = StockLevel::query()
                    ->whereKey($level->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

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

            $level->quantity += $data['type'] === 'in'
                ? $quantity
                : -$quantity;

            $level->save();

            return StockMovement::create([
                'tenant_id' => $tenantId,
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'type' => $data['type'],
                'quantity' => $quantity,
                'reference' => $data['reference'] ?? null,
            ]);
        });

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

        $warehouseIds = [$fromWarehouseId, $toWarehouseId];
        sort($warehouseIds);

        $levels = [];

        foreach ($warehouseIds as $warehouseId) {
            $level = StockLevel::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $data['product_id'])
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            if (! $level) {
                $level = StockLevel::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $data['product_id'],
                    'warehouse_id' => $warehouseId,
                    'quantity' => 0,
                ]);

                $level = StockLevel::query()
                    ->whereKey($level->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $levels[$warehouseId] = $level;
        }

        if ($levels[$fromWarehouseId]->quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => [
                    'موجودی انبار مبدأ برای انتقال کافی نیست.',
                ],
            ]);
        }

        $levels[$fromWarehouseId]->quantity -= $quantity;
        $levels[$fromWarehouseId]->save();

        $levels[$toWarehouseId]->quantity += $quantity;
        $levels[$toWarehouseId]->save();

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
}