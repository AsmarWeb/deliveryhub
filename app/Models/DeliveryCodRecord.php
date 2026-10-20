<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryCodRecord extends Model
{
    protected $fillable = ['delivery_id', 'expected_amount', 'currency', 'status', 'collected_by', 'collected_at', 'received_by', 'received_at'];

    protected function casts(): array
    {
        return ['expected_amount' => 'decimal:2', 'collected_at' => 'datetime', 'received_at' => 'datetime'];
    }
}
