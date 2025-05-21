<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BookingService;
use Illuminate\Support\Facades\Auth;
use App\Models\Booking;

class BookingController extends Controller
{
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Book a vehicle
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'start_date' => 'required|date|after:now',
            'end_date' => 'required|date|after:start_date',
            'notes' => 'nullable|string',
        ]);
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $booking = $this->bookingService->createBooking(
            $userId,
            $validated['vehicle_id'],
            $validated['start_date'],
            $validated['end_date'],
            $validated['notes'] ?? null
        );
        if (!$booking) {
            return response()->json(['message' => 'Vehicle not available for selected dates'], 409);
        }
        return response()->json(['message' => 'Booking created', 'booking' => $booking], 201);
    }

    /**
     * Get booking summary (price, availability)
     */
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'start_date' => 'required|date|after:now',
            'end_date' => 'required|date|after:start_date',
        ]);
        $available = !$this->bookingService->isAvailable(
            $validated['vehicle_id'],
            $validated['start_date'],
            $validated['end_date']
        );
        $vehicle = \App\Models\Vehicle::findOrFail($validated['vehicle_id']);
        $price = $this->bookingService->calculatePrice($vehicle, $validated['start_date'], $validated['end_date']);
        return response()->json([
            'available' => $available,
            'total_price' => $price,
        ]);
    }

    /**
     * Update an existing booking (FR006)
     */
    public function update(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'vehicle_id' => 'sometimes|exists:vehicles,id',
            'start_date' => 'sometimes|date|after:now',
            'end_date' => 'sometimes|date|after:start_date',
            'notes' => 'nullable|string',
        ]);
        $userId = Auth::id();
        $booking = Booking::findOrFail($bookingId);
        if ($booking->user_id !== $userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        // Only allow modification if booking is pending and at least 24h before start
        if ($booking->status !== 'pending' || now()->diffInHours($booking->start_date, false) < 24) {
            return response()->json(['message' => 'Modification not allowed'], 422);
        }
        $vehicleId = $validated['vehicle_id'] ?? $booking->vehicle_id;
        $startDate = $validated['start_date'] ?? $booking->start_date;
        $endDate = $validated['end_date'] ?? $booking->end_date;
        $service = app(BookingService::class);
        if (!$service->isAvailable($vehicleId, $startDate, $endDate, $booking->id)) {
            return response()->json(['message' => 'Vehicle not available for selected dates'], 409);
        }
        $booking->update(array_merge($validated, [
            'vehicle_id' => $vehicleId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_price' => $service->calculatePrice($booking->vehicle, $startDate, $endDate),
        ]));
        return response()->json(['message' => 'Booking updated', 'booking' => $booking]);
    }

    /**
     * Cancel a booking (FR007)
     */
    public function cancel(Request $request, $bookingId)
    {
        $userId = Auth::id();
        $booking = Booking::findOrFail($bookingId);
        if ($booking->user_id !== $userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        // Only allow cancellation if not already cancelled or completed
        if (in_array($booking->status, ['cancelled', 'completed'])) {
            return response()->json(['message' => 'Booking cannot be cancelled'], 422);
        }
        // Refund logic: full if >7 days before, 50% if <7 days, none if <24h
        $hours = now()->diffInHours($booking->start_date, false);
        $refund = 0;
        if ($hours >= 168) { // 7 days
            $refund = 1.0;
        } elseif ($hours >= 24) {
            $refund = 0.5;
        }
        $booking->status = 'cancelled';
        $booking->save();
        return response()->json([
            'message' => 'Booking cancelled',
            'refund_rate' => $refund,
            'refund_amount' => $booking->total_price * $refund,
        ]);
    }

    /**
     * List bookings for the authenticated user
     */
    public function myBookings(Request $request)
    {
        $userId = Auth::id();
        $bookings = Booking::where('user_id', $userId)
            ->with([
                'vehicle',
                'payments',
                'latestDepositPayment',
                'latestRentalPayment'
            ])
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['bookings' => $bookings]);
    }
}
