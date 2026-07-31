<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function users(Request $request)
    {
        $sort = $request->query('sort', 'most_recent');
        $logs = $this->activityLogService->summarizeByUsers((string) $sort);

        return response()->json([
            'success' => true,
            'message' => 'User activity summary retrieved.',
            'data' => ['logs' => $logs],
        ]);
    }

    public function list(Request $request)
    {
        $userId = $request->query('user_id');
        $limit = (int) $request->query('limit', 200);

        if ($userId) {
            $logs = $this->activityLogService->listByCauserUser((int) $userId, $limit);
        } else {
            // General activity log list.
            $logs = $this->activityLogService->list($limit);
        }

        return response()->json([
            'success' => true,
            'message' => 'Activity logs retrieved.',
            'data' => ['logs' => $logs],
        ]);
    }
}
