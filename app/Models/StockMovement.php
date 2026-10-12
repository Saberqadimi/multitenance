<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
