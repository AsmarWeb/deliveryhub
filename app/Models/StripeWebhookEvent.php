<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    protected $table = 'stripe_webhook_inbox';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'attempts' => 'integer', 'next_attempt_at' => 'datetime',
            'queued_at' => 'datetime', 'processing_started_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
