<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationDeliveryOrder extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['payload', 'payload_hash', 'idempotency_hash', 'tracking_token', 'tracking_hash'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'tracking_token' => 'encrypted'];
    }

    public function connection()
    {
        return $this->belongsTo(CatalogConnection::class, 'catalog_connection_id');
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }
}
