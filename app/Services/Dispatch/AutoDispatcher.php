<?php
namespace App\Services\Dispatch;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Services\Deliveries\DeliveryAssignmentService;
use App\Services\Deliveries\DeliveryTransitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutoDispatcher
{
    public function run(): array
    {
        $counts = ['assigned' => 0, 'expired' => 0, 'waiting' => 0, 'failed' => 0, 'ai_used' => 0];
        if (! config('dispatch.enabled')) return $counts;
        $stale = DeliveryAssignment::where('status', 'pending')->where('is_automatic', true)->where('assigned_at', '<=', now()->subSeconds(config('dispatch.offer_seconds')))
            ->orderBy('id')->limit(100)->get();
        foreach ($stale as $assignment) {
            try {
            DB::transaction(function () use ($assignment, &$counts) {
                $delivery = Delivery::whereKey($assignment->delivery_id)->lockForUpdate()->firstOrFail();
                $offer = DeliveryAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
                if ($delivery->status !== DeliveryStatus::Assigned || $offer->status !== 'pending' || ! $offer->is_automatic
                    || $offer->assigned_at?->gt(now()->subSeconds(config('dispatch.offer_seconds')))) return;
                $offer->update(['status' => 'expired', 'expired_at' => now()]);
                app(DeliveryTransitionService::class)->transition($delivery, DeliveryStatus::Matching, $delivery->merchant->user,
                    'Automatic driver offer timed out', ['automation' => true, 'assignment_id' => $offer->id]);
                $counts['expired']++;
            }, 3);
            } catch (\Throwable $e) { $counts['failed']++; report($e); }
        }
        $aiBudget = 5;
        foreach (Delivery::where('status', DeliveryStatus::Matching)
            ->where(fn ($query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))->orderBy('dispatch_attempted_at')->orderBy('id')->limit(25)->get() as $delivery) {
            try {
                $candidates = app(DriverMatcher::class)->candidates($delivery);
                DB::table('deliveries')->where('id', $delivery->id)->update(['dispatch_attempted_at' => now()]);
                $ai = $aiBudget > 0 ? app(AiDriverRanker::class)->choose($candidates) : null;
                if ($candidates->count() >= 2) $aiBudget--;
                if ($ai) $candidates = $candidates->sortBy(fn ($row) => $row['driver']->id === $ai ? 0 : 1);
                $assigned = false;
                foreach ($candidates as $row) {
                    try {
                        app(DeliveryAssignmentService::class)->assignAutomatically($delivery, $row['driver'], $row['driver']->id === $ai ? 'ai' : 'rules');
                        $counts['assigned']++; if ($row['driver']->id === $ai) $counts['ai_used']++; $assigned = true; break;
                    } catch (ValidationException) { /* Changed capacity/status: try another eligible candidate. */ }
                }
                if (! $assigned) $counts['waiting']++;
            } catch (\Throwable $e) { $counts['failed']++; report($e); }
        }
        return $counts;
    }
}
