<?php

use App\Http\Controllers\Catalog\ConnectionController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\PublicCatalogController;
use App\Http\Controllers\Integrations\StoreDeliveryController;
use App\Http\Middleware\AuthenticateCatalogConnection;
use App\Http\Middleware\AuthenticateDeliveryConnection;
use Illuminate\Support\Facades\Route;

Route::get('/v1/catalog/products', [PublicCatalogController::class, 'index'])->middleware('throttle:60,1');
Route::middleware(['auth:sanctum', 'account.active', 'throttle:20,1'])->prefix('v1/merchant/integrations')->group(function () {
    Route::get('/', [ConnectionController::class, 'index']);
    Route::post('/', [ConnectionController::class, 'store']);
    Route::patch('/{connection}', [ConnectionController::class, 'update'])->whereNumber('connection');
    Route::post('/{connection}/rotate-key', [ConnectionController::class, 'rotate'])->whereNumber('connection');
    Route::post('/{connection}/sync', [ConnectionController::class, 'sync'])->whereNumber('connection');
    Route::get('/{connection}/runs', [ConnectionController::class, 'runs'])->whereNumber('connection');
});
Route::post('/v1/admin/catalog/{connection}/approval', [ConnectionController::class, 'approve'])
    ->middleware(['auth:sanctum', 'throttle:20,1'])->whereNumber('connection');
Route::prefix('integrations/v1')->group(function () {
    Route::get('/products', [ProductController::class, 'index'])
        ->middleware([AuthenticateCatalogConnection::class.':catalog:read', 'throttle:catalog-key']);
    Route::post('/products/batch', [ProductController::class, 'batch'])
        ->middleware([AuthenticateCatalogConnection::class.':catalog:write', 'throttle:catalog-key']);
});

Route::middleware([AuthenticateDeliveryConnection::class, 'throttle:delivery-key'])
    ->prefix('integrations/v1')->group(function () {
        Route::get('/delivery-connection', [StoreDeliveryController::class, 'info']);
        Route::post('/deliveries', [StoreDeliveryController::class, 'store']);
        Route::get('/deliveries/{order}', [StoreDeliveryController::class, 'show'])->whereUuid('order');
    });
