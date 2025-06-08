<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DriverService;

class DriverController extends Controller
{
    protected $driverService;

    public function __construct(DriverService $driverService)
    {
        $this->driverService = $driverService;
    }

    public function index()
    {
        return response()->json($this->driverService->getAll());
    }

    public function show($id)
    {
        return response()->json($this->driverService->getById($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string',
        ]);
        $driver = $this->driverService->create($data);
        return response()->json($driver, 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string',
        ]);
        $driver = $this->driverService->update($id, $data);
        return response()->json($driver);
    }

    public function destroy($id)
    {
        $this->driverService->delete($id);
        return response()->json(['message' => 'Driver deleted successfully']);
    }
}
