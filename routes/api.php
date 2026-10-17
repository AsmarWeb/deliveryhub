<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;
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
