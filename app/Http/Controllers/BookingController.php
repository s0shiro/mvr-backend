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
            'driver_requested' => 'boolean',
            'pickup_type' => 'required|in:pickup,delivery',
            'delivery_location' => 'sometimes|required_if:pickup_type,delivery|string|in:' . implode(',', array_keys(Booking::DELIVERY_FEES)),
            'delivery_details' => 'sometimes|required_if:pickup_type,delivery|string|max:500',
            'valid_ids' => 'required|array|size:2',
            'valid_ids.id1' => 'required|string', // base64 string
            'valid_ids.id2' => 'required|string', // base64 string
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
            $validated['notes'] ?? null,
            $validated['driver_requested'] ?? false,
            $validated['pickup_type'] ?? 'pickup',
            $validated['delivery_location'] ?? null,
            $validated['delivery_details'] ?? null,
            $validated['valid_ids'] ?? null
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
            'driver_requested' => 'boolean',
        ]);
        $hasConflict = $this->bookingService->isAvailable(
            $validated['vehicle_id'],
            $validated['start_date'],
            $validated['end_date']
        );
        $vehicle = \App\Models\Vehicle::findOrFail($validated['vehicle_id']);
        $driverRequested = $validated['driver_requested'] ?? false;
        $price = $this->bookingService->calculatePrice(
            $vehicle, 
            $validated['start_date'], 
            $validated['end_date'],
            $driverRequested
        );

        // Calculate delivery fee if applicable
        $deliveryFee = 0;
        if ($request->input('pickup_type') === 'delivery' && $request->input('delivery_location')) {
            $deliveryFee = Booking::DELIVERY_FEES[$request->input('delivery_location')] ?? 0;
        }

        return response()->json([
            'available' => !$hasConflict,
            'total_price' => $price + $deliveryFee,
            'rental_rate' => $driverRequested ? $vehicle->rental_rate_with_driver : $vehicle->rental_rate,
            'with_driver' => $driverRequested,
            'delivery_options' => [
                'locations' => array_map(function($location, $fee) {
                    return [
                        'name' => $location,
                        'fee' => $fee
                    ];
                }, array_keys(Booking::DELIVERY_FEES), array_values(Booking::DELIVERY_FEES)),
                'delivery_fee' => $request->input('pickup_type') === 'delivery' 
                    ? (Booking::DELIVERY_FEES[$request->input('delivery_location')] ?? 0)
                    : 0,
            ],
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
            'pickup_type' => 'sometimes|in:pickup,delivery',
            'delivery_location' => 'sometimes|required_if:pickup_type,delivery|string|in:' . implode(',', array_keys(Booking::DELIVERY_FEES)),
            'delivery_details' => 'sometimes|required_if:pickup_type,delivery|string|max:500',
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
        $pickupType = $validated['pickup_type'] ?? $booking->pickup_type;
        $deliveryLocation = $validated['delivery_location'] ?? $booking->delivery_location;
        $deliveryDetails = $validated['delivery_details'] ?? $booking->delivery_details;
        $service = app(BookingService::class);
        if ($service->isAvailable($vehicleId, $startDate, $endDate, $booking->id)) {
            return response()->json(['message' => 'Vehicle not available for selected dates'], 409);
        }
        // Calculate delivery fee
        $deliveryFee = $pickupType === 'delivery' ? (Booking::DELIVERY_FEES[$deliveryLocation] ?? 0) : 0;
        
        $booking->update(array_merge($validated, [
            'vehicle_id' => $vehicleId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pickup_type' => $pickupType,
            'delivery_location' => $deliveryLocation,
            'delivery_details' => $deliveryDetails,
            'delivery_fee' => $deliveryFee,
            'total_price' => $service->calculatePrice($booking->vehicle, $startDate, $endDate) + $deliveryFee,
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

    /**
     * List completed bookings for the authenticated user
     */
    public function myCompletedBookings(Request $request)
    {
        $userId = Auth::id();
        $bookings = Booking::where('user_id', $userId)
            ->where('status', 'completed')
            ->with([
                'vehicle',
                'payments',
                'latestDepositPayment',
                'latestRentalPayment'
            ])
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['completed_bookings' => $bookings]);
    }
}
