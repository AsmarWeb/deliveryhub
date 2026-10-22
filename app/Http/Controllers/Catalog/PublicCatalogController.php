<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\PublicCatalog;
use Illuminate\Http\Request;

class PublicCatalogController extends Controller
{
    public function index(Request $request, PublicCatalog $catalog)
    {
        $products = $catalog->query($request)->paginate(24);
        $products->through(fn ($product) => $catalog->resource($product));

        return response()->json($products)->header('Cache-Control', 'no-store');
    }

    public function web(Request $request, PublicCatalog $catalog)
    {
        return response()->view('catalog.index', ['products' => $catalog->query($request)->paginate(24)->withQueryString()])
            ->header('Cache-Control', 'no-store');
    }
}
