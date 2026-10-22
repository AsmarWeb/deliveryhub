<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\CatalogSyncRun;
use App\Services\Catalog\CatalogImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $connection = $request->attributes->get('catalog_connection');

        return $connection->products()->orderBy('id')->paginate(50);
    }

    public function batch(Request $request, CatalogImporter $importer)
    {
        $connection = $request->attributes->get('catalog_connection');
        $products = $importer->validate($request->all());
        $result = DB::transaction(function () use ($connection, $products, $importer) {
            $counts = $importer->import($connection, $products);
            $run = CatalogSyncRun::create(array_merge($counts, ['id' => (string) Str::uuid(),
                'catalog_connection_id' => $connection->id, 'status' => 'succeeded', 'started_at' => now(), 'finished_at' => now()]));
            $connection->update(['last_synced_at' => now(), 'last_sync_error' => null]);

            return ['sync_run_id' => $run->id] + $counts;
        });

        return response()->json($result);
    }
}
