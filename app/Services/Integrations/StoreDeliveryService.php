<?php

namespace App\Services\Integrations;

use App\Enums\DeliveryStatus;
use App\Models\CatalogConnection;
use App\Models\IntegrationDeliveryOrder;
use App\Services\Catalog\EnterpriseCatalogAccess;
use App\Services\Deliveries\DeliveryCreationService;
use App\Services\Deliveries\DeliveryTransitionService;
use App\Services\Pricing\AutomaticDeliveryPricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreDeliveryService
{
    public function create(CatalogConnection $connection, string $key, array $data): array
    {
        // Canonical validated data, independent of JSON object key order.
        $sort = function (array $value) use (&$sort): array {
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return $value;
        };
        $hash = hash('sha256', json_encode($sort($data), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($connection, $key, $data, $hash) {
            $currentHash = $connection->delivery_key_hash;
            $connection = CatalogConnection::whereKey($connection->id)->lockForUpdate()->firstOrFail();
            abort_unless($connection->delivery_enabled && $connection->status === 'active' && hash_equals($currentHash, (string) $connection->delivery_key_hash), 401);
            app(EnterpriseCatalogAccess::class)->assert($connection->merchant);
            $query = IntegrationDeliveryOrder::where('catalog_connection_id', $connection->id);
            $byKey = (clone $query)->where('idempotency_hash', hash('sha256', $key))->first();
            $byReference = (clone $query)->where('external_order_hash', hash('sha256', $data['external_order_id']))->first();
            $existing = $byKey ?? $byReference;
            if ($existing) {
                abort_unless($existing->external_order_id === $data['external_order_id'] && hash_equals($existing->idempotency_hash, hash('sha256', $key)) && hash_equals($existing->payload_hash, $hash), 409, 'Order reference or idempotency key already exists with different data.');

                return [$existing, false];
            }
            $merchant = $connection->merchant;
            $pickup = $merchant->addresses()->find($connection->delivery_pickup_address_id);
            if (! $pickup) {
                throw ValidationException::withMessages(['pickup' => 'Configure an owned pickup address in the Raslo dashboard.']);
            }
            $address = $merchant->addresses()->create($data['recipient'] + ['label' => 'Store order '.$data['external_order_id'], 'country' => 'United Arab Emirates']);
            $delivery = app(DeliveryCreationService::class)->create($merchant, [
                'pickup_address_id' => $pickup->id, 'dropoff_address_id' => $address->id,
                'recipient_name' => $data['recipient']['contact_name'], 'recipient_phone' => $data['recipient']['contact_phone'],
                'package_type' => $data['package_description'] ?? 'Store order',
                'special_instructions' => $data['instructions'] ?? null,
            ], $merchant->user_id, 'Created by store delivery integration');
            $token = bin2hex(random_bytes(32));
            $order = IntegrationDeliveryOrder::create([
                'id' => (string) Str::uuid(), 'catalog_connection_id' => $connection->id, 'delivery_id' => $delivery->id,
                'external_order_id' => $data['external_order_id'], 'external_order_hash' => hash('sha256', $data['external_order_id']),
                'idempotency_hash' => hash('sha256', $key), 'payload_hash' => $hash, 'payload' => $data,
                'tracking_token' => $token, 'tracking_hash' => hash('sha256', $token),
            ]);
            $submitted = app(DeliveryTransitionService::class)->transition($delivery, DeliveryStatus::PendingPricing, $merchant->user, 'Submitted by store integration');
            app(AutomaticDeliveryPricingService::class)->price($submitted, $merchant->user);

            return [$order, true];
        });
    }

    public function trackingUrl(IntegrationDeliveryOrder $order): ?string
    {
        $base = rtrim((string) config('delivery_integrations.public_url'), '/');
        if (! str_starts_with($base, 'https://') || $order->created_at->lt(now()->subDays(config('delivery_integrations.tracking_days')))) {
            return null;
        }

        return $base.'/track/'.$order->tracking_token;
    }

    public function data(IntegrationDeliveryOrder $order): array
    {
        return DB::transaction(function () use ($order) {
            // A status and sequence must describe the same committed delivery state.
            $delivery = $order->delivery()->lockForUpdate()->firstOrFail();

            return ['id' => $order->id, 'external_order_id' => $order->external_order_id,
                'delivery_id' => $delivery->id, 'tracking_number' => $delivery->tracking_number,
                'status' => $delivery->status->value, 'sequence' => (int) $delivery->statusHistories()->max('id'),
                'currency' => 'AED', 'delivery_fee' => $delivery->delivery_fee,
                'requires_price_approval' => $delivery->status === DeliveryStatus::Priced,
                'tracking_url' => $this->trackingUrl($order)];
        });
    }
}
