<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Services\AuthService;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    protected function getCookieSettings()
    {
        $isProduction = app()->environment('production');
        
        if ($isProduction) {
            $domain = parse_url(config('app.url'), PHP_URL_HOST);
            // Remove 'www.' if present
            $domain = preg_replace('/^www\./i', '', $domain);
            
            // Extract the main domain
            $parts = explode('.', $domain);
            if (count($parts) > 2) {
                array_shift($parts); // Remove subdomain
                $domain = '.' . implode('.', $parts); // Prepend with dot for cross-subdomain support
            } else {
                $domain = '.' . $domain;
            }
            
            return [
                'path' => '/',
                'domain' => null, // Let the browser handle the domain
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None', // Must be 'None' for cross-site requests in production
            ];
        }

        // Development settings
        return [
            'path' => '/',
            'domain' => null,     // null works for localhost
            'secure' => false,    // false for http in development
            'httponly' => true,   // keep cookies http-only
            'samesite' => 'Lax', // Lax is fine for development
        ];
    }

    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'name' => 'required|string|max:255',
            'address' => 'string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $this->authService->register($request->only(['username', 'name', 'address', 'email', 'password']));
        $tokens = $this->authService->generateTokens($user);
        $cookieSettings = $this->getCookieSettings();

        $accessCookie = cookie(
            'access_token',
            $tokens['access_token'],
            15,
            $cookieSettings['path'],
            $cookieSettings['domain'],
            $cookieSettings['secure'],
            $cookieSettings['httponly'],
            false,
            $cookieSettings['samesite']
        );
        $refreshCookie = cookie(
            'refresh_token',
            $tokens['refresh_token'],
            10080,
            $cookieSettings['path'],
            $cookieSettings['domain'],
            $cookieSettings['secure'],
            $cookieSettings['httponly'],
            false,
            $cookieSettings['samesite']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            'user' => $user,
            'authorization' => [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'type' => 'bearer',
            ],
        ])->withCookie($accessCookie)->withCookie($refreshCookie);
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ], [
            'login.required' => 'Please provide your username or email address.',
            'login.string' => 'Login must be a text value.',
        ]);

        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [
            $loginField => $request->login,
            'password' => $request->password
        ];

        $token = $this->authService->attemptLogin($credentials);
        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();
        $tokens = $this->authService->generateTokens($user);
        $cookieSettings = $this->getCookieSettings();

        $accessCookie = cookie(
            'access_token',
            $tokens['access_token'],
            15,
            $cookieSettings['path'],
            $cookieSettings['domain'],
            $cookieSettings['secure'],
            $cookieSettings['httponly'],
            false,
            $cookieSettings['samesite']
        );
        $refreshCookie = cookie(
            'refresh_token',
            $tokens['refresh_token'],
            10080,
            $cookieSettings['path'],
            $cookieSettings['domain'],
            $cookieSettings['secure'],
            $cookieSettings['httponly'],
            false,
            $cookieSettings['samesite']
        );

        return response()->json([
            'status' => 'success',
            'user' => $user,
            'authorization' => [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'type' => 'bearer',
            ],
        ])->withCookie($accessCookie)->withCookie($refreshCookie);
    }

    public function logout()
    {
        Auth::logout();
        $cookieSettings = $this->getCookieSettings();
        $accessCookie = cookie(
            'access_token', '', -1,
            $cookieSettings['path'], $cookieSettings['domain'],
            $cookieSettings['secure'], $cookieSettings['httponly'], false, $cookieSettings['samesite']
        );
        $refreshCookie = cookie(
            'refresh_token', '', -1,
            $cookieSettings['path'], $cookieSettings['domain'],
            $cookieSettings['secure'], $cookieSettings['httponly'], false, $cookieSettings['samesite']
        );
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out',
        ])->withCookie($accessCookie)->withCookie($refreshCookie);
    }

    public function refresh(Request $request)
    {
        try {
            $refreshToken = $request->cookie('refresh_token');
            if (!$refreshToken) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Refresh token not found',
                ], 401);
            }
            Auth::setToken($refreshToken);
            $claims = Auth::getPayload();
            if ($claims->get('type') !== 'refresh') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid refresh token',
                ], 401);
            }
            $newAccessToken = Auth::claims(['type' => 'access'])->setTTL(15)->tokenById(Auth::id());
            $cookieSettings = $this->getCookieSettings();
            $accessCookie = cookie(
                'access_token', $newAccessToken, 15,
                $cookieSettings['path'], $cookieSettings['domain'],
                $cookieSettings['secure'], $cookieSettings['httponly'], false, $cookieSettings['samesite']
            );
            return response()->json([
                'status' => 'success',
                'message' => 'Token refreshed successfully',
                'authorization' => [
                    'access_token' => $newAccessToken,
                    'type' => 'bearer',
                ],
            ])->withCookie($accessCookie);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid refresh token',
            ], 401);
        }
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
        $user = $request->user();
        $changed = $this->authService->changePassword($user, $request->current_password, $request->new_password);
        if (!$changed) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect',
            ], 400);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully',
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
        ]);
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
        }
        if ($user->email_verified_at) {
            return response()->json(['status' => 'error', 'message' => 'Email already verified'], 400);
        }
        if ($user->verification_code !== $request->code) {
            return response()->json(['status' => 'error', 'message' => 'Invalid verification code'], 400);
        }
        if (now()->greaterThan($user->verification_code_expires_at)) {
            return response()->json(['status' => 'error', 'message' => 'Verification code expired'], 400);
        }
        $user->email_verified_at = now();
        $user->verification_code = null;
        $user->verification_code_expires_at = null;
        $user->save();
        return response()->json(['status' => 'success', 'message' => 'Email verified successfully']);
    }

    public function resendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
        }
        if ($user->email_verified_at) {
            return response()->json(['status' => 'error', 'message' => 'Email already verified'], 400);
        }
        $verificationCode = random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);
        $user->verification_code = $verificationCode;
        $user->verification_code_expires_at = $expiresAt;
        $user->save();
        \Mail::to($user->email)->send(new \App\Mail\UserNotificationMail(
            'Your Verification Code',
            "Your verification code is: $verificationCode"
        ));
        return response()->json(['status' => 'success', 'message' => 'Verification code resent']);
    }
}