<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use Illuminate\Pagination\LengthAwarePaginator;

class VehicleMaintenanceService
{
    public function listMaintenance(Vehicle $vehicle, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->buildMaintenanceQuery($vehicle, $filters);

        return $query->paginate($perPage);
    }

    public function createMaintenance(Vehicle $vehicle, array $data): VehicleMaintenance
    {
        return $vehicle->maintenances()->create($data);
    }

    public function calculateTotalMaintenanceCost(Vehicle $vehicle, array $filters = []): float
    {
        $query = $this->buildMaintenanceQuery($vehicle, $filters);

        return (float) $query->sum('amount');
    }

    private function buildMaintenanceQuery(Vehicle $vehicle, array $filters = [])
    {
        $query = $vehicle->maintenances();

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(maintenance_type) LIKE ?', ['%' . $search . '%'])
                    ->orWhereRaw('LOWER(note) LIKE ?', ['%' . $search . '%']);
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('maintenance_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('maintenance_date', '<=', $filters['date_to']);
        }

        return $query;
    }
}
