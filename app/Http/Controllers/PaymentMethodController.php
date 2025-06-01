<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    // List all payment methods
    public function index()
    {
        return response()->json(PaymentMethod::all());
    }

    // Store a new payment method
    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|unique:payment_methods,key',
            'label' => 'required|string',
            'account_name' => 'required|string',
            'account_number' => 'required|string',
            'bank_name' => 'nullable|string',
        ]);
        $method = PaymentMethod::create($validated);
        return response()->json($method, 201);
    }

    // Update an existing payment method
    public function update(Request $request, $id)
    {
        $method = PaymentMethod::findOrFail($id);
        $validated = $request->validate([
            'key' => 'required|string|unique:payment_methods,key,' . $id,
            'label' => 'required|string',
            'account_name' => 'required|string',
            'account_number' => 'required|string',
            'bank_name' => 'nullable|string',
        ]);
        $method->update($validated);
        return response()->json($method);
    }

    // Delete a payment method
    public function destroy($id)
    {
        $method = PaymentMethod::findOrFail($id);
        $method->delete();
        return response()->json(['message' => 'Payment method deleted']);
    }
}
