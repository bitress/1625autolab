<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SemaphoreService;
use Illuminate\Http\Request;

class SemaphoreController extends Controller
{
    public function __construct(private readonly SemaphoreService $semaphoreService) {}

    public function account(Request $request)
    {
        $refresh = filter_var($request->query('refresh', 'false'), FILTER_VALIDATE_BOOLEAN);

        try {
            $account = $this->semaphoreService->getAccount($refresh);

            return response()->json([
                'success' => true,
                'message' => 'Semaphore account details retrieved.',
                'data' => ['account' => $account],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function messages(Request $request)
    {
        $refresh = filter_var($request->query('refresh', 'false'), FILTER_VALIDATE_BOOLEAN);
        $filters = $request->except(['refresh']);

        try {
            $messages = $this->semaphoreService->getMessages($filters, $refresh);

            return response()->json([
                'success' => true,
                'message' => 'Semaphore messages retrieved.',
                'data' => ['messages' => $messages],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
