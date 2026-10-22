<?php

namespace App\Services\Catalog;

use App\Models\CatalogProduct;
use Illuminate\Http\Request;

class PublicCatalog
{
    public function query(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'store' => ['nullable', 'integer', 'min:1'],
            'category' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1', 'max:10000']]);

        return CatalogProduct::visible()->with('connection.merchant')
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($data['store'] ?? null, fn ($q, $store) => $q->where('catalog_connection_id', $store))
            ->when($data['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->orderByDesc('id');
    }

    public function resource(CatalogProduct $product): array
    {
        return [
            'id' => $product->id, 'name' => $product->name, 'description' => $product->description,
            'category' => $product->category, 'price_minor' => $product->price_minor, 'currency' => $product->currency,
            'image_url' => $product->image_url, 'product_url' => $product->product_url,
            'store' => ['id' => $product->catalog_connection_id, 'name' => $product->connection->name,
                'merchant_name' => $product->connection->merchant->business_name],
        ];
    }
}
