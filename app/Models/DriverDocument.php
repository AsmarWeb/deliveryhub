<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DriverDocument extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['expires_on' => 'immutable_date', 'superseded_at' => 'datetime',
            'reminded_at' => 'datetime', 'expired_notified_at' => 'datetime', 'rejection_reason' => 'encrypted'];
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function reviews()
    {
        return $this->hasMany(DriverDocumentReview::class);
    }

    public function effectiveStatus(): string
    {
        if ($this->expires_on && $this->expires_on->format('Y-m-d') < CarbonImmutable::today('Asia/Dubai')->format('Y-m-d')) {
            return 'expired';
        }

        return $this->status;
    }
}
