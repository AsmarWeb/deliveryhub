<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveryStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $deliveryId,
        public readonly string $trackingNumber,
        public readonly string $status,
        public readonly int $historyId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'delivery_id' => $this->deliveryId,
            'tracking_number' => $this->trackingNumber,
            'status' => $this->status,
            'history_id' => $this->historyId,
        ];
    }
}
