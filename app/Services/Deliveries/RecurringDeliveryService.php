<?php

namespace App\Services\Deliveries;

use App\Models\RecurringDelivery;
use App\Models\RecurringDeliveryRun;
use App\Services\Billing\AutomationSubscriptionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecurringDeliveryService
{
    public function __construct(
        private readonly DeliveryCreationService $creation
    ) {}

    public function execute(int $recurringDeliveryId): void
    {
        try {
            DB::transaction(function () use ($recurringDeliveryId) {
                $schedule = RecurringDelivery::query()
                    ->lockForUpdate()
                    ->find($recurringDeliveryId);

                if (! $schedule || ! $schedule->is_active) {
                    return;
                }

                if ($schedule->next_run_at->isFuture()) {
                    return;
                }

                $scheduledFor = $schedule->next_run_at->copy();

                $run = RecurringDeliveryRun::firstOrCreate(
                    [
                        'recurring_delivery_id' => $schedule->id,
                        'scheduled_for' => $scheduledFor,
                    ],
                    ['status' => 'pending']
                );

                if ($run->delivery_id !== null || $run->status === 'completed') {
                    $schedule->update([
                        'next_run_at' => $this->nextRun($schedule, $scheduledFor),
                    ]);

                    return;
                }

                // Skip expired occurrences instead of creating backdated deliveries.
                if ($scheduledFor->lt(now()->subMinutes(15))) {
                    $run->update([
                        'status' => 'skipped',
                        'error_message' => 'Scheduled occurrence expired.',
                        'executed_at' => now(),
                    ]);

                    $schedule->update([
                        'last_run_at' => $scheduledFor,
                        'next_run_at' => $this->nextRun($schedule, $scheduledFor),
                    ]);

                    return;
                }

                // Verify subscription access before generating a delivery.
                app(AutomationSubscriptionPolicy::class)
                    ->activeSubscription($schedule->merchant);

                $delivery = $this->creation->create(
                    $schedule->merchant,
                    [
                        'pickup_address_id' => $schedule->pickup_address_id,
                        'dropoff_address_id' => $schedule->dropoff_address_id,
                        'recipient_name' => $schedule->recipient_name,
                        'recipient_phone' => $schedule->recipient_phone,
                        'package_type' => $schedule->package_type,
                        'weight' => $schedule->weight,
                        'special_instructions' => $schedule->special_instructions,
                        'scheduled_at' => $scheduledFor,
                    ],
                    null,
                    'Delivery created by automation'
                );

                $run->update([
                    'delivery_id' => $delivery->id,
                    'status' => 'completed',
                    'executed_at' => now(),
                    'error_message' => null,
                ]);

                $schedule->update([
                    'last_run_at' => $scheduledFor,
                    'next_run_at' => $this->nextRun($schedule, $scheduledFor),
                ]);
            }, 3);
        } catch (Throwable $e) {
            Log::error('Recurring delivery execution failed', [
                'recurring_delivery_id' => $recurringDeliveryId,
                'exception' => $e,
            ]);

            // Preserve the failure for investigation.
            // A retry policy will be added with the scheduler.
            throw $e;
        }
    }

    private function nextRun(
        RecurringDelivery $schedule,
        CarbonImmutable|\DateTimeInterface $previous
    ): CarbonImmutable {
        $local = CarbonImmutable::instance($previous)
            ->setTimezone($schedule->timezone);

        $date = match ($schedule->frequency) {
            'daily' => $local->addDay(),
            'weekly' => $local->addWeek(),
            default => throw new \InvalidArgumentException(
                'Unsupported recurring frequency.'
            ),
        };

        [$hour, $minute] = array_map(
            'intval',
            explode(':', substr($schedule->execution_time, 0, 5))
        );

        return $date
            ->setTime($hour, $minute)
            ->utc();
    }
}
