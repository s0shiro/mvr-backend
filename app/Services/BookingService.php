<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class BookingService
{
    /**
     * Check if a vehicle is available for the given period, excluding a booking (for update).
     */
    public function isAvailable($vehicleId, $startDate, $endDate, $excludeBookingId = null)
    {
        $query = Booking::where('vehicle_id', $vehicleId)
            ->where('status', '!=', 'cancelled');
        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }
        return $query->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
            })
            ->exists();
    }

    /**
     * Create a booking if available.
     */
    public function createBooking($userId, $vehicleId, $startDate, $endDate, $notes = null, $driverRequested = false, $pickupType = 'pickup', $deliveryLocation = null, $deliveryDetails = null)
    {
        if ($this->isAvailable($vehicleId, $startDate, $endDate)) {
            return null; // Not available
        }
        $vehicle = Vehicle::findOrFail($vehicleId);
        
        // Calculate delivery fee if applicable
        $deliveryFee = 0;
        if ($pickupType === 'delivery' && $deliveryLocation) {
            $deliveryFee = Booking::DELIVERY_FEES[$deliveryLocation] ?? 0;
        }

        $totalPrice = $this->calculatePrice($vehicle, $startDate, $endDate, $driverRequested);
        
        return Booking::create([
            'user_id' => $userId,
            'vehicle_id' => $vehicleId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'pending',
            'total_price' => $totalPrice + $deliveryFee,
            'notes' => $notes,
            'driver_requested' => $driverRequested,
            'pickup_type' => $pickupType,
            'delivery_location' => $deliveryLocation,
            'delivery_details' => $deliveryDetails,
            'delivery_fee' => $deliveryFee,
        ]);
    }

    /**
     * Calculate price for the booking.
     */
    public function calculatePrice($vehicle, $startDate, $endDate, $driverRequested = false)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = $start->diffInDays($end) ?: 1;
        
        // Use the appropriate rate based on whether a driver is requested
        $rate = $driverRequested ? $vehicle->rental_rate_with_driver : $vehicle->rental_rate;
        
        return $rate * $days;
    }
}
