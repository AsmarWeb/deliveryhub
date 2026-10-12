<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryStatusHistory extends Model
{
    protected $fillable = [
        'delivery_id',
        'user_id',
        'from_status',
        'to_status',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => DeliveryStatus::class,
            'to_status' => DeliveryStatus::class,
            'metadata' => 'array',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
