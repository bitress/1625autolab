<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SiteSettingsService;
use Illuminate\Http\Request;

class SiteSettingsController extends Controller
{
    public function __construct(private readonly SiteSettingsService $siteSettingsService) {}

    public function get(Request $request)
    {
        $settings = $this->siteSettingsService->getAll();

        return response()->json([
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        try {
            $settings = $this->siteSettingsService->update($request->all());

            return response()->json([
                'settings' => $settings,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
