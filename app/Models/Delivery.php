<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'merchant_id',
        'pickup_address_id',
        'dropoff_address_id',
        'tracking_number',
        'status',
        'recipient_name',
        'recipient_phone',
        'package_type',
        'weight',
        'delivery_fee',
        'currency',
        'special_instructions',
        'scheduled_at',
        'picked_up_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'scheduled_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'status' => DeliveryStatus::class,
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function pickupAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'pickup_address_id');
    }

    public function dropoffAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'dropoff_address_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    public function statusHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeliveryStatusHistory::class);
    }
}
