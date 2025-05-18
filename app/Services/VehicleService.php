<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class VehicleService
{
    /**
     * Get cursor paginated list of vehicles
     *
     * @param array $filters Array of filters to apply
     * @param int $cursor Starting position for pagination
     * @param int $limit Number of items per page
     * @return array
     */
    public function getPaginatedVehicles(array $filters = [], int $cursor = 0, int $limit = 10): array
    {
        $query = Vehicle::query();

        // Apply filters
        if (isset($filters['type']) && !empty($filters['type'])) {
            $query->whereRaw('LOWER(type) = ?', [strtolower($filters['type'])]);
        }
        if (isset($filters['status']) && !empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['brand']) && !empty($filters['brand'])) {
            $query->whereRaw('LOWER(brand) LIKE ?', ['%' . strtolower($filters['brand']) . '%']);
        }
        if (isset($filters['name']) && !empty($filters['name'])) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($filters['name']) . '%']);
        }
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . $search . '%'])
                  ->orWhereRaw('LOWER(brand) LIKE ?', ['%' . $search . '%'])
                  ->orWhereRaw('LOWER(model) LIKE ?', ['%' . $search . '%'])
                  ->orWhereRaw('LOWER(plate_number) LIKE ?', ['%' . $search . '%']);
            });
        }

        // Get total count before pagination
        $totalCount = $query->count();

        // Get one extra item to determine if there's a next page
        $vehicles = $query->orderBy('id', 'desc')
            ->skip($cursor)
            ->take($limit + 1)
            ->get();

        $nextCursor = $vehicles->count() > $limit ? $cursor + $limit : null;

        return [
            'data' => $vehicles->take($limit),
            'nextCursor' => $nextCursor,
            'totalCount' => $totalCount
        ];
    }

    /**
     * Create a new vehicle
     */
    public function createVehicle(array $data): Vehicle
    {
        return Vehicle::create($data);
    }

    /**
     * Update a vehicle
     */
    public function updateVehicle(Vehicle $vehicle, array $data): bool
    {
        return $vehicle->update($data);
    }

    /**
     * Delete a vehicle (soft delete)
     */
    public function deleteVehicle(Vehicle $vehicle): bool
    {
        return $vehicle->delete();
    }

    /**
     * Get vehicle by ID
     */
    public function getVehicleById(int $id): ?Vehicle
    {
        return Vehicle::findOrFail($id);
    }

    /**
     * Change vehicle status
     */
    public function updateVehicleStatus(Vehicle $vehicle, string $status): bool
    {
        return $vehicle->update(['status' => $status]);
    }

    /**
     * Add base64 images to a vehicle
     */
    public function addVehicleImages(Vehicle $vehicle, array $images, bool $setPrimary = true): void
    {
        foreach ($images as $index => $imageData) {
            // Get mime type and base64 data from data URL
            if (preg_match('/^data:(.*?);base64,(.*)$/', $imageData, $matches)) {
                $vehicle->images()->create([
                    'mime_type' => $matches[1],
                    'image_data' => $matches[2],
                    'is_primary' => $setPrimary && $index === 0,
                    'sort_order' => $index
                ]);
            }
        }
    }

    /**
     * Remove an image from a vehicle
     */
    public function removeVehicleImage(Vehicle $vehicle, int $imageId): bool
    {
        return $vehicle->images()->where('id', $imageId)->delete();
    }

    /**
     * Set an image as primary
     */
    public function setVehiclePrimaryImage(Vehicle $vehicle, int $imageId): bool
    {
        $vehicle->images()->update(['is_primary' => false]);
        return $vehicle->images()->findOrFail($imageId)->update(['is_primary' => true]);
    }

    /**
     * Reorder vehicle images
     */
    public function reorderVehicleImages(Vehicle $vehicle, array $imageIds): bool
    {
        foreach ($imageIds as $index => $imageId) {
            $vehicle->images()->where('id', $imageId)->update(['sort_order' => $index]);
        }
        return true;
    }
}
