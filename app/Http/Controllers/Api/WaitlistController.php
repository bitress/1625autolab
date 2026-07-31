<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TurnstileService;
use App\Services\WaitlistService;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function __construct(
        private readonly WaitlistService $waitlistService,
        private readonly TurnstileService $turnstile
    ) {}

    public function join(Request $request)
    {
        $this->turnstile->validate($request->all());

        try {
            $entry = $this->waitlistService->join($request->all());

            return response()->json([
                'entry' => $entry,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function list(Request $request)
    {
        $status = $request->query('status', '');
        $entries = $this->waitlistService->getAll((string) $status);

        return response()->json([
            'entries' => $entries,
        ]);
    }

    public function remove(Request $request, int $id)
    {
        // Null requestUser if admin/owner
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'owner'], true);

        $requestUserId = $isAdmin ? null : ($request->user()?->id);

        try {
            $this->waitlistService->remove($id, $requestUserId);

            return response()->json([
                'success' => true,
                'message' => 'Removed from waitlist.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function claimGet(Request $request)
    {
        $token = $request->query('token', '');
        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Token is required.',
            ], 400);
        }

        try {
            $claim = $this->waitlistService->getClaimByToken((string) $token);

            return response()->json([
                'claim' => $claim,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
