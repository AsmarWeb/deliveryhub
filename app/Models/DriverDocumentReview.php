<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverDocumentReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reason' => 'encrypted'];
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
