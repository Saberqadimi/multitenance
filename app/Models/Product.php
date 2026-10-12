<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'sku',
        'name',
        'unit_price',
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
