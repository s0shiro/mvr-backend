<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class RentalPaymentController extends Controller
{
    /**
     * Get all rental payments with pagination
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        
        $payments = Payment::with(['booking.user', 'booking.vehicle'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($payments);
    }

    /**
     * Get rental revenue summary
     */
    public function revenue()
    {
        $approvedCount = Payment::where('status', 'approved')->count();
        $pendingCount = Payment::where('status', 'pending')->count();
        
        // Calculate revenue using relationships
        $totalRevenue = 0;
        $approvedPayments = Payment::with(['booking.vehicle'])
            ->where('status', 'approved')
            ->get();
            
        foreach ($approvedPayments as $payment) {
            if ($payment->booking && $payment->booking->vehicle) {
                if ($payment->type === 'deposit') {
                    $totalRevenue += $payment->booking->vehicle->deposit ?? 0;
                } else {
                    $totalRevenue += $payment->booking->total_price ?? 0;
                }
            }
        }
        
        return response()->json([
            'total_revenue' => $totalRevenue,
            'approved_count' => $approvedCount,
            'pending_count' => $pendingCount,
        ]);
    }

    /**
     * Get rental payments summary for reports
     */
    public function summary(Request $request)
    {
        $grouping = $request->get('grouping', 'day');
        $from = $request->get('from');
        $to = $request->get('to');

        $query = Payment::select(
            DB::raw("DATE(payments.created_at) as date"),
            DB::raw("SUM(CASE 
                WHEN payments.type = 'deposit' THEN vehicles.deposit 
                ELSE bookings.total_price 
            END) as total_sales"),
            DB::raw("SUM(CASE 
                WHEN payments.type = 'deposit' THEN vehicles.deposit 
                ELSE 0 
            END) as deposit_sales"),
            DB::raw("SUM(CASE 
                WHEN payments.type = 'rental' THEN bookings.total_price 
                ELSE 0 
            END) as rental_sales"),
            DB::raw("COUNT(*) as payment_count")
        )
        ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
        ->join('vehicles', 'bookings.vehicle_id', '=', 'vehicles.id')
        ->where('payments.status', 'approved');

        if ($from) {
            $query->whereDate('payments.created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('payments.created_at', '<=', $to);
        }

        if ($grouping === 'week') {
            $query->select(
                DB::raw("DATE(DATE_SUB(payments.created_at, INTERVAL WEEKDAY(payments.created_at) DAY)) as period_start"),
                DB::raw("DATE(DATE_ADD(DATE_SUB(payments.created_at, INTERVAL WEEKDAY(payments.created_at) DAY), INTERVAL 6 DAY)) as period_end"),
                DB::raw("SUM(CASE 
                    WHEN payments.type = 'deposit' THEN vehicles.deposit 
                    ELSE bookings.total_price 
                END) as total_sales"),
                DB::raw("SUM(CASE 
                    WHEN payments.type = 'deposit' THEN vehicles.deposit 
                    ELSE 0 
                END) as deposit_sales"),
                DB::raw("SUM(CASE 
                    WHEN payments.type = 'rental' THEN bookings.total_price 
                    ELSE 0 
                END) as rental_sales"),
                DB::raw("COUNT(*) as payment_count")
            )
            ->groupBy(DB::raw("YEARWEEK(payments.created_at)"));
        } elseif ($grouping === 'month') {
            $query->select(
                DB::raw("DATE_FORMAT(payments.created_at, '%Y-%m-01') as date"),
                DB::raw("SUM(CASE 
                    WHEN payments.type = 'deposit' THEN vehicles.deposit 
                    ELSE bookings.total_price 
                END) as total_sales"),
                DB::raw("SUM(CASE 
                    WHEN payments.type = 'deposit' THEN vehicles.deposit 
                    ELSE 0 
                END) as deposit_sales"),
                DB::raw("SUM(CASE 
                    WHEN payments.type = 'rental' THEN bookings.total_price 
                    ELSE 0 
                END) as rental_sales"),
                DB::raw("COUNT(*) as payment_count")
            )
            ->groupBy(DB::raw("YEAR(payments.created_at), MONTH(payments.created_at)"));
        } else {
            $query->groupBy(DB::raw("DATE(payments.created_at)"));
        }

        $results = $query->orderBy($grouping === 'week' ? 'period_start' : 'date')->get();

        return response()->json($results);
    }
}
