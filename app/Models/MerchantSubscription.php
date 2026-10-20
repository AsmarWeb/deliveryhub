<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantSubscription extends Model
{
    protected $fillable = [
        'cashier_subscription_id',
        'merchant_id', 'subscription_plan_id', 'status',
        'starts_at', 'ends_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function cashierSubscription(): BelongsTo
    {
        return $this->belongsTo(
            \Laravel\Cashier\Subscription::class,
            'cashier_subscription_id'
        );
    }
}
