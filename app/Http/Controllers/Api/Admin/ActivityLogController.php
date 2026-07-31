<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function users(Request $request)
    {
        // TODO: In the original codebase, this queried Spatie ActivityLog models.
        // Assuming there is a service or we can use the Model directly here for admin lists.
        // For the scope of this migration, we'll return a stubbed response for now,
        // to be filled when Eloquent models are scaffolded.

        return response()->json([
            'success' => true,
            'message' => 'User activity logs retrieved.',
            'data' => ['logs' => []],
        ]);
    }

    public function list(Request $request)
    {
        // General activity log list.
        return response()->json([
            'success' => true,
            'message' => 'Activity logs retrieved.',
            'data' => ['logs' => []],
        ]);
    }
}
