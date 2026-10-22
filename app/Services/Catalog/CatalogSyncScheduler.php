<?php

namespace App\Services\Catalog;

use App\Jobs\SyncCatalogFeed;
use App\Models\CatalogConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogSyncScheduler
{
    public function request(CatalogConnection $connection): ?string
    {
        $run = DB::transaction(function () use ($connection) {
            $connection = CatalogConnection::whereKey($connection->id)->lockForUpdate()->firstOrFail();
            abort_unless($connection->status === 'active' && $connection->mode === 'feed', 409, 'No active feed on this connection.');
            app(EnterpriseCatalogAccess::class)->assert($connection->merchant);
            // Recover jobs lost after a worker crash without blocking future synchronization forever.
            $connection->runs()->whereIn('status', ['queued', 'running'])->where('updated_at', '<', now()->subHour())
                ->update(['status' => 'failed', 'error_code' => 'WORKER_TIMEOUT', 'finished_at' => now()]);
            if ($connection->runs()->whereIn('status', ['queued', 'running'])->exists()) {
                return null;
            }
            $run = $connection->runs()->create(['id' => (string) Str::uuid(), 'status' => 'queued']);
            $connection->update(['next_sync_at' => now()->addMinutes(config('catalog.sync_minutes'))]);

            return $run;
        });
        if ($run) {
            try {
                SyncCatalogFeed::dispatch($run->id)->afterCommit();
            } catch (\Throwable $exception) {
                $run->update(['status' => 'failed', 'error_code' => 'QUEUE_UNAVAILABLE', 'finished_at' => now()]);
                throw $exception;
            }
        }

        return $run?->id;
    }
}
