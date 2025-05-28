<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    // Static payment info for demo
    private $paymentMethods = [
        'gcash' => [
            'label' => 'GCash',
            'account_name' => 'MVR Rentals',
            'account_number' => '09171234567',
        ],
        'bank_transfer' => [
            'label' => 'Bank Transfer',
            'bank_name' => 'BDO',
            'account_name' => 'MVR Rentals',
            'account_number' => '1234567890',
        ],
    ];

    public function methods()
    {
        return response()->json($this->paymentMethods);
    }

    // Customer submits payment info
    public function store(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'method' => 'required|in:gcash,bank_transfer',
            'reference_number' => 'required|string',
            'proof_image' => 'required|string', // base64
            'type' => 'in:deposit,rental', // optional, default to deposit
        ]);
        $userId = Auth::id();
        $booking = Booking::findOrFail($bookingId);
        if ($booking->user_id !== $userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $type = $validated['type'] ?? 'deposit';
        $existingPayment = $type === 'deposit' ? $booking->depositPayment : $booking->rentalPayment;
        if ($existingPayment && $existingPayment->exists && $existingPayment->status !== 'rejected') {
            return response()->json(['message' => ucfirst($type).' payment already submitted'], 409);
        }
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'method' => $validated['method'],
            'reference_number' => $validated['reference_number'],
            'proof_image' => $validated['proof_image'],
            'status' => 'pending',
            'type' => $type,
        ]);

        // Send notification to admins about new payment
        $this->notificationService->notifyAdmins('payment_submitted', $payment, [
            'message' => ucfirst($type).' payment submitted',
            'customer_name' => Auth::user()->name,
            'booking_id' => $booking->id,
            'payment_type' => $type,
            'payment_method' => $validated['method']
        ]);

        return response()->json(['message' => ucfirst($type).' payment submitted', 'payment' => $payment], 201);
    }

    // Admin or user views payment info
    public function show(Request $request, $bookingId)
    {
        $type = $request->query('type', 'deposit');
        $booking = Booking::findOrFail($bookingId);
        $payment = $type === 'deposit' ? $booking->depositPayment : $booking->rentalPayment;
        if (!$payment) {
            return response()->json(['message' => 'No payment found'], 404);
        }
        return response()->json($payment);
    }

    // Admin updates payment status
    public function updateStatus(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'type' => 'in:deposit,rental', // optional, default to deposit
        ]);
        $type = $validated['type'] ?? $request->query('type', 'deposit');
        $payment = Payment::where('booking_id', $bookingId)->where('type', $type)->firstOrFail();
        $payment->status = $validated['status'];
        $payment->save();
        // If rental payment is approved, update booking status to 'paid'
        if ($validated['status'] === 'approved' && $type === 'rental') {
            $booking = $payment->booking;
            $booking->status = 'paid';
            $booking->save();
        }
        return response()->json(['message' => ucfirst($type).' payment status updated', 'payment' => $payment]);
    }
}
