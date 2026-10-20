<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'monthly_price', 'currency',
        'commission_rate', 'monthly_delivery_limit',
        'max_active_automations', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'monthly_delivery_limit' => 'integer',
            'max_active_automations' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MerchantSubscription::class);
    }
}
