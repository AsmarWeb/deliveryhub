<?php

use App\Http\Controllers\Web\Admin\AdminDeliveryController;
use App\Http\Controllers\Web\Admin\AdminDeliveryCompletionController;
use App\Http\Controllers\Web\Admin\AdminProofOfDeliveryController;
use App\Http\Controllers\Web\Admin\AdminDashboardController;
use App\Http\Controllers\Web\Admin\AdminDriverController;
use App\Http\Controllers\Web\Admin\AdminMerchantController;
use App\Http\Controllers\Web\Admin\AdminDeliveryDispatchController;
use App\Http\Controllers\Web\Admin\MerchantCrmController;

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


use App\Http\Controllers\Web\HomeController;

Route::get('/language/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);

    session(['locale' => $locale]);

    return redirect()->back(fallback: route('web.dashboard'));
})->middleware('auth')->name('language.switch');

Route::get('/', [HomeController::class, 'index'])->name('home');

// Add these once:
Route::get('/sitemap.xml', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        . '<url><loc>' . e(url('/')) . '</loc></url>'
        . '<url><loc>' . e(url('/?lang=ar')) . '</loc></url>'
        . '</urlset>';
    return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/robots.txt', function () {
    return response("User-agent: *\nAllow: /\nSitemap: " . url('/sitemap.xml') . "\n", 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/merchant-crm', [MerchantCrmController::class, 'index'])->name('merchant-crm.index');
    Route::get('/merchant-crm/{lead}', [MerchantCrmController::class, 'show'])->name('merchant-crm.show');
    Route::patch('/merchant-crm/{lead}', [MerchantCrmController::class, 'update'])->name('merchant-crm.update');
    Route::post('/merchant-crm/{lead}/notes', [MerchantCrmController::class, 'note'])->name('merchant-crm.note');
    Route::post('/merchant-crm/{lead}/analyze', [MerchantCrmController::class, 'analyze'])->middleware('throttle:10,1')->name('merchant-crm.analyze');
});

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

            Route::get('/drivers', [
                AdminDriverController::class,
                'index',
            ])->name('drivers.index');

            Route::patch('/drivers/{driver}/status', [
                AdminDriverController::class,
                'updateStatus',
            ])->whereNumber('driver')
                ->name('drivers.status');

            Route::get('/merchants', [
                AdminMerchantController::class,
                'index',
            ])->name('merchants.index');

            Route::get('/merchants/{merchant}', [
                AdminMerchantController::class,
                'show',
            ])->whereNumber('merchant')
                ->name('merchants.show');

            Route::patch('/merchants/{merchant}/status', [
                AdminMerchantController::class,
                'updateStatus',
            ])->whereNumber('merchant')
                ->name('merchants.status');


            Route::post('/deliveries/{delivery}/price', [
                AdminDeliveryDispatchController::class,
                'price',
            ])->whereNumber('delivery')
                ->name('deliveries.price');

            Route::post('/deliveries/{delivery}/assign', [
                AdminDeliveryDispatchController::class,
                'assign',
            ])->whereNumber('delivery')
                ->name('deliveries.assign');

            Route::get(
                '/drivers/{driver}/location',
                [AdminDriverController::class, 'location']
            )->name('drivers.location');

            Route::get('/drivers/map', [
                AdminDriverController::class,
                'map',
            ])->name('drivers.map');

            Route::get('/drivers/locations', [
                AdminDriverController::class,
                'locations',
            ])->name('drivers.locations');
        });


    Route::get(
        '/admin/deliveries/{delivery}/proof/{type}',
        [AdminProofOfDeliveryController::class, 'file']
    )
        ->whereNumber('delivery')
        ->where('type', 'photo|signature')
        ->name('admin.deliveries.proof.file');
});


// Public merchant registration
Route::post('/merchant-leads', [\App\Http\Controllers\Web\MerchantLeadController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('merchant-leads.store');
