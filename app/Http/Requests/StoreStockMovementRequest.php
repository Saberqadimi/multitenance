<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->attributes->get('tenant')?->id;

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('tenant_id', $tenantId),
            ],

            'warehouse_id' => [
                'required_unless:type,transfer',
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where('tenant_id', $tenantId),
            ],

            'type' => [
                'required',
                Rule::in(['in', 'out', 'transfer']),
            ],

            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'reference' => [
                'nullable',
                'string',
                'max:255',
            ],

            'meta' => [
                'nullable',
                'array',
            ],

            'meta.from_warehouse_id' => [
                'required_if:type,transfer',
                'integer',
                'different:meta.to_warehouse_id',
                Rule::exists('warehouses', 'id')
                    ->where('tenant_id', $tenantId),
            ],

            'meta.to_warehouse_id' => [
                'required_if:type,transfer',
                'integer',
                'different:meta.from_warehouse_id',
                Rule::exists('warehouses', 'id')
                    ->where('tenant_id', $tenantId),
            ],
        ];
    }
}