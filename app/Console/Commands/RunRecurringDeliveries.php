<?php

namespace App\Console\Commands;

use App\Models\RecurringDelivery;
use App\Services\Deliveries\RecurringDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RunRecurringDeliveries extends Command
{
    protected $signature = 'deliveries:run-recurring
        {--limit=50 : Maximum schedules to process}';

    protected $description = 'Generate deliveries from due recurring schedules';

    public function handle(RecurringDeliveryService $service): int
    {
        $limit = (int) $this->option('limit');

        if ($limit < 1 || $limit > 500) {
            $this->error('Limit must be between 1 and 500.');

            return self::FAILURE;
        }

        $lock = Cache::lock('deliveries:run-recurring', 300);

        if (! $lock->get()) {
            $this->warn('Recurring delivery processor is already running.');

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        try {
            $ids = RecurringDelivery::query()
                ->where('is_active', true)
                ->where('next_run_at', '<=', now())
                ->orderBy('next_run_at')
                ->limit($limit)
                ->pluck('id');

            foreach ($ids as $id) {
                try {
                    $service->execute((int) $id);
                    $processed++;
                } catch (Throwable $e) {
                    $failed++;

                    $this->error(
                        "Recurring schedule {$id} failed: {$e->getMessage()}"
                    );
                }
            }
        } finally {
            $lock->release();
        }

        $this->info(
            "Processed: {$processed} | Failed: {$failed}"
        );

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
