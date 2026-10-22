<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationDeliveryWebhook extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'next_attempt_at' => 'datetime', 'queued_at' => 'datetime', 'processing_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(IntegrationDeliveryOrder::class, 'integration_delivery_order_id');
    }
}
