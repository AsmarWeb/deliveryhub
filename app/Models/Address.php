<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'label',
        'contact_name',
        'contact_phone',
        'country',
        'city',
        'area',
        'street',
        'building',
        'unit',
        'address_line',
        'latitude',
        'longitude',
        'delivery_instructions',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function pickupDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'pickup_address_id');
    }

    public function dropoffDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'dropoff_address_id');
    }
}
