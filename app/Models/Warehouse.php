<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'location',
    ];

    public function tenant() : BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stockMovements() : HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockLevels() : HasMany
    {
        return $this->hasMany(StockLevel::class);
    }
}
