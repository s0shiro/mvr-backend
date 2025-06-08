<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Vehicle;
use App\Models\Driver;
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
     * Find an available driver for the given period.
     */
    public function findAvailableDriver($startDate, $endDate)
    {
        // Only consider drivers who are active (ignore available flag)
        $drivers = \App\Models\Driver::where('status', 'active')->get();
        foreach ($drivers as $driver) {
            $hasConflict = Booking::where('driver_id', $driver->id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })
                ->exists();
            if (!$hasConflict) {
                return $driver;
            }
        }
        return null;
    }

    /**
     * Create a booking if available, and assign driver if requested.
     */
    public function createBooking($userId, $vehicleId, $startDate, $endDate, $notes = null, $driverRequested = false, $pickupType = 'pickup', $deliveryLocation = null, $deliveryDetails = null, $validIds = null)
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
        // Calculate days as integer (ceil for partial days, always at least 1)
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $hours = $start->floatDiffInHours($end);
        $days = max(1, (int) ceil($hours / 24));
        $driverId = null;
        if ($driverRequested) {
            $driver = $this->findAvailableDriver($startDate, $endDate);
            if (!$driver) {
                return null;
            }
            $driverId = $driver->id;
            // No need to set available flag
        }
        return Booking::create([
            'user_id' => $userId,
            'vehicle_id' => $vehicleId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'pending',
            'total_price' => $totalPrice + $deliveryFee,
            'notes' => $notes,
            'driver_requested' => $driverRequested,
            'driver_id' => $driverId,
            'pickup_type' => $pickupType,
            'delivery_location' => $deliveryLocation,
            'delivery_details' => $deliveryDetails,
            'delivery_fee' => $deliveryFee,
            'valid_ids' => $validIds,
            'days' => $days,
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

    /**
     * Calculate late fee for a booking return
     */
    public function calculateLateFee($scheduledEnd, $actualReturn)
    {
        if ($actualReturn->greaterThan($scheduledEnd)) {
            $hoursLate = $scheduledEnd->diffInHours($actualReturn);
            return $hoursLate * 100; // ₱100/hour
        }
        return 0;
    }

    /**
     * Get booking summary for a user (for dashboard)
     */
    public function getUserBookingSummary($userId)
    {
        // Get all bookings for the user, with vehicle info, ordered by start date desc
        return \App\Models\Booking::with('vehicle')
            ->where('user_id', $userId)
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Get all bookings for a user
     */
    public function getUserBookings($userId)
    {
        return Booking::where('user_id', $userId)
            ->with('vehicle')
            ->latest()
            ->get();
    }
}
