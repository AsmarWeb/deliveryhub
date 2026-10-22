<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\CatalogConnection;
use App\Models\Merchant;
use App\Services\Catalog\CatalogFeedClient;
use App\Services\Catalog\CatalogImporter;
use App\Services\Catalog\EnterpriseCatalogAccess;
use Illuminate\Http\Request;

class CatalogDashboardController extends Controller
{
    private function merchant(Request $request): Merchant
    {
        abort_unless($request->user()->hasRole('merchant'), 403);

        return Merchant::where('user_id', $request->user()->id)->firstOrFail();
    }

    private function context(Request $request): array
    {
        $merchant = $this->merchant($request);

        return ['merchant' => $merchant, 'canConnect' => app(EnterpriseCatalogAccess::class)->allows($merchant)];
    }

    public function index(Request $request, ConnectionController $connections)
    {
        $context = $this->context($request);
        $merchant = $context['merchant'];

        return response()->view('catalog.manage', $context + [
            'connections' => CatalogConnection::where('merchant_id', $merchant->id)->withCount('products')->with(['runs' => fn ($q) => $q->latest()->limit(5)])->latest()->get(),
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function platforms(Request $request)
    {
        return response()->view('catalog.platforms', $this->context($request))
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function guide(Request $request, string $platform)
    {
        abort_unless(in_array($platform, ['woocommerce', 'api'], true), 404);

        return response()->view('catalog.guide', $this->context($request) + [
            'platform' => $platform, 'pluginVersion' => '0.1.0',
            'pluginChecksum' => is_file(resource_path('integrations/raslo-woocommerce.zip'))
                ? hash_file('sha256', resource_path('integrations/raslo-woocommerce.zip')) : null,
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function download(Request $request, ConnectionController $connections)
    {
        $this->merchant($request);
        $connections->merchant($request); // The download is part of the paid Enterprise integration.
        $path = resource_path('integrations/raslo-woocommerce.zip');
        abort_unless(is_file($path), 503, 'The connector download is temporarily unavailable.');

        return response()->download($path, 'raslo-woocommerce.zip', [
            'Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function contract(Request $request, ConnectionController $connections)
    {
        $this->merchant($request);
        $connections->merchant($request);

        return response()->download(base_path('docs/catalog-openapi.json'), 'raslo-catalog-openapi.json', [
            'Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function check(Request $request, int $connection, ConnectionController $connections)
    {
        $merchant = $connections->merchant($request);
        $owned = CatalogConnection::where('merchant_id', $merchant->id)->findOrFail($connection);
        abort_unless($owned->mode === 'feed' && $owned->status === 'active', 409);
        try {
            // Reuse the approved-host, public-IP-pinned client. One read, no import or approval.
            $page = app(CatalogFeedClient::class)->page($owned, null);
            if ($page['products'] !== []) {
                app(CatalogImporter::class)->validate(['products' => $page['products']]);
            }
            $result = ['id' => $owned->id, 'ok' => true, 'count' => count($page['products'])];
        } catch (\Throwable $exception) {
            // Never flash upstream payloads, exception messages, credentials or URLs with secrets.
            $result = ['id' => $owned->id, 'ok' => false];
        }

        return redirect()->route('merchant.catalog.index')->with('connection_check', $result);
    }

    public function store(Request $request, ConnectionController $connections)
    {
        $request->merge(['scopes' => ['catalog:read', 'catalog:write']]);
        $response = $connections->store($request)->getData(true);

        return redirect()->route('merchant.catalog.index')->with('catalog_key', $response['api_key']);
    }

    public function rotate(Request $request, int $connection, ConnectionController $connections)
    {
        $response = $connections->rotate($request, $connection)->getData(true);

        return redirect()->route('merchant.catalog.index')->with('catalog_key', $response['api_key']);
    }

    public function update(Request $request, int $connection, ConnectionController $connections)
    {
        $connections->update($request, $connection);

        return redirect()->route('merchant.catalog.index')->with('status', app()->getLocale() === 'ar' ? 'تم تحديث الربط.' : 'Connection updated.');
    }

    public function sync(Request $request, int $connection, ConnectionController $connections)
    {
        $connections->sync($request, $connection);

        return redirect()->route('merchant.catalog.index')->with('status', app()->getLocale() === 'ar' ? 'تم طلب المزامنة؛ راجع سجل المحاولات.' : 'Synchronization requested; check the attempt history.');
    }

    public function admin(Request $request)
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'review' => ['nullable', 'in:pending,approved'],
        ]);
        $query = CatalogConnection::with(['merchant', 'products' => fn ($q) => $q->latest()->limit(6)])->withCount('products');
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)
                ->orWhereHas('merchant', fn ($q) => $q->where('business_name', 'like', $term)));
        }
        if (($filters['review'] ?? null) === 'pending') {
            $query->whereNull('approved_at');
        } elseif (($filters['review'] ?? null) === 'approved') {
            $query->whereNotNull('approved_at');
        }

        return response()->view('catalog.approve', [
            'connections' => $query->latest()->paginate(20)->withQueryString(),
            'pendingCount' => CatalogConnection::whereNull('approved_at')->count(),
            'approvedCount' => CatalogConnection::whereNotNull('approved_at')->count(),
        ])->header('Cache-Control', 'no-store');
    }

    public function approve(Request $request, int $connection, ConnectionController $connections)
    {
        $connections->approve($request, $connection);

        return redirect()->route('admin.catalog.index')->with('status', app()->getLocale() === 'ar' ? 'تم تحديث اعتماد نشر المتجر.' : 'Store publication approval updated.');
    }
}
