<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\CatalogConnection;
use App\Models\Merchant;
use App\Services\Catalog\CatalogFeedClient;
use App\Services\Catalog\CatalogSyncScheduler;
use App\Services\Catalog\EnterpriseCatalogAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConnectionController extends Controller
{
    public function merchant(Request $request): Merchant
    {
        $merchant = Merchant::where('user_id', $request->user()->id)->firstOrFail();
        abort_unless($request->user()->hasRole('merchant'), 403);
        app(EnterpriseCatalogAccess::class)->assert($merchant);

        return $merchant;
    }

    private function owned(Request $request, int $connection): CatalogConnection
    {
        return CatalogConnection::where('merchant_id', $this->merchant($request)->id)->findOrFail($connection);
    }

    public function index(Request $request)
    {
        return response()->json(['data' => CatalogConnection::where('merchant_id', $this->merchant($request)->id)->withCount('products')->latest()->get()]);
    }

    public function store(Request $request)
    {
        $merchant = $this->merchant($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'mode' => ['required', 'in:push,feed'],
            'feed_url' => ['required_if:mode,feed', 'nullable', 'url:https', 'max:2048'],
            'feed_token' => ['required_if:mode,feed', 'nullable', 'string', 'min:16', 'max:500', 'regex:/^[A-Za-z0-9._~+\/=\-]+$/'],
            'scopes' => ['required', 'array', 'min:1', 'max:2'], 'scopes.*' => ['required', 'in:catalog:read,catalog:write', 'distinct'],
        ]);
        if ($data['mode'] === 'feed') {
            app(CatalogFeedClient::class)->hostname($data['feed_url']);
        }
        $token = 'raslo_live_'.bin2hex(random_bytes(32));
        $connection = DB::transaction(function () use ($merchant, $data, $token) {
            Merchant::whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            abort_if(CatalogConnection::where('merchant_id', $merchant->id)->count() >= config('catalog.max_connections'), 422, 'Store connection limit reached.');

            return CatalogConnection::create([
                'merchant_id' => $merchant->id, 'name' => $data['name'], 'mode' => $data['mode'], 'scopes' => $data['scopes'],
                'key_hash' => hash('sha256', $token), 'key_prefix' => substr($token, 0, 20),
                'feed_url' => $data['mode'] === 'feed' ? $data['feed_url'] : null,
                'feed_token' => $data['mode'] === 'feed' ? $data['feed_token'] : null,
            ]);
        });
        $runId = $connection->mode === 'feed' ? app(CatalogSyncScheduler::class)->request($connection) : null;

        return response()->json(['data' => $connection->fresh(), 'api_key' => $token, 'sync_run_id' => $runId], 201)->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, int $connection)
    {
        $owned = $this->owned($request, $connection);
        $data = $request->validate([
            'status' => ['sometimes', 'required', 'in:active,disabled'],
            'feed_url' => ['sometimes', 'required', 'url:https', 'max:2048'],
            'feed_token' => ['sometimes', 'required', 'string', 'min:16', 'max:500', 'regex:/^[A-Za-z0-9._~+\/=\-]+$/'],
        ]);
        abort_if($data === [], 422, 'No connection settings supplied.');
        if (isset($data['feed_url']) || isset($data['feed_token'])) {
            abort_unless($owned->mode === 'feed', 422, 'Feed credentials require a Feed connection.');
        }
        if (isset($data['feed_url'])) {
            app(CatalogFeedClient::class)->hostname($data['feed_url']);
        }
        DB::transaction(function () use ($owned, $data) {
            CatalogConnection::whereKey($owned->id)->lockForUpdate()->firstOrFail()->update($data);
        });
        $owned->refresh();
        if ($owned->status === 'active' && $owned->mode === 'feed') {
            app(CatalogSyncScheduler::class)->request($owned);
        }

        return response()->json(['data' => $owned]);
    }

    public function rotate(Request $request, int $connection)
    {
        $owned = $this->owned($request, $connection);
        $token = 'raslo_live_'.bin2hex(random_bytes(32));
        DB::transaction(function () use ($owned, $token) {
            CatalogConnection::whereKey($owned->id)->lockForUpdate()->firstOrFail()->update(['key_hash' => hash('sha256', $token), 'key_prefix' => substr($token, 0, 20)]);
        });

        return response()->json(['api_key' => $token])->header('Cache-Control', 'no-store');
    }

    public function sync(Request $request, int $connection)
    {
        $owned = $this->owned($request, $connection);
        $runId = app(CatalogSyncScheduler::class)->request($owned);

        return response()->json(['sync_run_id' => $runId, 'message' => $runId ? 'Synchronization queued.' : 'Synchronization already queued or running.'], 202);
    }

    public function runs(Request $request, int $connection)
    {
        return $this->owned($request, $connection)->runs()->latest()->paginate(20);
    }

    public function approve(Request $request, int $connection)
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $data = $request->validate(['approved' => ['required', 'boolean']]);
        $owned = CatalogConnection::findOrFail($connection);
        $owned->update(['approved_at' => $data['approved'] ? now() : null]);

        return response()->json(['data' => $owned]);
    }
}
