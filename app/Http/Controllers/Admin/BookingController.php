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
                    'model' => $booking->vehicle->model,
                    'brand' => $booking->vehicle->brand,
                    'year' => $booking->vehicle->year, 
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
     * List bookings ready for vehicle return (status = 'released' or 'pending_return')
     */
    public function forReturn()
    {
        $bookings = Booking::with(['user', 'vehicle', 'payments', 'vehicleRelease', 'vehicleReturn'])
            ->whereIn('status', ['released', 'pending_return'])
            ->orderBy('end_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['bookings' => $bookings]);
    }

    /**
     * List completed bookings (status = 'completed') for admin history
     */
    public function completed(Request $request)
    {
        $validated = $request->validate([
            'sort_by' => 'nullable|in:created_at,updated_at,start_date,end_date,total_price',
            'sort_order' => 'nullable|in:asc,desc'
        ]);

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        $bookings = Booking::with(['user', 'vehicle', 'payments', 'vehicleRelease', 'vehicleReturn'])
            ->where('status', 'completed')
            ->orderBy($sortBy, $sortOrder)
            ->get();

        return response()->json(['bookings' => $bookings]);
    }

    /**
     * List canceled bookings (status = 'cancelled') for admin history
     */
    public function canceled(Request $request)
    {
        $validated = $request->validate([
            'sort_by' => 'nullable|in:created_at,cancelled_at,start_date,end_date,total_price',
            'sort_order' => 'nullable|in:asc,desc'
        ]);

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';

        // Handle cancelled_at sorting by using updated_at as fallback
        $orderColumn = $sortBy === 'cancelled_at' ? 'updated_at' : $sortBy;

        $bookings = Booking::with(['user', 'vehicle', 'payments'])
            ->where('status', 'cancelled')
            ->orderBy($orderColumn, $sortOrder)
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
            // Deposit refund fields
            'deposit_status' => 'nullable|in:pending,refunded,withheld',
            'deposit_refund_amount' => 'nullable|numeric|min:0',
            'deposit_refund_notes' => 'nullable|string',
            'deposit_refund_proof' => 'nullable|array',
            'deposit_refund_proof.*' => 'nullable|string',
            'refund_method' => 'nullable|string',
        ]);

        // Allow return processing if booking is released (admin uploads) or pending_return (customer submitted)
        if (!in_array($booking->status, ['released', 'pending_return'])) {
            return response()->json(['message' => 'Booking not eligible for return processing'], 422);
        }

        $existingReturn = $booking->vehicleReturn;

        // Calculate late fee if not provided
        $lateFee = $validated['late_fee'] ?? 0;
        $scheduledEnd = \Carbon\Carbon::parse($booking->end_date);
        $actualReturn = isset($validated['returned_at']) ? \Carbon\Carbon::parse($validated['returned_at']) : now();
        if ($actualReturn->greaterThan($scheduledEnd)) {
            $hoursLate = $scheduledEnd->diffInHours($actualReturn);
            $lateFee = $lateFee ?: ($hoursLate * 100); // ₱100/hour late fee
        }

        // Calculate deposit refund amount if not provided
        $depositAmount = $booking->vehicle->deposit ?? 0;
        $totalFees = $lateFee + ($validated['damage_fee'] ?? 0) + ($validated['cleaning_fee'] ?? 0);
        $defaultRefundAmount = max(0, $depositAmount - $totalFees);
        
        $depositData = [];
        if (isset($validated['deposit_status'])) {
            $depositData['deposit_status'] = $validated['deposit_status'];
            $depositData['deposit_refund_amount'] = $validated['deposit_refund_amount'] ?? $defaultRefundAmount;
            $depositData['deposit_refund_notes'] = $validated['deposit_refund_notes'];
            $depositData['deposit_refund_proof'] = $validated['deposit_refund_proof'];
            $depositData['refund_method'] = $validated['refund_method'];
            
            if ($validated['deposit_status'] === 'refunded') {
                $depositData['deposit_refunded_at'] = now();
            }
        }

        if ($existingReturn) {
            // Update existing return (from customer submission) with admin assessment
            $existingReturn->update(array_merge($validated, $depositData, [
                'returned_at' => $validated['returned_at'] ?? $existingReturn->returned_at,
                'late_fee' => $lateFee,
                'status' => 'completed',
                'admin_processed_at' => now(),
            ]));
            $return = $existingReturn;
        } else {
            // Create new return (admin direct processing)
            $return = $booking->vehicleReturn()->create(array_merge($validated, $depositData, [
                'vehicle_id' => $booking->vehicle_id,
                'returned_at' => $validated['returned_at'] ?? now(),
                'late_fee' => $lateFee,
                'status' => 'completed',
                'admin_processed_at' => now(),
                'deposit_status' => $validated['deposit_status'] ?? 'pending',
            ]));
        }

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

        // Notify customer about return completion
        $notificationData = [
            'message' => 'Your vehicle return has been processed',
            'vehicle_name' => $booking->vehicle->name ?? 'Vehicle',
            'booking_id' => $booking->id,
            'late_fee' => $lateFee,
            'damage_fee' => $validated['damage_fee'] ?? 0,
            'cleaning_fee' => $validated['cleaning_fee'] ?? 0,
            'total_additional_fees' => $lateFee + ($validated['damage_fee'] ?? 0) + ($validated['cleaning_fee'] ?? 0)
        ];

        // Add deposit refund information to notification
        if (isset($validated['deposit_status'])) {
            $notificationData['deposit_status'] = $validated['deposit_status'];
            $notificationData['deposit_refund_amount'] = $depositData['deposit_refund_amount'];
            $notificationData['refund_method'] = $validated['refund_method'];
        }

        app(\App\Services\NotificationService::class)->notifyUser(
            $booking->user_id,
            'vehicle_return_completed',
            $booking,
            $notificationData
        );

        return response()->json(['message' => 'Vehicle return processed', 'return' => $return]);
    }

    /**
     * Process deposit refund separately from vehicle return
     */
    public function processDepositRefund(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'deposit_status' => 'required|in:refunded,withheld',
            'deposit_refund_amount' => 'nullable|numeric|min:0',
            'deposit_refund_notes' => 'nullable|string',
            'deposit_refund_proof' => 'nullable|array',
            'deposit_refund_proof.*' => 'nullable|string',
            'refund_method' => 'nullable|string',
        ]);

        $vehicleReturn = $booking->vehicleReturn;
        if (!$vehicleReturn) {
            return response()->json(['message' => 'Vehicle return not found'], 404);
        }

        if ($vehicleReturn->deposit_status === 'refunded') {
            return response()->json(['message' => 'Deposit already refunded'], 422);
        }

        // Calculate default refund amount if not provided
        $depositAmount = $booking->vehicle->deposit ?? 0;
        $totalFees = ($vehicleReturn->late_fee ?? 0) + ($vehicleReturn->damage_fee ?? 0) + ($vehicleReturn->cleaning_fee ?? 0);
        $defaultRefundAmount = max(0, $depositAmount - $totalFees);

        $updateData = [
            'deposit_status' => $validated['deposit_status'],
            'deposit_refund_notes' => $validated['deposit_refund_notes'],
        ];

        if ($validated['deposit_status'] === 'refunded') {
            $updateData['deposit_refund_amount'] = $validated['deposit_refund_amount'] ?? $defaultRefundAmount;
            $updateData['deposit_refund_proof'] = $validated['deposit_refund_proof'];
            $updateData['refund_method'] = $validated['refund_method'];
            $updateData['deposit_refunded_at'] = now();
        }

        $vehicleReturn->update($updateData);

        // Notify customer about deposit refund
        app(\App\Services\NotificationService::class)->notifyUser(
            $booking->user_id,
            'deposit_refund_processed',
            $booking,
            [
                'message' => $validated['deposit_status'] === 'refunded' 
                    ? 'Your security deposit has been refunded' 
                    : 'Your security deposit has been withheld',
                'deposit_status' => $validated['deposit_status'],
                'deposit_refund_amount' => $updateData['deposit_refund_amount'] ?? 0,
                'refund_method' => $validated['refund_method'] ?? null,
                'booking_id' => $booking->id,
                'vehicle_name' => $booking->vehicle->name ?? 'Vehicle',
            ]
        );

        return response()->json([
            'message' => 'Deposit refund processed successfully', 
            'vehicle_return' => $vehicleReturn->fresh()
        ]);
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

    /**
     * Process refund for a cancelled booking
     */
    public function processRefund(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:1000',
            'refund_proof' => 'required|string', // base64 encoded image
        ]);

        $booking = Booking::findOrFail($bookingId);

        // Only allow refund processing for cancelled bookings with pending refunds
        if ($booking->status !== 'cancelled') {
            return response()->json(['message' => 'Booking is not cancelled'], 422);
        }

        if ($booking->refund_status !== 'pending') {
            return response()->json(['message' => 'Refund not available for processing'], 422);
        }

        // Check if there are approved payments for this booking
        $approvedPayments = $booking->payments()->where('status', 'approved')->get();
        if ($approvedPayments->isEmpty()) {
            return response()->json(['message' => 'No approved payments found for this booking. Refund cannot be processed.'], 422);
        }

        // Update refund status with admin-specified amount
        $booking->refund_status = 'processed';
        $booking->refund_amount = $validated['amount'];
        $booking->refund_processed_at = now();
        $booking->refund_notes = $validated['notes'] ?? null;
        $booking->refund_proof = $validated['refund_proof']; // Store base64 directly
        $booking->save();

        // Notify customer about refund completion
        app(\App\Services\NotificationService::class)->notifyUser(
            $booking->user_id,
            'refund_processed',
            $booking,
            [
                'message' => 'Your booking refund has been processed',
                'vehicle_name' => $booking->vehicle->name ?? 'Vehicle',
                'booking_id' => $booking->id,
                'refund_amount' => $booking->refund_amount,
                'refund_notes' => $booking->refund_notes,
                'processed_at' => now(),
                'approved_payments_count' => $approvedPayments->count(),
            ]
        );

        return response()->json([
            'message' => 'Refund processed successfully',
            'booking' => $booking->fresh(),
        ]);
    }
}
