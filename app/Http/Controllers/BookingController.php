<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use App\Models\Booking;
use App\Models\Vehicle;
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
     * Customer submits vehicle return details and images
     */
    public function submitReturn(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'customer_images' => 'nullable|array',
            'customer_images.*' => 'string', // base64 encoded images
            'customer_condition_notes' => 'nullable|string|max:1000',
            'odometer' => 'nullable|integer|min:0',
            'fuel_level' => 'nullable|string|max:50',
            'returned_at' => 'nullable|date',
            // Customer refund account information
            'customer_refund_method' => 'required|string|in:gcash,bank_transfer,cash',
            'customer_account_number' => 'nullable|string|max:50',
            'customer_account_name' => 'nullable|string|max:100',
            'customer_bank_name' => 'nullable|string|max:100',
            'customer_refund_notes' => 'nullable|string|max:500',
        ]);

        $userId = Auth::id();
        $booking = Booking::findOrFail($bookingId);

        if ($booking->user_id !== $userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Only allow return submission if booking is released and not already returned
        if ($booking->status !== 'released') {
            return response()->json(['message' => 'Booking not eligible for return submission'], 422);
        }

        // Check if return already exists
        if ($booking->vehicleReturn) {
            return response()->json(['message' => 'Return already submitted'], 422);
        }

        // Create the return record with customer data
        $return = $booking->vehicleReturn()->create([
            'vehicle_id' => $booking->vehicle_id,
            'customer_images' => $validated['customer_images'],
            'customer_condition_notes' => $validated['customer_condition_notes'] ?? null,
            'odometer' => $validated['odometer'] ?? null,
            'fuel_level' => $validated['fuel_level'] ?? null,
            'status' => 'customer_submitted',
            'customer_submitted_at' => now(),
            'returned_at' => $validated['returned_at'] ?? now(),
            // Customer refund account information
            'customer_refund_method' => $validated['customer_refund_method'],
            'customer_account_number' => $validated['customer_account_number'] ?? null,
            'customer_account_name' => $validated['customer_account_name'] ?? null,
            'customer_bank_name' => $validated['customer_bank_name'] ?? null,
            'customer_refund_notes' => $validated['customer_refund_notes'] ?? null,
        ]);

        // Update booking status to indicate return is pending admin review
        $booking->status = 'pending_return';
        $booking->save();

        // Notify admins about the return submission
        $this->notificationService->notifyAdmins('vehicle_return_submitted', $booking, [
            'message' => 'Customer submitted vehicle return',
            'customer_name' => Auth::user()->name,
            'vehicle_name' => $booking->vehicle->name ?? 'Vehicle',
            'booking_id' => $booking->id,
            'customer_images_count' => count($validated['customer_images'])
        ]);

        return response()->json([
            'message' => 'Return submitted successfully. Admin will review and process your return.',
            'return' => $return
        ]);
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
            'booking_id' => 'nullable|integer|exists:bookings,id',
        ]);

        $bookingId = $validated['booking_id'] ?? null;
        $bookingToExclude = null;

        if ($bookingId) {
            $bookingToExclude = Booking::findOrFail($bookingId);

            if ($bookingToExclude->user_id !== Auth::id()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }
        $hasConflict = !$this->bookingService->isAvailable(
            $validated['vehicle_id'],
            $validated['start_date'],
            $validated['end_date'],
            $bookingId
        );
        $vehicle = $bookingToExclude && (int) $bookingToExclude->vehicle_id === (int) $validated['vehicle_id']
            ? $bookingToExclude->vehicle
            : Vehicle::findOrFail($validated['vehicle_id']);
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
        if (!$service->isAvailable($vehicleId, $startDate, $endDate, $booking->id)) {
            return response()->json(['message' => 'Vehicle not available for selected dates'], 409);
        }
        // Calculate delivery fee
        $deliveryFee = $pickupType === 'delivery' ? (Booking::DELIVERY_FEES[$deliveryLocation] ?? 0) : 0;
        // Calculate days as integer (ceil for partial days, always at least 1)
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        $hours = $start->floatDiffInHours($end);
        $days = max(1, (int) ceil($hours / 24));
        $vehicleModel = (int) $booking->vehicle_id === (int) $vehicleId
            ? $booking->vehicle
            : Vehicle::findOrFail($vehicleId);
        $driverRequested = $booking->driver_requested;
        $booking->update(array_merge($validated, [
            'vehicle_id' => $vehicleId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pickup_type' => $pickupType,
            'delivery_location' => $deliveryLocation,
            'delivery_details' => $deliveryDetails,
            'delivery_fee' => $deliveryFee,
            'total_price' => $service->calculatePrice($vehicleModel, $startDate, $endDate, $driverRequested) + $deliveryFee,
            'days' => $days,
        ]));
        return response()->json(['message' => 'Booking updated', 'booking' => $booking]);
    }

    /**
     * Cancel a booking (FR007)
     */
    public function cancel(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500',
            // Refund account information (optional, only if has approved payments)
            'refund_method' => 'nullable|string|in:gcash,bank_transfer,cash',
            'account_number' => 'nullable|string|max:100',
            'account_name' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'refund_notes' => 'nullable|string|max:500',
        ]);

        $userId = Auth::id();
        $booking = Booking::findOrFail($bookingId);
        if ($booking->user_id !== $userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        // Only allow cancellation if not already cancelled or completed
        if (in_array($booking->status, ['cancelled', 'completed'])) {
            return response()->json(['message' => 'Booking cannot be cancelled'], 422);
        }

        // Check if there are any approved payments for this booking
        $approvedPayments = $booking->payments()->where('status', 'approved')->get();
        $hasApprovedPayments = $approvedPayments->isNotEmpty();

        // Only calculate refund if there are approved payments
        $refund = 0;
        $refundAmount = 0;
        
        if ($hasApprovedPayments) {
            // Refund logic: full if >7 days before, 50% if <7 days, none if <24h
            $hours = now()->diffInHours($booking->start_date, false);
            
            if ($hours >= 168) { // 7 days
                $refund = 1.0;
            } elseif ($hours >= 24) {
                $refund = 0.5;
            }
            
            // Calculate refund based on approved payments only
            $totalApprovedAmount = $approvedPayments->sum(function($payment) {
                // For simplicity, we'll use the vehicle deposit as the payment amount
                // In a real system, you might store the payment amount in the payments table
                $vehicle = $payment->booking->vehicle;
                return $vehicle ? $vehicle->deposit : 0;
            });
            
            $refundAmount = $totalApprovedAmount * $refund;
        }
        
        $booking->status = 'cancelled';
        $booking->refund_rate = $refund;
        $booking->refund_amount = $refundAmount;
        $booking->refund_status = $refundAmount > 0 ? 'pending' : 'not_applicable';
        $booking->cancelled_at = now();
        $booking->cancellation_reason = $validated['cancellation_reason'];
        
        // Store customer refund account information if provided and has approved payments
        if ($hasApprovedPayments && !empty($validated['refund_method'])) {
            $booking->refund_method = $validated['refund_method'];
            $booking->refund_account_number = $validated['account_number'];
            $booking->refund_account_name = $validated['account_name'];
            $booking->refund_bank_name = $validated['bank_name'];
            $booking->refund_customer_notes = $validated['refund_notes'];
        }
        
        $booking->save();

        // Notify admins about the customer cancellation
        $this->notificationService->notifyAdmins('booking_cancelled_by_customer', $booking, [
            'message' => 'Customer cancelled a booking',
            'customer_name' => Auth::user()->name,
            'vehicle_name' => $booking->vehicle->name ?? 'Vehicle',
            'booking_id' => $booking->id,
            'start_date' => $booking->start_date,
            'end_date' => $booking->end_date,
            'total_price' => $booking->total_price,
            'refund_rate' => $refund,
            'refund_amount' => $refundAmount,
            'cancellation_reason' => $validated['cancellation_reason'],
            'cancelled_at' => now(),
            'has_approved_payments' => $hasApprovedPayments,
        ]);

        return response()->json([
            'message' => 'Booking cancelled successfully',
            'refund_rate' => $refund,
            'refund_amount' => $refundAmount,
            'refund_note' => $refundAmount > 0 
                ? 'Your deposit refund will be processed by an admin within 1-3 business days.' 
                : 'No refund is applicable for this cancellation timing.',
        ]);
    }

    /**
     * Allow customers to submit refund account details after an automatic cancellation.
     */
    public function submitRefundDetails(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'refund_method' => 'required|string|in:gcash,bank_transfer,cash',
            'account_number' => 'nullable|string|max:100|required_if:refund_method,gcash|required_if:refund_method,bank_transfer',
            'account_name' => 'nullable|string|max:100|required_if:refund_method,gcash|required_if:refund_method,bank_transfer',
            'bank_name' => 'nullable|string|max:100|required_if:refund_method,bank_transfer',
            'refund_notes' => 'nullable|string|max:500',
        ]);

        $userId = Auth::id();
        $booking = Booking::with('payments', 'vehicle')->findOrFail($bookingId);

        if ($booking->user_id !== $userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($booking->status !== 'cancelled') {
            return response()->json(['message' => 'Refund details can only be submitted for cancelled bookings'], 422);
        }

        if ($booking->refund_status !== 'pending' || ($booking->refund_amount ?? 0) <= 0) {
            return response()->json(['message' => 'No pending refund found for this booking'], 422);
        }

        $hasRefundablePayments = $booking->payments()
            ->whereIn('status', ['approved', 'rejected'])
            ->exists();

        if (!$hasRefundablePayments) {
            return response()->json(['message' => 'No payments eligible for refund were found for this booking'], 422);
        }

        $booking->refund_method = $validated['refund_method'];
        $booking->refund_account_number = $validated['account_number'] ?? null;
        $booking->refund_account_name = $validated['account_name'] ?? null;
        $booking->refund_bank_name = $validated['bank_name'] ?? null;
        $booking->refund_customer_notes = $validated['refund_notes'] ?? null;
        $booking->save();

        $this->notificationService->notifyAdmins('refund_details_submitted', $booking, [
            'message' => 'Customer provided refund details for an auto-cancelled booking',
            'customer_name' => Auth::user()->name,
            'vehicle_name' => $booking->vehicle->name ?? 'Vehicle',
            'booking_id' => $booking->id,
            'refund_amount' => $booking->refund_amount,
            'refund_method' => $booking->refund_method,
        ]);

        return response()->json([
            'message' => 'Refund details submitted successfully. Our team will process your refund soon.',
            'booking' => $booking->fresh('vehicle')
        ]);
    }

    /**
     * List bookings for the authenticated user
     */
    public function myBookings(Request $request)
    {
        $userId = Auth::id();
        
        // Get sorting parameters with defaults
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');
        
        // Get status filter
        $statusFilter = $request->query('status');
        
        // Validate sort_by parameter to prevent SQL injection
        $allowedSortFields = ['created_at', 'start_date', 'end_date', 'total_price', 'status'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }
        
        // Validate sort_order parameter
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }
        
        // Validate status filter
        $allowedStatuses = ['pending', 'confirmed', 'for_release', 'released', 'cancelled'];
        if ($statusFilter && !in_array($statusFilter, $allowedStatuses)) {
            $statusFilter = null;
        }
        
        $query = Booking::where('user_id', $userId)
            ->where('status', '!=', 'completed')
            ->with([
                'vehicle',
                'payments',
                'latestDepositPayment',
                'latestRentalPayment'
            ]);
        
        // Apply status filter if provided
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }
        
        $bookings = $query->orderBy($sortBy, $sortOrder)->get();
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
                'deposit_status' => $return->deposit_status,
                'deposit_refund_amount' => $return->deposit_refund_amount,
                'deposit_refund_notes' => $return->deposit_refund_notes,
                'deposit_refund_proof' => $return->deposit_refund_proof,
                'refund_method' => $return->refund_method,
            ] : null,
        ];
        return response()->json(['completed_booking_summary' => $response]);
    }
}
