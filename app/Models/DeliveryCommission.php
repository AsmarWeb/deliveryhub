<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryCommission extends Model
{
    protected $fillable = [
        'delivery_id', 'merchant_id', 'merchant_subscription_id',
        'gross_amount', 'commission_rate', 'platform_amount',
        'partner_amount', 'currency', 'status', 'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'platform_amount' => 'decimal:2',
            'partner_amount' => 'decimal:2',
            'earned_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(
            MerchantSubscription::class,
            'merchant_subscription_id'
        );
    }
}
