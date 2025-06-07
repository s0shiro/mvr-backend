<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\BookingService;
use App\Services\NotificationService;
use App\Services\PaymentService;

class CustomerDashboardController extends Controller
{
    protected $bookingService;
    protected $notificationService;
    protected $paymentService;

    public function __construct(BookingService $bookingService, NotificationService $notificationService, PaymentService $paymentService)
    {
        $this->bookingService = $bookingService;
        $this->notificationService = $notificationService;
        $this->paymentService = $paymentService;
    }

    /**
     * Customer dashboard overview: recent bookings, payment status, notifications
     */
    public function overview(Request $request)
    {
        $user = Auth::user();

        // Get all bookings for stats
        $allBookings = $this->bookingService->getUserBookings($user->id);
        
        // Stats counts
        $stats = [
            'total_bookings' => $allBookings->count(),
            'active_rentals' => $allBookings->whereIn('status', ['confirmed', 'for_release', 'released'])->count(),
            'pending_payments' => $this->paymentService->getPendingPaymentsCount($user->id),
            'unread_notifications' => $this->notificationService->getUnreadCount($user),
        ];

        // Recent bookings (last 5)
        $recentBookings = $allBookings->take(5)->map(function ($booking) {
            return [
                'id' => $booking->id,
                'vehicle_name' => $booking->vehicle->name ?? 'Vehicle',
                'status' => $booking->status,
                'start_date' => $booking->start_date,
                'end_date' => $booking->end_date,
            ];
        });

        // Recent payments (last 5)
        $recentPayments = $this->paymentService->getRecentPayments($user->id, 5);

        // Recent notifications (last 5)
        $recentNotifications = $this->notificationService->getUserNotifications($user, true)
            ->take(5);

        return response()->json([
            'stats' => $stats,
            'bookings' => $recentBookings,
            'payments' => $recentPayments,
            'notifications' => $recentNotifications,
        ]);
    }
}
