<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Catalog\ConnectionController;
use App\Http\Controllers\Controller;
use App\Models\CatalogConnection;
use App\Models\IntegrationDeliveryOrder;
use App\Models\IntegrationDeliveryWebhook;
use App\Services\Catalog\CatalogFeedClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeliveryConnectionSettingsController extends Controller
{
    private function owned(Request $request, int $connection): CatalogConnection
    {
        $merchant = app(ConnectionController::class)->merchant($request);

        return CatalogConnection::where('merchant_id', $merchant->id)->findOrFail($connection);
    }

    public function contract(Request $request)
    {
        app(ConnectionController::class)->merchant($request);

        return response()->download(base_path('docs/delivery-openapi.json'), 'raslo-delivery-openapi.json', ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store']);
    }

    public function show(Request $request, int $connection)
    {
        $owned = $this->owned($request, $connection);

        return response()->view('catalog.deliveries', [
            'connection' => $owned, 'addresses' => $owned->merchant->addresses()->orderBy('label')->get(),
            'orders' => IntegrationDeliveryOrder::where('catalog_connection_id', $owned->id)->with('delivery')->latest()->paginate(15),
            'webhooks' => IntegrationDeliveryWebhook::whereHas('order', fn ($q) => $q->where('catalog_connection_id', $owned->id))->latest()->limit(5)->get(),
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function update(Request $request, int $connection)
    {
        $owned = $this->owned($request, $connection);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'pickup_address_id' => ['nullable', 'required_if:enabled,1', 'integer', Rule::exists('addresses', 'id')->where('merchant_id', $owned->merchant_id)],
            'webhook_url' => ['nullable', 'url:https', 'max:2048'], 'rotate' => ['nullable', 'boolean'],
        ]);
        if (! empty($data['webhook_url'])) {
            app(CatalogFeedClient::class)->hostname($data['webhook_url']);
        }
        $secrets = DB::transaction(function () use ($owned, $data) {
            $row = CatalogConnection::whereKey($owned->id)->lockForUpdate()->firstOrFail();
            $key = $secret = null;
            $row->delivery_enabled = (bool) $data['enabled'];
            if (! $row->delivery_enabled) {
                $row->delivery_key_hash = null;
            } elseif (! $row->delivery_key_hash || ! empty($data['rotate'])) {
                $key = 'raslo_delivery_'.bin2hex(random_bytes(32));
                $row->delivery_key_hash = hash('sha256', $key);
                $row->delivery_key_prefix = substr($key, 0, 23);
            }
            $row->delivery_pickup_address_id = $data['pickup_address_id'] ?? null;
            $row->delivery_webhook_url = $data['webhook_url'] ?? null;
            if ($row->delivery_webhook_url && (! $row->delivery_webhook_secret || ! empty($data['rotate']))) {
                $secret = bin2hex(random_bytes(32));
                $row->delivery_webhook_secret = $secret;
            }
            $row->save();

            return array_filter(['api_key' => $key, 'webhook_secret' => $secret]);
        });

        return redirect()->route('merchant.catalog.deliveries', $owned)->with('delivery_credentials', $secrets)->with('status', app()->getLocale() === 'ar' ? 'تم تحديث إعدادات التوصيل.' : 'Delivery settings updated.');
    }
}
