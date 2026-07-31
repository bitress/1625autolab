<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate a user and return a token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', strtolower($request->email))->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if (isset($user->is_active) && !$user->is_active) {
            return response()->json(['message' => 'This account has been deactivated. Please contact support.'], 403);
        }

        if (array_key_exists('email_verified_at', $user->getAttributes()) && $user->email_verified_at === null) {
            return response()->json(['message' => 'Please verify your email address before signing in.'], 403);
        }

        // Log the activity using Spatie
        if (function_exists('activity')) {
            activity('auth')
                ->performedOn($user)
                ->byUser($user)
                ->withProperties([
                    'email' => $user->email,
                    'role' => $user->role ?? 'client'
                ])
                ->log('user_logged_in');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'client',
            ]
        ]);
    }

    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
        ]);

        $phone = $this->normalizePhoneForStorage($request->phone ?? '');

        $user = User::create([
            'name' => $request->name,
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password), // configured to Argon2id in config
            'phone' => $phone,
            'role' => 'client',
        ]);

        if (function_exists('activity')) {
            activity('auth')
                ->performedOn($user)
                ->byUser($user)
                ->withProperties(['email' => $user->email])
                ->log('user_registered');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ], 201);
    }

    /**
     * Logout and revoke the token.
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // Revoke current token
            $user->currentAccessToken()->delete();

            if (function_exists('activity')) {
                activity('auth')
                    ->performedOn($user)
                    ->byUser($user)
                    ->withProperties(['role' => $user->role ?? 'client'])
                    ->log('user_logged_out');
            }
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    /**
     * Normalize PH mobile formats to local 11-digit form (09XXXXXXXXX).
     */
    private function normalizePhoneForStorage(string $phone): string
    {
        $trimmed = trim($phone);
        if ($trimmed === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $trimmed);
        if ($digits === null || $digits === '') {
            return $trimmed;
        }

        if (str_starts_with($digits, '63') && strlen($digits) === 12 && ($digits[2] ?? '') === '9') {
            return '0' . substr($digits, 2);
        }
        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0' . $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 11 && ($digits[1] ?? '') === '9') {
            return $digits;
        }

        return $trimmed;
    }
}
