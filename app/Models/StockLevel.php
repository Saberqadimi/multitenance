<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'quantity',
    ];
}
