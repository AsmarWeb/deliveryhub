<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\StripeBillingController;
use App\Http\Controllers\Api\V1\MerchantAddressController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\DeliveryTrackingController;
use App\Http\Controllers\Api\V1\ProofOfDeliveryViewController;
use App\Http\Controllers\Api\V1\ProofOfDeliveryController;
use App\Http\Controllers\Api\V1\DeliveryCompletionController;
use App\Http\Controllers\Api\V1\DriverDeliveryController;
use App\Http\Controllers\Api\V1\DriverAssignmentController;
use App\Http\Controllers\Api\V1\DeliveryAssignmentController;
use App\Http\Controllers\Api\V1\DeliveryTransitionController;
use App\Http\Controllers\Api\V1\DeliveryPricingController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\DriverProfileController;
use App\Http\Controllers\Api\V1\DriverLocationController;
use App\Http\Controllers\Api\V1\MerchantDeliveryPriceApprovalController;
use App\Http\Controllers\Api\V1\Auth\SelfRegistrationController;
use App\Http\Controllers\Api\V1\Auth\ApplicationStatusController;

Route::prefix('v1')->group(function () {
    Route::post('/driver/dispatch-presence', \App\Http\Controllers\Api\V1\DispatchPresenceController::class)
        ->middleware(['auth:sanctum', 'account.active', 'throttle:10,1']);
    Route::get('/merchant/available-drivers', \App\Http\Controllers\Api\V1\AvailableDriversController::class)
        ->middleware(['auth:sanctum', 'account.active', 'throttle:60,1']);

    Route::middleware(['auth:sanctum', 'account.active', 'throttle:60,1'])->group(function () {
        Route::get('/deliveries/{delivery}/cod', [\App\Http\Controllers\Api\V1\DeliveryCodController::class, 'show'])->whereNumber('delivery');
        Route::post('/deliveries/{delivery}/cod/collect', [\App\Http\Controllers\Api\V1\DeliveryCodController::class, 'collect'])->whereNumber('delivery');
        Route::post('/deliveries/{delivery}/cod/receive', [\App\Http\Controllers\Api\V1\DeliveryCodController::class, 'receive'])->whereNumber('delivery');
    });

    Route::post('/deliveries/{delivery}/cancel', \App\Http\Controllers\Api\V1\MerchantDeliveryCancellationController::class)
        ->whereNumber('delivery')->middleware(['auth:sanctum', 'account.active', 'throttle:10,1']);

    Route::get('/merchant/finance', \App\Http\Controllers\Api\V1\MerchantFinanceController::class)
        ->middleware(['auth:sanctum', 'account.active', 'throttle:60,1']);

    Route::middleware([
        'auth:sanctum',
        'account.active',
    ])->prefix('merchant/billing')->group(function () {
        Route::post('/checkout', [
            StripeBillingController::class,
            'checkout',
        ])->middleware('throttle:billing-write')->name('merchant.billing.checkout');

        Route::post('/portal', [
            StripeBillingController::class,
            'portal',
        ])->middleware('throttle:billing-write')->name('merchant.billing.portal');


        Route::get('/plans', [
            StripeBillingController::class,
            'plans',
        ])->middleware('throttle:billing-read')->name('merchant.billing.plans');

        Route::get('/current', [
            StripeBillingController::class,
            'current',
        ])->middleware('throttle:billing-read')->name('merchant.billing.current');


    });


    Route::middleware([
        'auth:sanctum',
        'account.active',
    ])
        ->get('/dashboard', DashboardController::class)
        ->name('dashboard');

    Route::prefix('auth')->group(function () {

        // Public routes
        Route::post('/login', [AuthController::class, 'login']);

        // Protected routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::get('/account', [\App\Http\Controllers\Api\V1\Auth\AccountProfileController::class, 'show']);
            Route::patch('/account', [\App\Http\Controllers\Api\V1\Auth\AccountProfileController::class, 'update'])->middleware('throttle:10,1');
        });

        Route::post('/register/merchant', [SelfRegistrationController::class, 'merchant'])->middleware('throttle:5,1');
        Route::post('/register/driver', [SelfRegistrationController::class, 'driver'])->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->get(
            '/application-status',
            ApplicationStatusController::class
        );
    });


    Route::middleware('auth:sanctum')
        ->prefix('deliveries')
        ->group(function () {
            Route::get('/', [DeliveryController::class, 'index']);
            Route::post('/', [DeliveryController::class, 'store']);
            Route::get('/{delivery}', [DeliveryController::class, 'show']);
            Route::post(
                '/{delivery}/transition',
                DeliveryTransitionController::class
            );
            Route::post(
                '/{delivery}/price',
                DeliveryPricingController::class
            );
            Route::post(
                '/{delivery}/approve-price',
                MerchantDeliveryPriceApprovalController::class
            );
            Route::post(
                '{delivery}/assign',
                [DeliveryAssignmentController::class, 'store']
            )->name('deliveries.assign');
            Route::post(
                '/{delivery}/complete',
                DeliveryCompletionController::class
            )->name('deliveries.complete');


            Route::get(
                '/{delivery}/proof',
                [ProofOfDeliveryViewController::class, 'show']
            )->name('deliveries.proof.show');

            Route::get(
                '/{delivery}/proof/{type}',
                [ProofOfDeliveryViewController::class, 'file']
            )
                ->where('type', 'photo|signature')
                ->name('deliveries.proof.file');
            Route::get(
                '/{delivery}/tracking',
                DeliveryTrackingController::class
            )->name('deliveries.tracking');
        });
    Route::middleware('auth:sanctum')
        ->prefix('driver')
        ->group(function () {
            Route::get(
                '/assignments',
                [DriverAssignmentController::class, 'index']
            );

            Route::post(
                '/assignments/{assignment}/accept',
                [DriverAssignmentController::class, 'accept']
            );

            Route::post(
                '/assignments/{assignment}/reject',
                [DriverAssignmentController::class, 'reject']
            );

            Route::get('/profile', [
                DriverProfileController::class,
                'show',
            ])->name('driver.profile.show');

            Route::patch('/availability', [
                DriverProfileController::class,
                'updateAvailability',
            ])->name('driver.availability.update');

            Route::patch('/location', [
                DriverLocationController::class,
                'update',
            ])->middleware('throttle:30,1')->name('driver.location.update');
        });

    Route::middleware('auth:sanctum')
        ->prefix('driver/deliveries')
        ->group(function () {
            Route::post(
                '/{delivery}/pickup',
                [DriverDeliveryController::class, 'pickup']
            );

            Route::post(
                '/{delivery}/start-transit',
                [DriverDeliveryController::class, 'startTransit']
            );
        });

    Route::middleware('auth:sanctum')
        ->post(
            'driver/deliveries/{delivery}/proof',
            [ProofOfDeliveryController::class, 'store']
        )
        ->name('driver.deliveries.proof');

    Route::middleware('auth:sanctum')
        ->prefix('notifications')
        ->group(function () {
            Route::get('/', [
                NotificationController::class,
                'index'
            ]);

            Route::get('/unread-count', [
                NotificationController::class,
                'unreadCount'
            ]);

            Route::patch('/{id}/read', [
                NotificationController::class,
                'markAsRead'
            ]);

            Route::post('/read-all', [
                NotificationController::class,
                'markAllAsRead'
            ]);
        });

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/merchant/addresses', [
            MerchantAddressController::class,
            'index',
        ]);

        Route::post('/merchant/addresses', [
            MerchantAddressController::class,
            'store',
        ]);
    });
});


// DELIVERYHUB MERCHANT AUTOMATION ROUTES
Route::middleware([
    'auth:sanctum',
    'account.active',
    'throttle:30,1',
])->prefix('v1/merchant/automations')->group(function () {
    Route::get('/', [
        \App\Http\Controllers\Api\V1\RecurringDeliveryController::class,
        'index',
    ])->name('merchant.automations.index');

    Route::post('/', [
        \App\Http\Controllers\Api\V1\RecurringDeliveryController::class,
        'store',
    ])->name('merchant.automations.store');

    Route::patch('/{recurringDelivery}/status', [
        \App\Http\Controllers\Api\V1\RecurringDeliveryController::class,
        'updateStatus',
    ])->name('merchant.automations.status');
});
