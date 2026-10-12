<?php

use App\Http\Controllers\Auth\WebAuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\MerchantDeliveryController;
use App\Http\Controllers\Web\MerchantDeliveryCreationController;
use App\Http\Controllers\Web\MerchantAddressController;
use Illuminate\Support\Facades\Route;

Route::get('/language/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);

    session(['locale' => $locale]);

    return redirect()->back(fallback: route('web.dashboard'));
})->middleware('auth')->name('language.switch');

Route::view('/', 'welcome');

Route::middleware('guest')->group(function () {
    Route::get('/login', [
        WebAuthController::class,
        'showLogin'
    ])->name('login');

    Route::post('/login', [
        WebAuthController::class,
        'login'
    ])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [
        WebAuthController::class,
        'logout'
    ])->name('logout');

    Route::get(
        '/dashboard',
        DashboardController::class
    )->name('web.dashboard');

    Route::get(
        '/merchant/deliveries',
        [MerchantDeliveryController::class, 'index']
    )->name('merchant.deliveries.index');

    Route::get(
        '/merchant/deliveries/create',
        [MerchantDeliveryCreationController::class, 'create']
    )->name('merchant.deliveries.create');

    Route::post(
        '/merchant/deliveries',
        [MerchantDeliveryCreationController::class, 'store']
    )->name('merchant.deliveries.store');

    Route::get('/merchant/deliveries/{delivery}', [
        \App\Http\Controllers\Web\MerchantDeliveryController::class,
        'show',
    ])->name('merchant.deliveries.show');


    Route::prefix('merchant/addresses')
        ->name('merchant.addresses.')
        ->controller(MerchantAddressController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{address}/edit', 'edit')
                ->whereNumber('address')->name('edit');
            Route::put('/{address}', 'update')
                ->whereNumber('address')->name('update');
            Route::delete('/{address}', 'destroy')
                ->whereNumber('address')->name('destroy');
        });
});
