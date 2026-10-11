<?php

namespace App\Events;

use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Delivery $delivery,
        public readonly DeliveryStatusHistory $history,
    ) {}
}
