<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    protected $bookingService;
    protected $notificationService;

    public function __construct(\App\Services\BookingService $bookingService, NotificationService $notificationService)
    {
        $this->bookingService = $bookingService;
        $this->notificationService = $notificationService;
    }

    /**
     * List all bookings with their payments and vehicle details
     */
    public function index()
    {
        $bookings = Booking::with(['user:id,name', 'vehicle.primaryImage'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('start_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        $summaries = $bookings->map(function ($booking) {
            // Check for any pending payment
            $hasPendingPayment = $booking->payments()->where('status', 'pending')->exists();
            return [
                'id' => $booking->id,
                'status' => $booking->status,
                'start_date' => $booking->start_date,
                'end_date' => $booking->end_date,
                'total_price' => $booking->total_price,
                'pickup_type' => $booking->pickup_type,
                'delivery_location' => $booking->delivery_location,
                'delivery_details' => $booking->delivery_details,
                'created_at' => $booking->created_at,
                'has_pending_payment' => $hasPendingPayment,
                'user' => $booking->user ? [
                    'id' => $booking->user->id,
                    'name' => $booking->user->name,
                ] : null,
                'vehicle' => $booking->vehicle ? [
                    'id' => $booking->vehicle->id,
                    'name' => $booking->vehicle->name,
                    'primary_image_url' => $booking->vehicle->primary_image_url ?? null,
                ] : null,
            ];
        });

        return response()->json(['bookings' => $summaries]);
    }

    /**
     * List bookings ready for vehicle release (status = 'for_release')
     */
    public function forRelease()
    {
        $bookings = Booking::with(['user', 'vehicle', 'payments', 'vehicleRelease'])
            ->where('status', 'for_release')
            ->orderBy('start_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['bookings' => $bookings]);
    }

    /**
     * List bookings ready for vehicle return (status = 'released')
     */
    public function forReturn()
    {
        $bookings = Booking::with(['user', 'vehicle', 'payments', 'vehicleRelease', 'vehicleReturn'])
            ->where('status', 'released')
            ->orderBy('end_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['bookings' => $bookings]);
    }

    /**
     * List completed bookings (status = 'completed') for admin history
     */
    public function completed()
    {
        $bookings = Booking::with(['user', 'vehicle', 'payments', 'vehicleRelease', 'vehicleReturn'])
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['bookings' => $bookings]);
    }

    /**
     * Confirm a payment
     */
    public function confirmPayment($paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $payment->status = 'approved';
        $payment->approved_at = now();
        $payment->save();

        // If this was a deposit payment, update booking status to confirmed
        if ($payment->type === 'deposit') {
            $payment->booking->status = 'confirmed';
            $payment->booking->save();
        }

        // If this was a rental payment, update booking status to ready_for_release
        if ($payment->type === 'rental') {
            $payment->booking->status = 'for_release';
            $payment->booking->save();
        }

        // Notify user about approval
        app(\App\Services\NotificationService::class)->notifyUser(
            $payment->booking->user_id,
            'payment_status_updated',
            $payment,
            [
                'message' => 'Your ' . $payment->type . ' payment has been approved',
                'customer_name' => $payment->booking->user->name,
                'payment_type' => $payment->type,
                'payment_status' => 'approved',
                'payment_method' => $payment->method,
                'booking_id' => $payment->booking->id
            ]
        );

        return response()->json(['message' => 'Payment confirmed', 'payment' => $payment]);
    }

    /**
     * Reject a payment
     */
    public function rejectPayment($paymentId)
    {
        $payment = Payment::findOrFail($paymentId);
        $payment->status = 'rejected';
        $payment->save();

        // Notify user about rejection
        app(\App\Services\NotificationService::class)->notifyUser(
            $payment->booking->user_id,
            'payment_status_updated',
            $payment,
            [
                'message' => 'Your ' . $payment->type . ' payment has been rejected. Please submit a new payment.',
                'customer_name' => $payment->booking->user->name,
                'payment_type' => $payment->type,
                'payment_status' => 'rejected',
                'payment_method' => $payment->method,
                'booking_id' => $payment->booking->id
            ]
        );

        return response()->json(['message' => 'Payment rejected', 'payment' => $payment]);
    }

    /**
     * Log vehicle release details and update booking status
     */
    public function releaseVehicle(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'condition_notes' => 'nullable|string',
            'fuel_level' => 'nullable|string',
            'odometer' => 'nullable|integer',
            'released_at' => 'nullable|date',
            'images' => 'nullable|array',
            'images.*' => 'nullable|string', 
        ]);

        // Only allow release if booking is for_release and not already released
        if ($booking->status !== 'for_release' || $booking->vehicleRelease) {
            return response()->json(['message' => 'Booking not eligible for release or already released'], 422);
        }

        // Prevent release if vehicle is already in use
        $vehicle = $booking->vehicle;
        if ($vehicle->status === 'in_use') {
            return response()->json(['message' => 'Vehicle is already in use and cannot be released for this booking'], 422);
        }

        $release = $booking->vehicleRelease()->create(array_merge($validated, [
            'vehicle_id' => $booking->vehicle_id,
            'released_at' => $validated['released_at'] ?? now(),
        ]));

        $booking->status = 'released';
        $booking->save();

        // Update vehicle status to in_use
        $vehicle->status = 'in_use';
        $vehicle->save();

        // Notify user about vehicle release
        app(\App\Services\NotificationService::class)->notifyUser(
            $booking->user_id,
            'vehicle_released',
            $booking,
            [
                'message' => 'Your vehicle for booking #' . $booking->id . ' has been released and is ready for use.',
                'booking_id' => $booking->id,
                'vehicle_id' => $booking->vehicle_id,
                'start_date' => $booking->start_date,
                'end_date' => $booking->end_date,
                'vehicle' => $vehicle->name ?? ($vehicle->make ?? '') . ' ' . ($vehicle->model ?? '')
            ]
        );

        return response()->json(['message' => 'Vehicle released', 'release' => $release]);
    }

    /**
     * Process vehicle return, log details, update statuses, and calculate fees
     */
    public function returnVehicle(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'returned_at' => 'nullable|date',
            'odometer' => 'nullable|integer',
            'fuel_level' => 'nullable|string',
            'condition_notes' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'nullable|string',
            'late_fee' => 'nullable|numeric',
            'damage_fee' => 'nullable|numeric',
            'cleaning_fee' => 'nullable|numeric',
        ]);

        // Only allow return if booking is released and not already returned
        if ($booking->status !== 'released' || $booking->vehicleReturn) {
            return response()->json(['message' => 'Booking not eligible for return or already returned'], 422);
        }

        // Calculate late fee if not provided
        $lateFee = $validated['late_fee'] ?? 0;
        $scheduledEnd = \Carbon\Carbon::parse($booking->end_date);
        $actualReturn = isset($validated['returned_at']) ? \Carbon\Carbon::parse($validated['returned_at']) : now();
        if ($actualReturn->greaterThan($scheduledEnd)) {
            $hoursLate = $scheduledEnd->diffInHours($actualReturn);
            $lateFee = $lateFee ?: ($hoursLate * 100); // ₱100/hour late fee
        }

        $return = $booking->vehicleReturn()->create(array_merge($validated, [
            'vehicle_id' => $booking->vehicle_id,
            'returned_at' => $validated['returned_at'] ?? now(),
            'late_fee' => $lateFee,
        ]));

        // Update booking and vehicle status
        $booking->status = 'completed';
        $booking->save();
        $vehicle = $booking->vehicle;
        $vehicle->status = 'available'; // Always set to available after return
        $vehicle->save();

        // Make driver available again if assigned
        if ($booking->driver_id) {
            $driver = \App\Models\Driver::find($booking->driver_id);
            if ($driver) {
                $driver->available = true;
                $driver->save();
            }
        }

        return response()->json(['message' => 'Vehicle returned', 'return' => $return]);
    }

    /**
     * Show a specific booking with all details for admin
     */
    public function show(Booking $booking)
    {
        $booking->load(['user', 'vehicle', 'payments', 'vehicleRelease', 'vehicleReturn', 'driver']);
        $data = $booking->toArray();
        // Attach driver info if present
        if ($booking->driver) {
            $data['driver'] = [
                'id' => $booking->driver->id,
                'name' => $booking->driver->name,
                'phone' => $booking->driver->phone ?? null,
            ];
        } else {
            $data['driver'] = null;
        }
        return response()->json(['booking' => $data]);
    }

    /**
     * Get bookings for calendar view
     */
    public function calendar(Request $request)
    {
        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start'
        ]);
        
        $startDate = $validated['start'];
        $endDate = $validated['end'];
        
        $events = $this->bookingService->getCalendarEvents($startDate, $endDate);
        
        return response()->json(['events' => $events]);
    }

    /**
     * Cancel a booking (Admin)
     */
    public function cancel(Request $request, $bookingId)
    {
        $booking = Booking::findOrFail($bookingId);
        
        // Only allow cancellation if not already cancelled or completed
        if (in_array($booking->status, ['cancelled', 'completed'])) {
            return response()->json(['message' => 'Booking cannot be cancelled'], 422);
        }
        
        // Admin cancellation logic: calculate refund based on timing
        $hours = now()->diffInHours($booking->start_date, false);
        $refund = 0;
        if ($hours >= 168) { // 7 days
            $refund = 1.0;
        } elseif ($hours >= 24) {
            $refund = 0.5;
        }
        
        // Refund is based on the vehicle's deposit, not total_price
        $vehicle = $booking->vehicle;
        $deposit = $vehicle ? $vehicle->deposit : 0;
        $refundAmount = $deposit * $refund;
        
        $booking->status = 'cancelled';
        $booking->refund_rate = $refund;
        $booking->refund_amount = $refundAmount;
        $booking->save();
        
        // Send notification to the customer about admin cancellation
        $this->notificationService->create('booking_cancelled_by_admin', $booking, [
            'message' => 'Your booking has been cancelled by admin',
            'vehicle_name' => $booking->vehicle->name ?? 'Unknown Vehicle',
            'start_date' => $booking->start_date,
            'end_date' => $booking->end_date,
            'refund_rate' => $refund,
            'refund_amount' => $refundAmount,
            'booking_id' => $booking->id
        ], $booking->user);
        
        return response()->json([
            'message' => 'Booking cancelled',
            'refund_rate' => $refund,
            'refund_amount' => $refundAmount,
        ]);
    }
}
