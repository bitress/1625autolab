<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ManyChatController extends Controller
{
    public function menu(Request $request)
    {
        // TODO: implement logic that delegates to ManyChatService or similar.
        // For now, mirroring the exact signature of handleManyChatMenu()
        return response()->json([]);
    }

    public function drillDown(Request $request)
    {
        // TODO: implement logic that delegates to ManyChatService or similar.
        return response()->json([]);
    }
}
