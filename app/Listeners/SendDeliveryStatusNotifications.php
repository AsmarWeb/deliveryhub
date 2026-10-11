<?php

namespace App\Listeners;

use App\Enums\DeliveryStatus;
use App\Events\DeliveryStatusChanged;
use App\Models\DeliveryAssignment;
use App\Models\User;
use App\Notifications\DeliveryStatusNotification;
use Illuminate\Support\Facades\DB;

class SendDeliveryStatusNotifications
{
    /**
     * This listener writes database notifications after the transaction commits.
     * No queue worker is required for this first, database-only release.
     */
    public function handle(DeliveryStatusChanged $event): void
    {
        $status = $event->history->to_status;
        if (! in_array($status, [
            DeliveryStatus::Assigned->value,
            DeliveryStatus::Accepted->value,
            DeliveryStatus::Pickup->value,
            DeliveryStatus::Delivered->value,
            DeliveryStatus::Completed->value,
        ], true)) {
            return;
        }

        $delivery = $event->delivery;
        $recipientIds = [];
        $merchantUserId = $delivery->merchant()->value('user_id');
        if ($merchantUserId !== null) {
            $recipientIds[] = (int) $merchantUserId;
        }

        if ($status === DeliveryStatus::Assigned->value) {
            $driverUserId = DeliveryAssignment::query()
                ->where('delivery_id', $delivery->getKey())
                ->where('status', 'pending')
                ->latest('id')
                ->first()?->driver?->user_id;
            if ($driverUserId !== null) {
                $recipientIds[] = (int) $driverUserId;
            }
        }

        foreach (array_unique($recipientIds) as $userId) {
            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }

            // Inserting a stable ID makes repeated event handling idempotent.
            // The notifications.id column is unique in Laravel's default migration.
            $id = \Ramsey\Uuid\Uuid::uuid5(
                \Ramsey\Uuid\Uuid::NAMESPACE_URL,
                'delivery-status:' . $event->history->getKey() . ':user:' . $userId
            )->toString();
            DB::table('notifications')->insertOrIgnore([
                'id' => $id,
                'type' => DeliveryStatusNotification::class,
                'notifiable_type' => $user->getMorphClass(),
                'notifiable_id' => $user->getKey(),
                'data' => json_encode([
                    'delivery_id' => $delivery->getKey(),
                    'tracking_number' => $delivery->tracking_number,
                    'status' => $status,
                    'history_id' => $event->history->getKey(),
                ], JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
