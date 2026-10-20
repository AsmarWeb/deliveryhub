<?php

namespace App\Models;

use Laravel\Cashier\Billable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    use HasFactory, Billable;

    protected $fillable = [
        'user_id',
        'business_name',
        'business_email',
        'business_phone',
        'trade_license_number',
        'tax_number',
        'status',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function merchantSubscriptions(): HasMany
    {
        return $this->hasMany(MerchantSubscription::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function stripeName(): ?string
    {
        return $this->business_name;
    }

    public function stripeEmail(): ?string
    {
        return $this->business_email
            ?: $this->user?->email;
    }
}
