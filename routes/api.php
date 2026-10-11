<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;
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

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {

        // Public routes
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        // Protected routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
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
});
