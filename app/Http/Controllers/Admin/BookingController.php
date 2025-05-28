<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * List all bookings with their payments and vehicle details
     */
    public function index()
    {
        $bookings = Booking::with(['user', 'vehicle', 'payments', 'vehicleRelease'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['bookings' => $bookings]);
    }

    /**
     * List bookings ready for vehicle release (status = 'for_release')
     */
    public function forRelease()
    {
        $bookings = Booking::with(['user', 'vehicle', 'payments', 'vehicleRelease'])
            ->where('status', 'for_release')
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
            'images.*' => 'nullable|string', // base64 or url
        ]);

        // Only allow release if booking is for_release and not already released
        if ($booking->status !== 'for_release' || $booking->vehicleRelease) {
            return response()->json(['message' => 'Booking not eligible for release or already released'], 422);
        }

        $release = $booking->vehicleRelease()->create(array_merge($validated, [
            'vehicle_id' => $booking->vehicle_id,
            'released_at' => $validated['released_at'] ?? now(),
        ]));

        $booking->status = 'released';
        $booking->save();

        // Update vehicle status to in_use
        $vehicle = $booking->vehicle;
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
        $scheduledEnd = $booking->end_date;
        $actualReturn = isset($validated['returned_at']) ? now()->parse($validated['returned_at']) : now();
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

        return response()->json(['message' => 'Vehicle returned', 'return' => $return]);
    }
}
