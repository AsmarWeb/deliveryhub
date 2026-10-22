<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryPricingSetting extends Model
{
    protected $fillable = [
        'base_fee',
        'price_per_km',
        'minimum_fee',
        'maximum_distance_km',
        'auto_pricing_enabled',
        'emirate_pricing_enabled',
        'emirate_rates',
    ];

    protected function casts(): array
    {
        return [
            'base_fee' => 'decimal:2',
            'price_per_km' => 'decimal:2',
            'minimum_fee' => 'decimal:2',
            'maximum_distance_km' => 'decimal:2',
            'auto_pricing_enabled' => 'boolean',
            'emirate_pricing_enabled' => 'boolean',
            'emirate_rates' => 'array',
        ];
    }
}
