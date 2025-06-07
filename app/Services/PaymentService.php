<?php

namespace App\Services;

use App\Models\Payment;

class PaymentService
{
    /**
     * Get recent payments for a user
     */
    public function getRecentPayments($userId, $limit = 5)
    {
        return Payment::whereHas('booking', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->with('booking')
        ->latest()
        ->take($limit)
        ->get()
        ->map(function ($payment) {
            return [
                'id' => $payment->id,
                'type' => $payment->type,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'booking_id' => $payment->booking_id,
                'created_at' => $payment->created_at,
            ];
        });
    }

    /**
     * Get count of pending payments for a user
     */
    public function getPendingPaymentsCount($userId)
    {
        return Payment::whereHas('booking', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->where('status', 'pending')
        ->count();
    }

    /**
     * Get payment status for a list of booking IDs
     */
    public function getPaymentsForBookings($bookingIds)
    {
        return Payment::whereIn('booking_id', $bookingIds)
            ->get();
    }
}
