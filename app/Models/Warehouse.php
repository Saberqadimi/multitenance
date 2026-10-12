<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'location',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockLEvels()
    {
        return $this->hasMany(StockLevel::class);
    }
}
