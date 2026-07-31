<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class HealthController extends Controller
{
    /**
     * Legacy health check endpoint.
     * Returns a simple JSON status to indicate the API is up.
     */
    public function check()
    {
        return response()->json(['status' => 'ok']);
    }
}
