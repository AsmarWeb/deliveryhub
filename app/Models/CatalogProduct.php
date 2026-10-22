<?php

namespace App\Models;

use App\Services\Catalog\EnterpriseCatalogAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogProduct extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['external_key'];

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'stock_quantity' => 'integer', 'source_updated_at' => 'immutable_datetime'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CatalogConnection::class, 'catalog_connection_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('status', 'active')->where('stock_quantity', '>', 0)
            ->whereHas('connection', fn ($q) => $q->where('status', 'active')->whereNotNull('approved_at')
                ->whereIn('merchant_id', app(EnterpriseCatalogAccess::class)->eligibleMerchants()->select('merchants.id')));
    }
}
