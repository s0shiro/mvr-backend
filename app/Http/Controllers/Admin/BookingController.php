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

        return response()->json(['message' => 'Vehicle released', 'release' => $release]);
    }
}
