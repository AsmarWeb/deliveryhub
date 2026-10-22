<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogConnection extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['key_hash', 'feed_token', 'delivery_key_hash', 'delivery_webhook_secret'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'feed_token' => 'encrypted', 'approved_at' => 'datetime',
            'delivery_pickup_address_id' => 'integer', 'delivery_enabled' => 'boolean', 'delivery_webhook_secret' => 'encrypted', 'last_used_at' => 'datetime', 'last_synced_at' => 'datetime', 'next_sync_at' => 'datetime'];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(CatalogProduct::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CatalogSyncRun::class);
    }
}
