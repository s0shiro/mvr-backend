<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\VehicleMaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleMaintenanceController extends Controller
{
    public function __construct(private readonly VehicleMaintenanceService $vehicleMaintenanceService)
    {
    }

    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $filters = array_filter(
            $validated,
            fn ($value, $key) => in_array($key, ['search', 'date_from', 'date_to'], true) && $value !== null,
            ARRAY_FILTER_USE_BOTH
        );

        $history = $this->vehicleMaintenanceService->listMaintenance($vehicle, $filters, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => $history->items(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
                'last_page' => $history->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $validated = $request->validate([
            'maintenance_date' => 'required|date',
            'maintenance_type' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $maintenance = $this->vehicleMaintenanceService->createMaintenance($vehicle, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Maintenance record added successfully',
            'data' => $maintenance,
        ], 201);
    }
}
