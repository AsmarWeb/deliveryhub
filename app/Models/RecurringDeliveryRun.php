<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringDeliveryRun extends Model
{
    protected $fillable = [
        'recurring_delivery_id',
        'delivery_id',
        'scheduled_for',
        'status',
        'error_message',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function recurringDelivery(): BelongsTo
    {
        return $this->belongsTo(RecurringDelivery::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
