<?php

namespace App\Services\Dashboard;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\User;

class DashboardService
{
    public function forUser(User $user): array
    {
        if ($user->hasRole('admin')) {
            return $this->admin($user);
        }

        if ($user->hasRole('merchant')) {
            return $this->merchant($user);
        }

        if ($user->hasRole('driver')) {
            return $this->driver($user);
        }

        abort(403, 'Dashboard access denied.');
    }

    private function activeStatuses(): array
    {
        return [
            DeliveryStatus::Assigned->value,
            DeliveryStatus::Accepted->value,
            DeliveryStatus::Pickup->value,
            DeliveryStatus::InTransit->value,
        ];
    }

    private function admin(User $user): array
    {
        return [
            'role' => 'admin',
            'data' => [
                'total_deliveries' => Delivery::count(),

                'active_deliveries' => Delivery::whereIn(
                    'status',
                    $this->activeStatuses()
                )->count(),

                'completed_deliveries' => Delivery::where(
                    'status',
                    DeliveryStatus::Completed->value
                )->count(),

                'pending_pricing' => Delivery::where(
                    'status',
                    DeliveryStatus::PendingPricing->value
                )->count(),

                'total_merchants' => Merchant::count(),

                'total_drivers' => Driver::count(),

                'available_drivers' => Driver::where(
                    'is_available',
                    true
                )->count(),

                'unread_notifications' => $user
                    ->unreadNotifications()
                    ->count(),
            ],
        ];
    }

    private function merchant(User $user): array
    {
        $merchant = Merchant::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $deliveries = Delivery::where(
            'merchant_id',
            $merchant->id
        );

        return [
            'role' => 'merchant',
            'data' => [
                'total_deliveries' => (clone $deliveries)->count(),

                'pending_deliveries' => (clone $deliveries)
                    ->whereIn('status', [
                        DeliveryStatus::Draft->value,
                        DeliveryStatus::PendingPricing->value,
                        DeliveryStatus::Priced->value,
                        DeliveryStatus::Matching->value,
                    ])->count(),

                'active_deliveries' => (clone $deliveries)
                    ->whereIn(
                        'status',
                        $this->activeStatuses()
                    )->count(),

                'completed_deliveries' => (clone $deliveries)
                    ->where(
                        'status',
                        DeliveryStatus::Completed->value
                    )->count(),

                'recent_deliveries' => (clone $deliveries)
                    ->latest()
                    ->limit(5)
                    ->get([
                        'id',
                        'tracking_number',
                        'status',
                        'created_at',
                    ]),

                'unread_notifications' => $user
                    ->unreadNotifications()
                    ->count(),
            ],
        ];
    }

    private function driver(User $user): array
    {
        $driver = Driver::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $assignments = DeliveryAssignment::where(
            'driver_id',
            $driver->id
        );

        return [
            'role' => 'driver',
            'data' => [
                'pending_assignments' => (clone $assignments)
                    ->where('status', 'pending')
                    ->count(),

                'accepted_assignments' => (clone $assignments)
                    ->where('status', 'accepted')
                    ->count(),

                'completed_deliveries' => Delivery::where(
                    'status',
                    DeliveryStatus::Completed->value
                )->whereHas('assignments', function ($query) use ($driver) {
                    $query->where('driver_id', $driver->id)
                        ->where('status', 'accepted');
                })->count(),

                'is_available' => $driver->is_available,

                'unread_notifications' => $user
                    ->unreadNotifications()
                    ->count(),
            ],
        ];
    }
}
