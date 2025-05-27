<?php
namespace App\Http\Controllers;

use App\Services\FeedbackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    protected $feedbackService;

    public function __construct(FeedbackService $feedbackService)
    {
        $this->feedbackService = $feedbackService;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);
        $feedback = $this->feedbackService->create($validated);
        return response()->json(['message' => 'Feedback submitted successfully', 'feedback' => $feedback], 201);
    }

    public function byBooking($bookingId)
    {
        $feedback = $this->feedbackService->listByBooking($bookingId);
        return response()->json($feedback);
    }

    public function byUser($userId)
    {
        $feedback = $this->feedbackService->listByUser($userId);
        return response()->json($feedback);
    }

    public function byVehicle($vehicleId)
    {
        $feedback = $this->feedbackService->listByVehicle($vehicleId);
        return response()->json($feedback);
    }

    public function index()
    {
        $feedback = $this->feedbackService->listAll();
        return response()->json($feedback);
    }
}
