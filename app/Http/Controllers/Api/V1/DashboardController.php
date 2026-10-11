<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return $this->adminDashboard($user);
        }

        if ($user->hasRole('merchant')) {
            return $this->merchantDashboard($user);
        }

        if ($user->hasRole('driver')) {
            return $this->driverDashboard($user);
        }

        abort(403, 'Dashboard access denied.');
    }

    private function adminDashboard($user): JsonResponse
    {
        $activeStatuses = [
            DeliveryStatus::Assigned->value,
            DeliveryStatus::Accepted->value,
            DeliveryStatus::Pickup->value,
            DeliveryStatus::InTransit->value,
        ];

        return response()->json([
            'role' => 'admin',
            'data' => [
                'total_deliveries' => Delivery::count(),

                'active_deliveries' => Delivery::whereIn(
                    'status',
                    $activeStatuses
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
        ]);
    }

    private function merchantDashboard($user): JsonResponse
    {
        $merchant = Merchant::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $deliveries = Delivery::where(
            'merchant_id',
            $merchant->id
        );

        return response()->json([
            'role' => 'merchant',
            'data' => [
                'total_deliveries' => (clone $deliveries)
                    ->count(),

                'pending_deliveries' => (clone $deliveries)
                    ->whereIn('status', [
                        DeliveryStatus::Draft->value,
                        DeliveryStatus::PendingPricing->value,
                        DeliveryStatus::Priced->value,
                        DeliveryStatus::Matching->value,
                    ])->count(),

                'active_deliveries' => (clone $deliveries)
                    ->whereIn('status', [
                        DeliveryStatus::Assigned->value,
                        DeliveryStatus::Accepted->value,
                        DeliveryStatus::Pickup->value,
                        DeliveryStatus::InTransit->value,
                    ])->count(),

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
        ]);
    }

    private function driverDashboard($user): JsonResponse
    {
        $driver = Driver::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $assignments = DeliveryAssignment::where(
            'driver_id',
            $driver->id
        );

        return response()->json([
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
        ]);
    }
}
