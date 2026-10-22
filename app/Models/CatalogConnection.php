<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogConnection extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['key_hash', 'feed_token'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'feed_token' => 'encrypted', 'approved_at' => 'datetime',
            'last_used_at' => 'datetime', 'last_synced_at' => 'datetime', 'next_sync_at' => 'datetime'];
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
