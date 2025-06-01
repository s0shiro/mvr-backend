<?php

namespace App\Http\Controllers;

use App\Models\BusinessSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessSaleController extends Controller
{
    // List all sales/notes for a business
    public function index($businessId)
    {
        $sales = BusinessSale::where('business_id', $businessId)->orderBy('date', 'desc')->get();
        return response()->json($sales);
    }

    // Store a new sale/note
    public function store(Request $request, $businessId)
    {
        $request->validate([
            'date' => 'nullable|date',
            'amount' => 'nullable|numeric',
            'type' => 'required|string',
            'note' => 'nullable|string',
        ]);

        $sale = BusinessSale::create([
            'business_id' => $businessId,
            'date' => $request->date,
            'amount' => $request->amount,
            'type' => $request->type,
            'note' => $request->note,
            'created_by' => Auth::id(),
        ]);

        return response()->json($sale, 201);
    }

    // Show a single sale/note
    public function show($businessId, $id)
    {
        $sale = BusinessSale::where('business_id', $businessId)->findOrFail($id);
        return response()->json($sale);
    }

    // Update a sale/note
    public function update(Request $request, $businessId, $id)
    {
        $sale = BusinessSale::where('business_id', $businessId)->findOrFail($id);
        $request->validate([
            'date' => 'nullable|date',
            'amount' => 'nullable|numeric',
            'type' => 'required|string',
            'note' => 'nullable|string',
        ]);
        $sale->update($request->only(['date', 'amount', 'type', 'note']));
        return response()->json($sale);
    }

    // Delete a sale/note
    public function destroy($businessId, $id)
    {
        $sale = BusinessSale::where('business_id', $businessId)->findOrFail($id);
        $sale->delete();
        return response()->json(['message' => 'Sale/note deleted']);
    }
}
