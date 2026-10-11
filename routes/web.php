<?php

use App\Http\Controllers\Auth\WebAuthController;
use Illuminate\Support\Facades\Route;

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

    Route::get('/dashboard', function () {
        abort_unless(
            auth()->user()->hasRole([
                'admin',
                'merchant',
                'driver',
            ]),
            403
        );

        return view('dashboard.index');
    })->name('web.dashboard');
});
