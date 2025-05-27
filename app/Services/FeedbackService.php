<?php
namespace App\Services;

use App\Models\Feedback;
use Illuminate\Support\Facades\Auth;

class FeedbackService
{
    public function create(array $data)
    {
        return Feedback::create([
            'user_id' => Auth::id(),
            'booking_id' => $data['booking_id'],
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);
    }

    public function listByBooking($bookingId)
    {
        return Feedback::where('booking_id', $bookingId)->with('user')->get();
    }

    public function listByUser($userId)
    {
        return Feedback::where('user_id', $userId)->with('booking')->get();
    }

    public function listAll()
    {
        return Feedback::with(['user', 'booking'])->get();
    }
    
    public function listByVehicle($vehicleId)
    {
        return Feedback::whereHas('booking', function ($q) use ($vehicleId) {
            $q->where('vehicle_id', $vehicleId);
        })->with(['user', 'booking'])->get();
    }
}
