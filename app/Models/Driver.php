<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'vehicle_type',
        'vehicle_plate',
        'license_number',
        'status',
        'is_available',
        'current_latitude',
        'current_longitude',
        'last_location_at',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'current_latitude' => 'decimal:7',
            'current_longitude' => 'decimal:7',
            'last_location_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }
}
