<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessController extends Controller
{
    // List all businesses (admin only)
    public function index()
    {
        $businesses = Business::all();
        return response()->json($businesses);
    }

    // Store a new business (admin only)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:resort,photography',
            'description' => 'nullable|string',
        ]);

        $business = Business::create([
            'name' => $request->name,
            'type' => $request->type,
            'description' => $request->description,
            'created_by' => Auth::id(),
        ]);

        return response()->json($business, 201);
    }

    // Show a single business
    public function show($id)
    {
        $business = Business::findOrFail($id);
        return response()->json($business);
    }

    // Update a business (admin only)
    public function update(Request $request, $id)
    {
        $business = Business::findOrFail($id);
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:resort,photography',
            'description' => 'nullable|string',
        ]);
        $business->update($request->only(['name', 'type', 'description']));
        return response()->json($business);
    }

    // Delete a business (admin only)
    public function destroy($id)
    {
        $business = Business::findOrFail($id);
        $business->delete();
        return response()->json(['message' => 'Business deleted']);
    }
}
