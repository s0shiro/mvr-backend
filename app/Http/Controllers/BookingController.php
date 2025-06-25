<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use App\Models\Booking;
use NumberToWords\NumberToWords;

class BookingController extends Controller
{
    protected $bookingService;
    protected $notificationService;

    public function __construct(
        BookingService $bookingService,
        NotificationService $notificationService
    ) {
        $this->bookingService = $bookingService;
        $this->notificationService = $notificationService;
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
            $msg = ($validated['driver_requested'] ?? false)
                ? 'No driver available for selected dates'
                : 'Vehicle not available for selected dates';
            return response()->json(['message' => $msg], 409);
        }

        // Send notification to admins
        $this->notificationService->notifyAdmins('booking_created', $booking, [
            'message' => 'New booking created',
            'customer_name' => Auth::user()->name,
            'vehicle_id' => $validated['vehicle_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'booking_id' => $booking->id
        ]);

        return response()->json(['message' => 'Booking created', 'booking' => $booking->load('driver')], 201);
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
        $hasConflict = !$this->bookingService->isAvailable(
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

        // Check driver availability if requested
        $driverAvailable = true;
        if ($driverRequested) {
            $driver = $this->bookingService->findAvailableDriver($validated['start_date'], $validated['end_date']);
            $driverAvailable = $driver !== null;
        }

        return response()->json([
            'available' => !$hasConflict,
            'driver_available' => $driverAvailable,
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
        // Calculate days as integer (ceil for partial days, always at least 1)
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        $hours = $start->floatDiffInHours($end);
        $days = max(1, (int) ceil($hours / 24));
        $booking->update(array_merge($validated, [
            'vehicle_id' => $vehicleId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pickup_type' => $pickupType,
            'delivery_location' => $deliveryLocation,
            'delivery_details' => $deliveryDetails,
            'delivery_fee' => $deliveryFee,
            'total_price' => $service->calculatePrice($booking->vehicle, $startDate, $endDate) + $deliveryFee,
            'days' => $days,
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
        // Refund is based on the vehicle's deposit, not total_price
        $vehicle = $booking->vehicle;
        $deposit = $vehicle ? $vehicle->deposit : 0;
        $refundAmount = $deposit * $refund;
        $booking->status = 'cancelled';
        $booking->refund_rate = $refund;
        $booking->refund_amount = $refundAmount;
        $booking->save();
        return response()->json([
            'message' => 'Booking cancelled',
            'refund_rate' => $refund,
            'refund_amount' => $refundAmount,
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

    /**
     * Get a detailed summary of a booking
     *
     * @param int $bookingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function summaryDetails($bookingId)
    {
        $booking = Booking::with(['user', 'vehicle', 'vehicleRelease'])->findOrFail($bookingId);
        $user = $booking->user;
        $vehicle = $booking->vehicle;
        $releaseDate = $booking->start_date;
        $executedAt = $booking->updated_at ?? $booking->created_at;
        $periodDays = $booking->days ?? (\Carbon\Carbon::parse($booking->start_date)->diffInDays(\Carbon\Carbon::parse($booking->end_date)) + 1);

        // Convert numbers to words
        $numberToWords = new NumberToWords();
        $numberTransformer = $numberToWords->getNumberTransformer('en');
        $rentalAmountWords = $booking->total_price !== null ? strtoupper($numberTransformer->toWords((int) $booking->total_price)) . ' PESOS' : null;
        $securityDepositWords = $vehicle?->deposit !== null ? strtoupper($numberTransformer->toWords((int) $vehicle->deposit)) . ' PESOS' : null;

        $response = [
            'executed_at' => $executedAt ? \Carbon\Carbon::parse($executedAt)->format('d/m/Y') : null,
            'customer_name' => $user?->name,
            'customer_address' => $user?->address,
            'vehicle' => [
                'name' => $vehicle?->name,
                'brand' => $vehicle?->brand,
                'year' => $vehicle?->year,
                'model' => $vehicle?->model,
            ],
            'period' => $periodDays . ' day' . ($periodDays > 1 ? 's' : ''),
            'release_date' => $releaseDate ? \Carbon\Carbon::parse($releaseDate)->format('d/m/Y') : null,
            'rental_amount' => $booking->total_price,
            'rental_amount_words' => $rentalAmountWords,
            'security_deposit' => $vehicle?->deposit,
            'security_deposit_words' => $securityDepositWords,
            'hourly_rate' => $vehicle?->rental_rate ? round($vehicle->rental_rate / 24, 2) : null,
            'daily_rate' => $vehicle?->rental_rate,
            'daily_rate_words' => $vehicle?->rental_rate ? strtoupper($numberTransformer->toWords((int) $vehicle->rental_rate)) . ' PESOS' : null,
        ];
        return response()->json(['booking_summary' => $response]);
    }

    /**
     * Get a comprehensive summary of a completed booking, including all related details
     *
     * @param int $bookingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function completedBookingDetails($bookingId)
    {
        $booking = Booking::with([
            'user',
            'vehicle',
            'vehicleRelease',
            'vehicleReturn',
            'payments',
            'latestDepositPayment',
            'latestRentalPayment',
        ])->findOrFail($bookingId);
        if ($booking->status !== 'completed') {
            return response()->json(['message' => 'Booking is not completed.'], 422);
        }
        $user = $booking->user;
        $vehicle = $booking->vehicle;
        $release = $booking->vehicleRelease;
        $return = $booking->vehicleReturn;
        $payments = $booking->payments;
        $depositPayment = $booking->latestDepositPayment;
        $rentalPayment = $booking->latestRentalPayment;
        $releaseDate = $booking->vehilceRelease?->released_at ?? $booking->start_date;
        $executedAt = $booking->updated_at ?? $booking->created_at;
        $periodDays = $booking->days ?? (\Carbon\Carbon::parse($booking->start_date)->diffInDays(\Carbon\Carbon::parse($booking->end_date)) + 1);

        // Convert numbers to words
        $numberToWords = new \NumberToWords\NumberToWords();
        $numberTransformer = $numberToWords->getNumberTransformer('en');
        $rentalAmountWords = $booking->total_price !== null ? strtoupper($numberTransformer->toWords((int) $booking->total_price)) . ' PESOS' : null;
        $securityDepositWords = $vehicle?->deposit !== null ? strtoupper($numberTransformer->toWords((int) $vehicle->deposit)) . ' PESOS' : null;

        $response = [
            'executed_at' => $executedAt ? \Carbon\Carbon::parse($executedAt)->format('d/m/Y') : null,
            'customer_name' => $user?->name,
            'customer_address' => $user?->address,
            'vehicle' => [
                'name' => $vehicle?->name,
                'brand' => $vehicle?->brand,
                'year' => $vehicle?->year,
                'model' => $vehicle?->model,
            ],
            'period' => $periodDays . ' day' . ($periodDays > 1 ? 's' : ''),
            'release_date' => $releaseDate ? \Carbon\Carbon::parse($releaseDate)->format('d/m/Y') : null,
            'rental_amount' => $booking->total_price,
            'rental_amount_words' => $rentalAmountWords,
            'security_deposit' => $vehicle?->deposit,
            'security_deposit_words' => $securityDepositWords,
            'hourly_rate' => $vehicle?->rental_rate ? round($vehicle->rental_rate / 24, 2) : null,
            'daily_rate' => $vehicle?->rental_rate,
            'daily_rate_words' => $vehicle?->rental_rate ? strtoupper($numberTransformer->toWords((int) $vehicle->rental_rate)) . ' PESOS' : null,
            'payments' => $payments,
            'deposit_payment' => $depositPayment,
            'rental_payment' => $rentalPayment,
            'vehicle_release' => $release ? [
                'released_at' => $release->released_at ? $release->released_at->format('d/m/Y H:i') : null,
                'odometer' => $release->odometer,
                'fuel_level' => $release->fuel_level,
                'condition_notes' => $release->condition_notes,
                'images' => $release->images,
            ] : null,
            'vehicle_return' => $return ? [
                'returned_at' => $return->returned_at ? $return->returned_at->format('d/m/Y H:i') : null,
                'odometer' => $return->odometer,
                'fuel_level' => $return->fuel_level,
                'condition_notes' => $return->condition_notes,
                'images' => $return->images,
                'late_fee' => $return->late_fee,
                'damage_fee' => $return->damage_fee,
                'cleaning_fee' => $return->cleaning_fee,
            ] : null,
        ];
        return response()->json(['completed_booking_summary' => $response]);
    }
}
