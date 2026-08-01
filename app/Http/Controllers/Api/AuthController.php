<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\AuthSecurityService;
use App\Services\NotificationPreferencesService;
use App\Services\PrivacyService;
use App\Services\TurnstileService;
use App\Services\UploadStorageService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly AuthSecurityService $securityService,
        private readonly TurnstileService $turnstile,
        private readonly PrivacyService $privacyService,
        private readonly NotificationPreferencesService $notificationPrefs,
        private readonly UploadStorageService $uploadService
    ) {}

    public function login(LoginRequest $request)
    {
        $this->turnstile->validate($request->all());

        try {
            $result = $this->userService->login($request->email, $request->password);
            $token = (string) ($result['token'] ?? '');
            $userId = (int) ($result['user']['id'] ?? 0);

            if ($token !== '' && $userId > 0) {
                $this->securityService->createSession(
                    $userId,
                    $token,
                    Carbon::now()->addDays(30)->timestamp,
                    $request->ip() ?? '',
                    $request->userAgent() ?? ''
                );
            }

            $this->securityService->recordLoginAttempt(
                $request->email,
                true,
                $result['user']['id'] ?? null,
                $request->ip() ?? '',
                $request->userAgent() ?? '',
                'Login successful.'
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            $this->securityService->recordLoginAttempt(
                $request->email,
                false,
                null,
                $request->ip() ?? '',
                $request->userAgent() ?? '',
                $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 401);
        }
    }

    public function register(RegisterRequest $request)
    {
        $this->turnstile->validate($request->all());

        try {
            $result = $this->userService->register($request->validated());

            return response()->json($result, 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 400);
        }
    }

    public function logout(Request $request)
    {
        $token = $request->bearerToken();

        if ($user = $request->user()) {
            $user->currentAccessToken()->delete();
            if ($token) {
                $this->securityService->endSessionByToken($token);
            }
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
            'user' => $request->user()->toArray(),
        ]);
    }

    public function refresh(Request $request)
    {
        // Sanctum doesn't support refresh tokens out of the box like JWT does.
        // A common pattern is to just issue a new token and delete the old one.
        $user = $request->user();
        $oldToken = $request->bearerToken();
        $user->currentAccessToken()->delete();
        $newToken = $user->createToken('auth_token')->plainTextToken;

        if ($oldToken) {
            $this->securityService->endSessionByToken($oldToken, 'refresh');
        }

        $this->securityService->createSession(
            (int) $user->id,
            $newToken,
            Carbon::now()->addDays(30)->timestamp,
            $request->ip() ?? '',
            $request->userAgent() ?? ''
        );

        return response()->json([
            'token' => $newToken,
            'refresh_token' => $newToken,
            'user' => $user->toArray(),
        ]);
    }

    public function profile(Request $request)
    {
        try {
            $updatedUser = $this->userService->updateProfile($request->user()->id, $request->all());

            return response()->json([
                'user' => $updatedUser,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 400);
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
                'message' => 'Email verified successfully. You can now sign in.',
                'user' => $user,
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
            if ($request->user()) {
                $this->userService->resendEmailVerification((int) $request->user()->id);
            } else {
                $email = strtolower(trim((string) $request->input('email', '')));
                if ($email === '') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email is required.',
                    ], 422);
                }

                $user = User::where('email', $email)->first();
                if ($user) {
                    $this->userService->resendEmailVerification((int) $user->id);
                }
            }

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

    public function avatarUpload(Request $request)
    {
        if (! $request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'No file provided.',
            ], 422);
        }

        $file = $request->file('file');

        try {
            UploadStorageService::assertImageFile($file, ['image/jpeg', 'image/png', 'image/webp'], 5);
            $url = $this->uploadService->upload($file, 'avatars/');

            return response()->json([
                'url' => $url,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 400);
        }
    }

    public function sessionList(Request $request)
    {
        $token = $request->bearerToken();

        return response()->json([
            'sessions' => $this->securityService->listSessions(
                (int) $request->user()->id,
                $token ? hash('sha256', $token) : null
            ),
        ]);
    }

    public function sessionRevoke(Request $request, int $id)
    {
        $revoked = $this->securityService->revokeSessionById((int) $request->user()->id, $id);

        return response()->json([
            'ok' => $revoked,
        ], $revoked ? 200 : 404);
    }

    public function sessionRevokeOthers(Request $request)
    {
        $token = $request->bearerToken();
        $revoked = $token
            ? $this->securityService->revokeOtherSessions((int) $request->user()->id, hash('sha256', $token))
            : 0;

        return response()->json([
            'revoked' => $revoked,
        ]);
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
            'preferences' => $prefs,
        ]);
    }

    public function notificationPrefsSave(Request $request)
    {
        $prefs = $this->notificationPrefs->save($request->user()->id, $request->all());

        return response()->json([
            'preferences' => $prefs,
        ]);
    }
}
