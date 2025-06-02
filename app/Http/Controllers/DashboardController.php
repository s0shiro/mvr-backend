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
    // Admin overview endpoint
    public function adminOverview(Request $request)
    {
        // Summary statistics
        $totalVehicles = Vehicle::count();
        $availableVehicles = Vehicle::where('status', 'available')->count();
        $rentedVehicles = Vehicle::where('status', 'rented')->count();
        $maintenanceVehicles = Vehicle::where('status', 'maintenance')->count();

        $totalBookings = Booking::count();
        $activeBookings = Booking::where('status', 'active')->count();
        $completedBookings = Booking::where('status', 'completed')->count();
        $cancelledBookings = Booking::where('status', 'cancelled')->count();

        $totalUsers = User::count();
        $totalCustomers = User::role('customer')->count();
        $totalStaff = User::role('manager')->count();
        $totalAdmins = User::role('admin')->count();

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();

        // Revenue calculations based on approved rental payments, using booking's total_price
        $revenueToday = Payment::where('status', 'approved')
            ->where('type', 'rental')
            ->whereDate('updated_at', $today)
            ->with('booking')
            ->get()
            ->sum(function($payment) {
                return $payment->booking?->total_price ?? 0;
            });
        $revenueWeek = Payment::where('status', 'approved')
            ->where('type', 'rental')
            ->whereBetween('updated_at', [$startOfWeek, Carbon::now()])
            ->with('booking')
            ->get()
            ->sum(function($payment) {
                return $payment->booking?->total_price ?? 0;
            });
        $revenueMonth = Payment::where('status', 'approved')
            ->where('type', 'rental')
            ->whereBetween('updated_at', [$startOfMonth, Carbon::now()])
            ->with('booking')
            ->get()
            ->sum(function($payment) {
                return $payment->booking?->total_price ?? 0;
            });

        // Pending actions
        $pendingBookings = Booking::whereIn('status', ['pending', 'confirmed'])->count();
        $pendingRefunds = Payment::where('type', 'refund')->where('status', 'pending')->count();
        $vehiclesToRelease = Booking::where('status', 'for_release')->count();
        $vehiclesToReturn = Booking::where('status', 'released')->where('end_date', '<', Carbon::now())->count();

        // Recent activity
        $recentBookings = Booking::with(['user', 'vehicle'])->latest()->take(5)->get();
        $recentPayments = Payment::latest()->take(5)->get();
        $recentFeedback = Feedback::with('user')->latest()->take(5)->get();

        // Alerts
        $overdueReturns = Booking::where('status', 'released')->where('end_date', '<', Carbon::now())->count();
        $maintenanceDue = Vehicle::where('status', 'maintenance')->count();

        return response()->json([
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
                'users' => [
                    'total' => $totalUsers,
                    'customers' => $totalCustomers,
                    'manager' => $totalStaff,
                    'admins' => $totalAdmins,
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
        ]);
    }
}
