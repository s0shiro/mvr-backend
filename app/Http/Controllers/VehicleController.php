<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\VehicleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class VehicleController extends Controller
{
    protected VehicleService $vehicleService;

    public function __construct(VehicleService $vehicleService)
    {
        $this->vehicleService = $vehicleService;
    }

    /**
     * Get cursor paginated list of vehicles
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'cursor' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:100',
            'type' => 'nullable|string|in:car,motorcycle',
            'status' => 'nullable|string|in:available,maintenance,rented',
            'search' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255'
        ]);

        $filters = $request->only(['type', 'status', 'search', 'name', 'brand']);
        $cursor = $request->input('cursor', 0);
        $limit = $request->input('limit', 10);

        $result = $this->vehicleService->getPaginatedVehicles($filters, $cursor, $limit);

        return response()->json([
            'status' => 'success',
            'data' => $result['data'],
            'meta' => [
                'nextCursor' => $result['nextCursor'],
                'totalCount' => $result['totalCount']
            ]
        ]);
    }

    /**
     * Store a new vehicle
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:car,motorcycle',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'plate_number' => 'required|string|unique:vehicles,plate_number|max:20',
            'capacity' => 'required|integer|min:1',
            'rental_rate' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'required|string|in:available,maintenance,rented'
        ]);

        $vehicle = $this->vehicleService->createVehicle($validatedData);

        return response()->json([
            'status' => 'success',
            'message' => 'Vehicle created successfully',
            'data' => $vehicle
        ], 201);
    }

    /**
     * Display the specified vehicle
     */
    public function show(int $id): JsonResponse
    {
        $vehicle = $this->vehicleService->getVehicleById($id);
        // Eager load images
        $vehicle->load(['images']);

        // Attach image_url to each image
        $images = $vehicle->images->map(function ($img) {
            return array_merge($img->toArray(), [
                'image_url' => $img->image_url,
            ]);
        });

        $vehicleArray = $vehicle->toArray();
        $vehicleArray['images'] = $images;

        return response()->json([
            'status' => 'success',
            'data' => $vehicleArray
        ]);
    }

    /**
     * Update the specified vehicle
     */
    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:car,motorcycle',
            'brand' => 'sometimes|string|max:100',
            'model' => 'sometimes|string|max:100',
            'year' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'plate_number' => 'sometimes|string|unique:vehicles,plate_number,' . $vehicle->id . '|max:20',
            'capacity' => 'sometimes|integer|min:1',
            'rental_rate' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|in:available,maintenance,rented'
        ]);

        $this->vehicleService->updateVehicle($vehicle, $validatedData);

        return response()->json([
            'status' => 'success',
            'message' => 'Vehicle updated successfully',
            'data' => $vehicle->fresh()
        ]);
    }

    /**
     * Remove the specified vehicle
     */
    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->vehicleService->deleteVehicle($vehicle);

        return response()->json([
            'status' => 'success',
            'message' => 'Vehicle deleted successfully'
        ]);
    }

    /**
     * Update vehicle status
     */
    public function updateStatus(Request $request, Vehicle $vehicle): JsonResponse
    {
        $validatedData = $request->validate([
            'status' => 'required|string|in:available,maintenance,rented'
        ]);

        $this->vehicleService->updateVehicleStatus($vehicle, $validatedData['status']);

        return response()->json([
            'status' => 'success',
            'message' => 'Vehicle status updated successfully',
            'data' => $vehicle->fresh()
        ]);
    }

    /**
     * Upload base64 images for a vehicle
     */
    public function uploadImages(Request $request, Vehicle $vehicle): JsonResponse
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => ['required', 'string', 'regex:/^data:image\/(jpeg|png|jpg|gif);base64,/']
        ]);

        $this->vehicleService->addVehicleImages($vehicle, $request->images);

        return response()->json([
            'status' => 'success',
            'message' => 'Images uploaded successfully',
            'data' => $vehicle->load(['images' => function($query) {
                $query->select('id', 'vehicle_id', 'mime_type', 'is_primary', 'sort_order');
            }])
        ]);
    }

    /**
     * Delete an image from a vehicle
     */
    public function deleteImage(Vehicle $vehicle, int $imageId): JsonResponse
    {
        $this->vehicleService->removeVehicleImage($vehicle, $imageId);

        return response()->json([
            'status' => 'success',
            'message' => 'Image deleted successfully'
        ]);
    }

    /**
     * Set primary image for a vehicle
     */
    public function setPrimaryImage(Vehicle $vehicle, int $imageId): JsonResponse
    {
        $this->vehicleService->setVehiclePrimaryImage($vehicle, $imageId);

        return response()->json([
            'status' => 'success',
            'message' => 'Primary image set successfully',
            'data' => $vehicle->load(['images' => function($query) {
                $query->select('id', 'vehicle_id', 'mime_type', 'is_primary', 'sort_order');
            }])
        ]);
    }

    /**
     * Get a specific image by ID
     */
    public function getImage(Vehicle $vehicle, int $imageId): JsonResponse
    {
        $image = $vehicle->images()->findOrFail($imageId);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'image_url' => $image->image_url
            ]
        ]);
    }

    /**
     * Reorder vehicle images
     */
    public function reorderImages(Request $request, Vehicle $vehicle): JsonResponse
    {
        $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'required|integer|exists:vehicle_images,id'
        ]);

        $this->vehicleService->reorderVehicleImages($vehicle, $request->image_ids);

        return response()->json([
            'status' => 'success',
            'message' => 'Images reordered successfully',
            'data' => $vehicle->load(['images' => function($query) {
                $query->select('id', 'vehicle_id', 'mime_type', 'is_primary', 'sort_order');
            }])
        ]);
    }
}
