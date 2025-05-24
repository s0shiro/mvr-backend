<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;

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
    Route::middleware(['auth:api', 'role:admin|customer|manager'])->group(function () {
        Route::get('/vehicles', 'index');
        Route::get('/vehicles/{vehicle}', 'show');
        Route::get('/vehicles/{vehicle}/images/{imageId}', 'getImage');
    });

    // Routes accessible to admin only
    Route::middleware(['auth:api', 'role:admin'])->group(function () {
        Route::post('/vehicles', 'store');
        Route::put('/vehicles/{vehicle}', 'update');
        Route::delete('/vehicles/{vehicle}', 'destroy');
        Route::patch('/vehicles/{vehicle}/status', 'updateStatus');
        
        // Image management routes
        Route::post('/vehicles/{vehicle}/images', 'uploadImages');
        Route::delete('/vehicles/{vehicle}/images/{imageId}', 'deleteImage');
        Route::put('/vehicles/{vehicle}/images/{imageId}/primary', 'setPrimaryImage');
        Route::put('/vehicles/{vehicle}/images/reorder', 'reorderImages');
    });
});

// Booking routes
Route::controller(BookingController::class)->middleware(['auth:api', 'role:admin|customer|manager'])->group(function () {
    Route::post('/bookings/summary', 'summary'); // Get booking summary (availability, price)
    Route::post('/bookings', 'store'); // Create booking
    Route::put('/bookings/{booking}', 'update'); // Modify booking (FR006)
    Route::post('/bookings/{booking}/cancel', 'cancel'); // Cancel booking (FR007)
    Route::get('/mybookings', 'myBookings'); // List bookings for the authenticated user
});

// Payment routes
Route::get('/payment-methods', [PaymentController::class, 'methods']);
Route::controller(PaymentController::class)->middleware(['auth:api', 'role:admin|customer|manager'])->group(function () {
    Route::post('/bookings/{booking}/payment', 'store'); // Customer submits payment
    Route::get('/bookings/{booking}/payment', 'show'); // View payment info
    Route::patch('/bookings/{booking}/payment/status', 'updateStatus'); // Admin updates payment status
});

// Admin routes
Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/bookings', [App\Http\Controllers\Admin\BookingController::class, 'index']);
    Route::post('/bookings/payments/{paymentId}/confirm', [App\Http\Controllers\Admin\BookingController::class, 'confirmPayment']);
    Route::post('/bookings/payments/{paymentId}/reject', [App\Http\Controllers\Admin\BookingController::class, 'rejectPayment']);
    Route::post('/bookings/{booking}/release', [App\Http\Controllers\Admin\BookingController::class, 'releaseVehicle']); // Confirm vehicle release
    Route::get('/bookings/for-release', [App\Http\Controllers\Admin\BookingController::class, 'forRelease']);
});
