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
            $failure = DB::transaction(function () use ($recurringDeliveryId): ?Throwable {
                $schedule = RecurringDelivery::query()
                    ->lockForUpdate()
                    ->find($recurringDeliveryId);

                if (! $schedule || ! $schedule->is_active) {
                    return null;
                }

                if ($schedule->next_run_at->isFuture()) {
                    return null;
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

                    return null;
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

                    return null;
                }

                try {
                    // A savepoint rolls back partial delivery writes while the outer
                    // transaction keeps the occurrence locked and records the failure.
                    $delivery = DB::transaction(function () use ($schedule, $scheduledFor) {
                        $subscription = app(AutomationSubscriptionPolicy::class)
                            ->activeSubscription($schedule->merchant);

                        if ($schedule->merchant->status !== 'active' ||
                            $subscription->plan->max_active_automations <= 0) {
                            throw new \RuntimeException('Automation access is no longer active.');
                        }

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

                        return $delivery;
                    });
                } catch (Throwable $e) {
                    $run->update([
                        'status' => 'failed',
                        'error_message' => 'Execution failed. See application logs for details.',
                        'executed_at' => now(),
                    ]);

                    // Keep next_run_at unchanged: the next minute retries this same
                    // occurrence, until the existing 15-minute expiry skips it.
                    return $e;
                }

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

                return null;
            }, 3);

            if ($failure !== null) {
                throw $failure;
            }
        } catch (Throwable $e) {
            Log::error('Recurring delivery execution failed', [
                'recurring_delivery_id' => $recurringDeliveryId,
                'exception' => $e,
            ]);

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
