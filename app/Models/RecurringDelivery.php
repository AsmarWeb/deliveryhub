<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringDelivery extends Model
{
    protected $fillable = [
        'merchant_id',
        'pickup_address_id',
        'dropoff_address_id',
        'recipient_name',
        'recipient_phone',
        'package_type',
        'weight',
        'special_instructions',
        'frequency',
        'timezone',
        'day_of_week',
        'execution_time',
        'next_run_at',
        'last_run_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'day_of_week' => 'integer',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'is_active' => 'boolean',
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

    public function runs(): HasMany
    {
        return $this->hasMany(RecurringDeliveryRun::class);
    }
}
