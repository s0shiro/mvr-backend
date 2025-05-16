<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthService
{
    public function register(array $data)
    {
        $user = User::create([
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        return $user;
    }

    public function attemptLogin(array $credentials)
    {
        if (!$token = Auth::attempt($credentials)) {
            return false;
        }
        return $token;
    }

    public function generateTokens(User $user)
    {
        $accessToken = Auth::claims(['type' => 'access'])->setTTL(15)->login($user);
        $refreshToken = Auth::claims(['type' => 'refresh'])->setTTL(10080)->login($user);
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword)
    {
        if (!Hash::check($currentPassword, $user->password)) {
            return false;
        }
        $user->password = Hash::make($newPassword);
        $user->save();
        return true;
    }
}
