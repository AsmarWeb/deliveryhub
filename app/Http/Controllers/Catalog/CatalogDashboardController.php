<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\CatalogConnection;
use Illuminate\Http\Request;

class CatalogDashboardController extends Controller
{
    public function index(Request $request, ConnectionController $connections)
    {
        $merchant = $connections->merchant($request);

        return response()->view('catalog.manage', [
            'connections' => CatalogConnection::where('merchant_id', $merchant->id)->withCount('products')->with(['runs' => fn ($q) => $q->latest()->limit(5)])->latest()->get(),
        ])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
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

        return redirect()->route('merchant.catalog.index')->with('status', 'Connection updated.');
    }

    public function sync(Request $request, int $connection, ConnectionController $connections)
    {
        $connections->sync($request, $connection);

        return redirect()->route('merchant.catalog.index')->with('status', 'Synchronization requested.');
    }

    public function admin(Request $request)
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        return view('catalog.approve', ['connections' => CatalogConnection::with('merchant')->withCount('products')->latest()->paginate(30)]);
    }

    public function approve(Request $request, int $connection, ConnectionController $connections)
    {
        $connections->approve($request, $connection);

        return redirect()->route('admin.catalog.index')->with('status', 'Catalog visibility updated.');
    }
}
