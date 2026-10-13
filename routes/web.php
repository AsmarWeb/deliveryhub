<?php

use App\Http\Controllers\Web\Admin\AdminDeliveryController;
use App\Http\Controllers\Web\Admin\AdminDeliveryCompletionController;
use App\Http\Controllers\Web\Admin\AdminProofOfDeliveryController;
use App\Http\Controllers\Web\Admin\AdminDashboardController;

use App\Http\Controllers\Auth\WebAuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\MerchantDeliveryController;
use App\Http\Controllers\Web\MerchantDeliveryCreationController;
use App\Http\Controllers\Web\MerchantAddressController;
use App\Http\Controllers\Web\DriverAssignmentController;
use App\Http\Controllers\Web\DriverDeliveryController;
use App\Http\Controllers\Web\DriverProofOfDeliveryController;
use App\Http\Controllers\Web\MerchantProofOfDeliveryController;
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


Route::get(
    '/admin/dashboard',
    AdminDashboardController::class
)
    ->middleware('auth')
    ->name('admin.dashboard');


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


    Route::prefix('driver/assignments')
        ->name('driver.assignments.')
        ->controller(DriverAssignmentController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');

            Route::get('/{assignment}', 'show')
                ->whereNumber('assignment')
                ->name('show');

            Route::post('/{assignment}/accept', 'accept')
                ->whereNumber('assignment')
                ->name('accept');

            Route::post('/{assignment}/reject', 'reject')
                ->whereNumber('assignment')
                ->name('reject');
        });

    Route::prefix('driver/assignments')
        ->name('driver.assignments.')
        ->controller(DriverDeliveryController::class)
        ->group(function () {
            Route::post('/{assignment}/pickup', 'pickup')
                ->whereNumber('assignment')
                ->name('pickup');

            Route::post('/{assignment}/start-transit', 'startTransit')
                ->whereNumber('assignment')
                ->name('start-transit');
        });

    Route::prefix('driver/assignments')
        ->name('driver.assignments.')
        ->controller(DriverProofOfDeliveryController::class)
        ->group(function () {
            Route::get('/{assignment}/proof', 'create')
                ->whereNumber('assignment')
                ->name('proof.create');

            Route::post('/{assignment}/proof', 'store')
                ->whereNumber('assignment')
                ->name('proof.store');
        });

    Route::get(
        '/merchant/deliveries/{delivery}/proof/{type}',
        [MerchantProofOfDeliveryController::class, 'file']
    )
        ->whereNumber('delivery')
        ->where('type', 'photo|signature')
        ->name('merchant.deliveries.proof.file');


    Route::prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/deliveries', [
                AdminDeliveryController::class,
                'index',
            ])->name('deliveries.index');

            Route::get('/deliveries/{delivery}', [
                AdminDeliveryController::class,
                'show',
            ])->whereNumber('delivery')
                ->name('deliveries.show');

            Route::post('/deliveries/{delivery}/complete', [
                AdminDeliveryCompletionController::class,
                '__invoke',
            ])->whereNumber('delivery')
                ->name('deliveries.complete');
        });


    Route::get(
        '/admin/deliveries/{delivery}/proof/{type}',
        [AdminProofOfDeliveryController::class, 'file']
    )
        ->whereNumber('delivery')
        ->where('type', 'photo|signature')
        ->name('admin.deliveries.proof.file');
});
