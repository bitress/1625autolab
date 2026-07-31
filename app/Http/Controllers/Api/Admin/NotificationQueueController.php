<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\NotificationJobQueueService;
use Illuminate\Http\Request;

class NotificationQueueController extends Controller
{
    public function __construct(private readonly NotificationJobQueueService $jobQueue) {}

    public function list(Request $request)
    {
        $status = $request->query('status', 'failed');
        $limit = $request->query('limit') ? (int) $request->query('limit') : 50;

        $jobs = $this->jobQueue->listJobs($status, $limit);

        return response()->json([
            'jobs' => $jobs,
        ]);
    }

    public function health(Request $request)
    {
        $warnAfterSeconds = $request->query('warn_after_seconds') ? (int) $request->query('warn_after_seconds') : 300;

        $health = $this->jobQueue->getHealth($warnAfterSeconds);

        return response()->json([
            'health' => $health,
        ]);
    }

    public function replayFailed(Request $request)
    {
        $limit = $request->query('limit') ? (int) $request->query('limit') : 50;

        try {
            $result = $this->jobQueue->replayFailed(null, $limit);

            return response()->json([
                'success' => true,
                'message' => 'Failed jobs queued for replay.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function replayOne(Request $request, int $id)
    {
        try {
            $result = $this->jobQueue->replayFailed($id, 1);

            return response()->json([
                'success' => true,
                'message' => 'Job queued for replay.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
