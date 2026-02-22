<?php

namespace App\Services;

use App\Models\Driver;

class DriverService
{
    public function getAll($perPage = 10, $search = null)
    {
        $query = Driver::query();
        if ($search) {
            $search = strtolower($search);
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%$search%"])
                  ->orWhereRaw('LOWER(phone) LIKE ?', ["%$search%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%$search%"])
                  ->orWhereRaw('LOWER(status) LIKE ?', ["%$search%"]);
            });
        }
        return $query->paginate($perPage);
    }

    public function getById($id)
    {
        return Driver::findOrFail($id);
    }

    public function create($data)
    {
        return Driver::create($data);
    }

    public function update($id, $data)
    {
        $driver = Driver::findOrFail($id);
        $driver->update($data);
        return $driver->fresh();
    }

    public function delete($id)
    {
        $driver = Driver::findOrFail($id);
        $driver->delete();
        return true;
    }
}
