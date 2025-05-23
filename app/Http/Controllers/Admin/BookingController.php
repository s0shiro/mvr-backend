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
        $bookings = Booking::with(['user', 'vehicle', 'payments'])
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
}
