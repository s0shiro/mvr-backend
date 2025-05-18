<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VehicleController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:api','role:admin|customer']);

// Auth routes
Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::post('/logout', 'logout')->middleware('auth:api');
    Route::post('/refresh', 'refresh');
    Route::post('/change-password', 'changePassword')->middleware('auth:api');
});

// Vehicle routes
Route::controller(VehicleController::class)->group(function () {
    // Routes accessible to both admin and customer
    Route::middleware(['auth:api', 'role:admin|customer'])->group(function () {
        Route::get('/vehicles', 'index');
        Route::get('/vehicles/{vehicle}', 'show');
    });

    // Routes accessible to admin only
    Route::middleware(['auth:api', 'role:admin'])->group(function () {
        Route::post('/vehicles', 'store');
        Route::put('/vehicles/{vehicle}', 'update');
        Route::delete('/vehicles/{vehicle}', 'destroy');
        Route::patch('/vehicles/{vehicle}/status', 'updateStatus');
    });
});
