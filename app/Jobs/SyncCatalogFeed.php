<?php

namespace App\Jobs;

use App\Models\CatalogSyncRun;
use App\Services\Catalog\CatalogFeedClient;
use App\Services\Catalog\CatalogImporter;
use App\Services\Catalog\EnterpriseCatalogAccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class SyncCatalogFeed implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public string $runId) {}

    public function handle(CatalogFeedClient $client, CatalogImporter $importer): void
    {
        $run = CatalogSyncRun::with('connection.merchant')->find($this->runId);
        if (! $run || $run->status !== 'queued') {
            return;
        }
        $lock = Cache::lock('catalog-sync:'.$run->catalog_connection_id, 650);
        if (! $lock->get()) {
            $run->update(['status' => 'failed', 'error_code' => 'SYNC_BUSY', 'finished_at' => now()]);

            return;
        }
        try {
            $run->update(['status' => 'running', 'started_at' => now()]);
            $cursor = null;
            $seen = [];
            $complete = false;
            for ($page = 0; $page < config('catalog.max_pages'); $page++) {
                $connection = $run->connection->fresh(['merchant']);
                if ($connection->status !== 'active' || $connection->mode !== 'feed') {
                    throw new RuntimeException('Connection disabled.');
                }
                app(EnterpriseCatalogAccess::class)->assert($connection->merchant);
                $data = $client->page($connection, $cursor);
                $counts = count($data['products']) ? $importer->import($connection, $importer->validate($data))
                    : ['processed' => 0, 'changed' => 0, 'skipped' => 0];
                foreach ($counts as $field => $count) {
                    $run->{$field} += $count;
                }
                $run->save();
                $cursor = $data['next_cursor'];
                if ($cursor === null) {
                    $complete = true;
                    break;
                }
                if (isset($seen[$cursor])) {
                    throw new RuntimeException('Repeated feed cursor.');
                }
                $seen[$cursor] = true;
            }
            if (! $complete) {
                throw new RuntimeException('Feed page limit exceeded.');
            }
            $run->connection->update(['last_synced_at' => now(), 'last_sync_error' => null]);
            $run->update(['status' => 'succeeded', 'finished_at' => now()]);
        } catch (Throwable $exception) {
            // Do not persist provider responses, tokens or URLs in error logs.
            $run->update(['status' => 'failed', 'error_code' => 'SYNC_FAILED', 'finished_at' => now()]);
            $run->connection->update(['last_sync_error' => 'SYNC_FAILED']);
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        CatalogSyncRun::whereKey($this->runId)->whereIn('status', ['running', 'queued'])
            ->update(['status' => 'failed', 'error_code' => 'WORKER_FAILED', 'finished_at' => now()]);
    }
}
