<?php

namespace App\Services\Dashboard;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsService
{
    public function get(int $days = 30): array
    {
        $days = in_array($days, [7, 30, 90], true)
            ? $days
            : 30;

        $today = CarbonImmutable::today();

        $start = $today
            ->subDays($days - 1)
            ->startOfDay();

        $end = $today->endOfDay();

        // Cohort: deliveries created during the period.
        $deliveries = Delivery::query()
            ->whereBetween('created_at', [$start, $end]);

        $total = (clone $deliveries)->count();

        $completed = (clone $deliveries)
            ->where('status', DeliveryStatus::Completed->value)
            ->count();

        $successRate = $total > 0
            ? round(($completed / $total) * 100, 1)
            : 0.0;

        // Deliveries created per day.
        $createdByDay = (clone $deliveries)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'day');

        // First recorded completion for each delivery.
        // This avoids counting repeated completion events.
        $firstCompletions = DeliveryStatusHistory::query()
            ->select('delivery_id')
            ->selectRaw('MIN(created_at) as completed_at')
            ->where('to_status', DeliveryStatus::Completed->value)
            ->groupBy('delivery_id');

        // Completion events within the selected period.
        $completedByDay = DB::query()
            ->fromSub($firstCompletions, 'completions')
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw(
                'DATE(completed_at) as day, COUNT(*) as total'
            )
            ->groupBy(DB::raw('DATE(completed_at)'))
            ->pluck('total', 'day');

        $trends = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i)->toDateString();

            $trends[] = [
                'date' => $date,
                'created' => (int) ($createdByDay[$date] ?? 0),
                'completed' => (int) ($completedByDay[$date] ?? 0),
            ];
        }

        // Attribute a completed delivery to its latest
        // accepted assignment, if one exists.

        $driverPerformance = DB::query()
            ->fromSub($firstCompletions, 'completions')
            ->join(
                'deliveries',
                'deliveries.id',
                '=',
                'completions.delivery_id'
            )
            ->join(
                'proof_of_deliveries',
                'proof_of_deliveries.delivery_id',
                '=',
                'deliveries.id'
            )
            ->join(
                'drivers',
                'drivers.id',
                '=',
                'proof_of_deliveries.driver_id'
            )
            ->join(
                'users',
                'users.id',
                '=',
                'drivers.user_id'
            )
            ->where(
                'deliveries.status',
                DeliveryStatus::Completed->value
            )
            ->whereBetween(
                'completions.completed_at',
                [$start, $end]
            )
            ->select('drivers.id', 'users.name')
            ->selectRaw(
                'COUNT(DISTINCT deliveries.id) as completed_deliveries'
            )
            ->groupBy('drivers.id', 'users.name')
            ->orderByDesc('completed_deliveries')
            ->limit(10)
            ->get();


        return [
            'days' => $days,
            'total' => $total,
            'completed' => $completed,
            'success_rate' => $successRate,
            'trends' => $trends,
            'driver_performance' => $driverPerformance,
        ];
    }
}
