<?php

namespace App\Services;

use App\Models\Driver;

class DriverService
{
    public function getAll()
    {
        return Driver::all();
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
        return $driver;
    }

    public function delete($id)
    {
        $driver = Driver::findOrFail($id);
        $driver->delete();
        return true;
    }
}
