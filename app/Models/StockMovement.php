<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'type',
        'quantity',
        'reference',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function tenant() : BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function products() : BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse() : BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
