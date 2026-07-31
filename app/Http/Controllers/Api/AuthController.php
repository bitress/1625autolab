<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Services\AuthSecurityService;
use App\Services\NotificationPreferencesService;
use App\Services\PrivacyService;
use App\Services\TurnstileService;
use App\Services\UserService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly AuthSecurityService $securityService,
        private readonly TurnstileService $turnstile,
        private readonly PrivacyService $privacyService,
        private readonly NotificationPreferencesService $notificationPrefs
    ) {}

    public function login(LoginRequest $request)
    {
        $this->turnstile->validate($request->all());

        try {
            $result = $this->userService->login($request->email, $request->password);

            $this->securityService->onLoginSuccess(
                $result['user'],
                $request->ip() ?? '',
                $request->userAgent() ?? ''
            );

            return response()->json([
                'success' => true,
                'message' => 'Logged in successfully.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->securityService->onLoginFailure($request->email, $request->ip() ?? '');

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 401);
        }
    }

    public function register(RegisterRequest $request)
    {
        $this->turnstile->validate($request->all());

        try {
            $result = $this->userService->register($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Registration successful.',
                'data' => $result,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }
    }

    public function logout(Request $request)
    {
        if ($user = $request->user()) {
            $user->currentAccessToken()->delete();
            // TODO: call AuthSecurityService->onLogout($user) if needed
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request)
    {
        // $request->user() is a Laravel model, but we convert to array for consistency with our services
        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved.',
            'data' => [
                'user' => $request->user()->toArray(),
            ],
        ]);
    }

    public function refresh(Request $request)
    {
        // Sanctum doesn't support refresh tokens out of the box like JWT does.
        // A common pattern is to just issue a new token and delete the old one.
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $newToken = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed.',
            'data' => [
                'token' => $newToken,
                'user' => $user->toArray(),
            ],
        ]);
    }

    public function profile(Request $request)
    {
        try {
            $updatedUser = $this->userService->updateProfile($request->user()->id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'data' => [
                    'user' => $updatedUser,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $this->turnstile->validate($request->all());

        $this->userService->sendPasswordResetLink($request->email);

        return response()->json([
            'success' => true,
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $this->turnstile->validate($request->all());

        $this->userService->resetPassword($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now log in.',
        ]);
    }

    public function verifyEmail(Request $request)
    {
        try {
            $user = $this->userService->verifyEmail($request->query('token', ''));

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully.',
                'data' => ['user' => $user],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function resendVerification(Request $request)
    {
        try {
            $this->userService->resendEmailVerification($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Verification email sent.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function dataExport(Request $request)
    {
        try {
            $data = $this->privacyService->exportData($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Data export complete.',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function accountDelete(Request $request)
    {
        try {
            $this->privacyService->deleteAccount($request->user()->id);
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function notificationPrefsGet(Request $request)
    {
        $prefs = $this->notificationPrefs->getForUser($request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Preferences retrieved.',
            'data' => ['preferences' => $prefs],
        ]);
    }

    public function notificationPrefsSave(Request $request)
    {
        $prefs = $this->notificationPrefs->save($request->user()->id, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Preferences saved.',
            'data' => ['preferences' => $prefs],
        ]);
    }
}
