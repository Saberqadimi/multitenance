<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenant = static::currentTenant();

            if (! $tenant) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where(
                $builder->getModel()->qualifyColumn('tenant_id'),
                $tenant->id
            );
        });

        static::creating(function (Model $model) {
            $tenant = static::currentTenant();

            if ($tenant && $model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', $tenant->id);
            }
        });
    }

    protected static function currentTenant(): ?Tenant
    {
        $tenant = request()->attributes->get('tenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}