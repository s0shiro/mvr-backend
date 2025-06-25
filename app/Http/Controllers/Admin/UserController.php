<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Services\UserService;
use App\Services\NotificationService;

class UserController extends Controller
{
    // List users with cursor-based pagination
    protected $userService;
    protected $notificationService;

    public function __construct(UserService $userService, NotificationService $notificationService)
    {
        $this->userService = $userService;
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $query = User::with('roles');
        
        $filters = $request->only(['role', 'search']);
        \Log::info('User filters received:', $filters);  // Debug log
        
        $query = $this->userService->getFilteredUsers($query, $filters);

        $users = $query->cursorPaginate($perPage, ['*'], 'cursor', $request->get('cursor'));

        // Format response with nextCursor
        return response()->json([
            'data' => $users->items(),
            'nextCursor' => $users->nextCursor()?->encode(),
            'prevCursor' => $users->previousCursor()?->encode(),
        ]);
    }

    // Show user details
    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        return response()->json($user);
    }

    // Create a new user
    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:users,username',
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:admin,manager', // Only allow admin and manager
        ]);

        // Generate verification code and expiry
        $verificationCode = random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);

        $user = User::create([
            'username' => $validated['username'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'address' => $request->input('address', ''), // Provide default empty string if not set
            'password' => Hash::make($validated['password']),
            'verification_code' => $verificationCode,
            'verification_code_expires_at' => $expiresAt,
        ]);
        $user->assignRole($validated['role']);

        // Use NotificationService to send verification email
        $this->notificationService->notifyUser(
            $user->id,
            'user_verification',
            $user,
            [
                'message' => "Your verification code is: $verificationCode",
                'verification_code' => $verificationCode,
                'expires_at' => $expiresAt,
            ]
        );

        return response()->json($user, 201);
    }

    // Update user
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $validated = $request->validate([
            'username' => 'sometimes|string|unique:users,username,' . $user->id,
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:6',
            'role' => 'sometimes|string|exists:roles,name',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }
        $user->update($validated);
        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }
        return response()->json($user);
    }

    // Delete user
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }
}
