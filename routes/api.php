<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\RentalPaymentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:api','role:admin|customer|manager']);

// Auth routes
Route::controller(AuthController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::post('/logout', 'logout')->middleware('auth:api');
    Route::post('/refresh', 'refresh');
    Route::post('/change-password', 'changePassword')->middleware('auth:api');
    Route::post('/verify-code', 'verifyCode'); // <-- Add this line
    Route::post('/resend-verification-code', 'resendVerificationCode'); // Resend verification code
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

// Notification routes
Route::controller(NotificationController::class)->middleware(['auth:api'])->group(function () {
    Route::get('/notifications', 'index');
    Route::post('/notifications/{id}/read', 'markAsRead');
    Route::post('/notifications/mark-all-read', 'markAllAsRead');
    Route::get('/notifications/unread-count', 'unreadCount'); // Unread notification count
});

// Booking routes
Route::controller(BookingController::class)->middleware(['auth:api', 'role:admin|customer|manager'])->group(function () {
    Route::post('/bookings/summary', 'summary'); // Get booking summary (availability, price)
    Route::post('/bookings', 'store'); // Create booking
    Route::put('/bookings/{booking}', 'update'); // Modify booking (FR006)
    Route::post('/bookings/{booking}/cancel', 'cancel'); // Cancel booking (FR007)
    Route::post('/bookings/{booking}/submit-return', 'submitReturn'); // Customer submits return
    Route::get('/mybookings', 'myBookings'); // List bookings for the authenticated user
    Route::get('/mybookings/completed', 'myCompletedBookings'); // List completed bookings for the authenticated user
    Route::get('/bookings/{booking}/summary-details', 'summaryDetails'); // Get detailed booking summary
    Route::get('/bookings/{booking}/completed-details', 'completedBookingDetails'); // Get comprehensive completed booking summary
});

// Payment routes
Route::get('/payment-methods', [PaymentController::class, 'methods']);
Route::controller(PaymentController::class)->middleware(['auth:api', 'role:admin|customer|manager'])->group(function () {
    Route::post('/bookings/{booking}/payment', 'store'); // Customer submits payment
    Route::get('/bookings/{booking}/payment', 'show'); // View payment info
    Route::patch('/bookings/{booking}/payment/status', 'updateStatus'); // Admin updates payment status
});

// Feedback routes
Route::controller(FeedbackController::class)
    ->middleware(['auth:api', 'role:admin|customer|manager'])
    ->group(function () {
        Route::post('/feedback', 'store'); // Submit feedback
        Route::get('/feedback', 'index'); // List all feedback (admin/manager)
        Route::get('/feedback/booking/{bookingId}', 'byBooking'); // List feedback for a booking
        Route::get('/feedback/user/{userId}', 'byUser'); // List feedback by user
        Route::get('/feedback/vehicle/{vehicleId}', 'byVehicle'); // List feedback for a vehicle
    });

// Admin routes
Route::middleware(['auth:api', 'role:admin|manager'])->prefix('admin')->group(function () {
    Route::get('/bookings/calendar', [App\Http\Controllers\Admin\BookingController::class, 'calendar']); // Get calendar events
    Route::get('/bookings', [App\Http\Controllers\Admin\BookingController::class, 'index']);
    Route::post('/bookings/payments/{paymentId}/confirm', [App\Http\Controllers\Admin\BookingController::class, 'confirmPayment']);
    Route::post('/bookings/payments/{paymentId}/reject', [App\Http\Controllers\Admin\BookingController::class, 'rejectPayment']);
    Route::post('/bookings/{booking}/cancel', [App\Http\Controllers\Admin\BookingController::class, 'cancel']); // Admin cancel booking
    Route::post('/bookings/{booking}/process-refund', [App\Http\Controllers\Admin\BookingController::class, 'processRefund']); // Process customer refund
    Route::post('/bookings/{booking}/release', [App\Http\Controllers\Admin\BookingController::class, 'releaseVehicle']); // Confirm vehicle release
    Route::get('/bookings/for-release', [App\Http\Controllers\Admin\BookingController::class, 'forRelease']);
    Route::get('/bookings/for-return', [App\Http\Controllers\Admin\BookingController::class, 'forReturn']);
    Route::post('/bookings/{booking}/return', [App\Http\Controllers\Admin\BookingController::class, 'returnVehicle']);
    Route::post('/bookings/{booking}/refund-deposit', [App\Http\Controllers\Admin\BookingController::class, 'processDepositRefund']);
    Route::get('/bookings/completed', [App\Http\Controllers\Admin\BookingController::class, 'completed']); // Completed bookings history
    Route::get('/bookings/canceled', [App\Http\Controllers\Admin\BookingController::class, 'canceled']); // Canceled bookings history
    Route::get('/bookings/{booking}', [App\Http\Controllers\Admin\BookingController::class, 'show']); // Get a specific booking by ID

    // User management routes
    Route::get('/users', [App\Http\Controllers\Admin\UserController::class, 'index']);
    Route::get('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'show']);
    Route::post('/users', [App\Http\Controllers\Admin\UserController::class, 'store']);
    Route::put('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'update']);
    Route::delete('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'destroy']);

    // Payment method management
    Route::get('/payment-methods', [\App\Http\Controllers\PaymentMethodController::class, 'index']);
    Route::post('/payment-methods', [\App\Http\Controllers\PaymentMethodController::class, 'store']);
    Route::put('/payment-methods/{id}', [\App\Http\Controllers\PaymentMethodController::class, 'update']);
    Route::delete('/payment-methods/{id}', [\App\Http\Controllers\PaymentMethodController::class, 'destroy']);
});

// Business routes (admin only)
Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::get('/businesses', [App\Http\Controllers\BusinessController::class, 'index']);
    Route::post('/businesses', [App\Http\Controllers\BusinessController::class, 'store']);
    Route::get('/businesses/{id}', [App\Http\Controllers\BusinessController::class, 'show']);
    Route::put('/businesses/{id}', [App\Http\Controllers\BusinessController::class, 'update']);
    Route::delete('/businesses/{id}', [App\Http\Controllers\BusinessController::class, 'destroy']);

    // Business sales/notes routes (admin only)
    Route::get('/businesses/{business}/sales/summary', [App\Http\Controllers\BusinessSaleController::class, 'summary']);
    Route::get('/businesses/{business}/sales', [App\Http\Controllers\BusinessSaleController::class, 'index']);
    Route::post('/businesses/{business}/sales', [App\Http\Controllers\BusinessSaleController::class, 'store']);
    Route::get('/businesses/{business}/sales/{id}', [App\Http\Controllers\BusinessSaleController::class, 'show']);
    Route::put('/businesses/{business}/sales/{id}', [App\Http\Controllers\BusinessSaleController::class, 'update']);
    Route::delete('/businesses/{business}/sales/{id}', [App\Http\Controllers\BusinessSaleController::class, 'destroy']);
});

// Rental payment routes (admin only)
Route::middleware(['auth:api', 'role:admin|manager'])->prefix('rental')->group(function () {
    Route::get('/payments', [RentalPaymentController::class, 'index']);
    Route::get('/revenue', [RentalPaymentController::class, 'revenue']);
    Route::get('/payments/summary', [RentalPaymentController::class, 'summary']);
});

// Driver routes
Route::controller(App\Http\Controllers\DriverController::class)
    ->middleware(['auth:api', 'role:admin|manager'])
    ->prefix('drivers')
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::post('/', 'store');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

// Admin dashboard overview
Route::middleware(['auth:api', 'role:admin|manager'])->get('/admin/overview', [\App\Http\Controllers\DashboardController::class, 'adminOverview']);

// Customer dashboard overview
Route::middleware(['auth:api', 'role:customer'])->get('/customer/overview', [CustomerDashboardController::class, 'overview']);
