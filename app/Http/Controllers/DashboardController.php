<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Feedback;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // Dashboard overview endpoint for both admin and manager
    public function adminOverview(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('admin');

        // Summary statistics
        $totalVehicles = Vehicle::count();
        $availableVehicles = Vehicle::where('status', 'available')->count();
        $rentedVehicles = Vehicle::where('status', 'in_use')->count();
        $maintenanceVehicles = Vehicle::where('status', 'maintenance')->count();

        $totalBookings = Booking::count();
        $activeBookings = Booking::where('status', 'released')->count();
        $completedBookings = Booking::where('status', 'completed')->count();
        $cancelledBookings = Booking::where('status', 'cancelled')->count();

        $totalUsers = User::count();
        $totalCustomers = User::role('customer')->count();
        $totalStaff = User::role('manager')->count();
        $totalAdmins = User::role('admin')->count();

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();

        // Revenue calculations optimized using joins instead of eager loading
        $revenueToday = Payment::select('payments.*', 'bookings.total_price')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->where('payments.status', 'approved')
            ->where('payments.type', 'rental')
            ->whereDate('payments.updated_at', $today)
            ->sum('bookings.total_price');

        $revenueWeek = Payment::select('payments.*', 'bookings.total_price')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->where('payments.status', 'approved')
            ->where('payments.type', 'rental')
            ->whereBetween('payments.updated_at', [$startOfWeek, Carbon::now()])
            ->sum('bookings.total_price');

        $revenueMonth = Payment::select('payments.*', 'bookings.total_price')
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->where('payments.status', 'approved')
            ->where('payments.type', 'rental')
            ->whereBetween('payments.updated_at', [$startOfMonth, Carbon::now()])
            ->sum('bookings.total_price');

        // Pending actions
        $pendingBookings = Booking::whereIn('status', ['pending', 'confirmed'])->count();
        $pendingRefunds = Payment::where('type', 'refund')->where('status', 'pending')->count();
        $vehiclesToRelease = Booking::where('status', 'for_release')->count();
        $vehiclesToReturn = Booking::where('status', 'released')->where('end_date', '<', Carbon::now())->count();

        // Recent activity with selective fields
        $recentBookings = Booking::select(['id', 'user_id', 'status', 'created_at', 'start_date', 'end_date', 'total_price', 'notes', 'driver_requested', 'driver_id', 'pickup_type', 'delivery_location', 'delivery_details', 'delivery_fee', 'days', 'refund_rate', 'refund_amount'])
            ->with([
                'user:id,name'
            ])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'status' => $booking->status,
                    'start_date' => $booking->start_date,
                    'end_date' => $booking->end_date,
                    'driver_requested' => $booking->driver_requested,
                    'pickup_type' => $booking->pickup_type,
                    'delivery_fee' => $booking->delivery_fee,
                    'days' => $booking->days,
                    'user' => $booking->user ? [
                        'id' => $booking->user->id,
                        'name' => $booking->user->name,
                        'role' => $booking->user->role ?? null,
                    ] : null
                ];
            });

        $recentPayments = Payment::select(['id', 'booking_id', 'type', 'status', 'method', 'created_at'])
            ->latest()
            ->take(5)
            ->get();

        $recentFeedback = Feedback::select(['id', 'user_id', 'comment', 'created_at'])
            ->with('user:id,name')
            ->latest()
            ->take(5)
            ->get();

        // Alerts
        $overdueReturns = Booking::where('status', 'released')->where('end_date', '<', Carbon::now())->count();
        $maintenanceDue = Vehicle::where('status', 'maintenance')->count();

        $response = [
            'summary' => [
                'vehicles' => [
                    'total' => $totalVehicles,
                    'available' => $availableVehicles,
                    'rented' => $rentedVehicles,
                    'maintenance' => $maintenanceVehicles,
                ],
                'bookings' => [
                    'total' => $totalBookings,
                    'active' => $activeBookings,
                    'completed' => $completedBookings,
                    'cancelled' => $cancelledBookings,
                ],
                'revenue' => [
                    'today' => $revenueToday,
                    'week' => $revenueWeek,
                    'month' => $revenueMonth,
                ],
                'pending' => [
                    'bookings' => $pendingBookings,
                    'refunds' => $pendingRefunds,
                    'vehicles_to_release' => $vehiclesToRelease,
                    'vehicles_to_return' => $vehiclesToReturn,
                ],
            ],
            'recent' => [
                'bookings' => $recentBookings,
                'payments' => $recentPayments,
                'feedback' => $recentFeedback,
            ],
            'alerts' => [
                'overdue_returns' => $overdueReturns,
                'maintenance_due' => $maintenanceDue,
            ],
        ];

        // Only include users data for admin role
        if ($isAdmin) {
            $response['summary']['users'] = [
                'total' => $totalUsers,
                'customers' => $totalCustomers,
                'manager' => $totalStaff,
                'admins' => $totalAdmins,
            ];
        }

        return response()->json($response);
    }
}
