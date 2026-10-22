<?php

namespace App\Services\Catalog;

use App\Models\CatalogConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CatalogImporter
{
    public function validate(array $payload): array
    {
        $data = Validator::make($payload, [
            'products' => ['required', 'array', 'min:1', 'max:100'],
            'products.*' => ['required', 'array:external_id,name,description,sku,category,price_minor,currency,stock_quantity,status,image_url,product_url,source_updated_at'],
            'products.*.external_id' => ['required', 'string', 'max:120', 'distinct:strict'],
            'products.*.name' => ['required', 'string', 'max:200'],
            'products.*.description' => ['nullable', 'string', 'max:10000'],
            'products.*.sku' => ['nullable', 'string', 'max:120'],
            'products.*.category' => ['nullable', 'string', 'max:100'],
            'products.*.price_minor' => ['required', 'integer', 'min:0', 'max:999999999'],
            'products.*.currency' => ['required', 'in:AED'],
            'products.*.stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'products.*.status' => ['required', 'in:active,draft,archived'],
            'products.*.image_url' => ['nullable', 'url:https', 'max:2048'],
            'products.*.product_url' => ['nullable', 'url:https', 'max:2048'],
            'products.*.source_updated_at' => ['required', 'date_format:Y-m-d\TH:i:sP', 'before_or_equal:now'],
        ])->validate();

        return $data['products'];
    }

    public function import(CatalogConnection $connection, array $products): array
    {
        return DB::transaction(function () use ($connection, $products) {
            // Serialize writes from pull and push; also serializes disable/rotation.
            $keyHash = $connection->key_hash;
            $connection = CatalogConnection::whereKey($connection->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($connection->key_hash, $keyHash), 401, 'Integration key was rotated.');
            abort_unless($connection->status === 'active', 403, 'Connection disabled.');
            app(EnterpriseCatalogAccess::class)->assert($connection->merchant);
            $counts = ['processed' => count($products), 'changed' => 0, 'skipped' => 0];
            foreach ($products as $product) {
                $externalKey = hash('sha256', $product['external_id']);
                $existing = $connection->products()->where('external_key', $externalKey)->first();
                $updated = Carbon::parse($product['source_updated_at'])->utc();
                if ($existing && $existing->source_updated_at->greaterThanOrEqualTo($updated)) {
                    $counts['skipped']++;

                    continue;
                }
                if (! $existing && $connection->products()->count() >= config('catalog.max_products')) {
                    throw ValidationException::withMessages(['products' => 'Catalog product limit reached.']);
                }
                $connection->products()->updateOrCreate(['external_key' => $externalKey], array_merge([
                    'description' => null, 'sku' => null, 'category' => null, 'image_url' => null, 'product_url' => null,
                ], $product, ['source_updated_at' => $updated]));
                $counts['changed']++;
            }

            return $counts;
        });
    }
}
