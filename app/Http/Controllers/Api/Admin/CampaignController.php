<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\MarketingCampaignService;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(private readonly MarketingCampaignService $campaignService) {}

    public function list(Request $request)
    {
        $campaigns = $this->campaignService->listCampaigns();

        return response()->json([
            'success' => true,
            'message' => 'Campaigns retrieved.',
            'data' => ['campaigns' => $campaigns],
        ]);
    }

    public function get(Request $request, int $id)
    {
        try {
            $campaign = $this->campaignService->getCampaign($id);

            return response()->json([
                'success' => true,
                'message' => 'Campaign retrieved.',
                'data' => ['campaign' => $campaign],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 404);
        }
    }

    public function create(Request $request)
    {
        try {
            $campaign = $this->campaignService->createCampaign(
                $request->all(),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Campaign created.',
                'data' => ['campaign' => $campaign],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $campaign = $this->campaignService->updateCampaign($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Campaign updated.',
                'data' => ['campaign' => $campaign],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function delete(Request $request, int $id)
    {
        try {
            $this->campaignService->deleteCampaign($id);

            return response()->json([
                'success' => true,
                'message' => 'Campaign deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function run(Request $request, int $id)
    {
        try {
            $result = $this->campaignService->runCampaign($id, false);

            return response()->json([
                'success' => true,
                'message' => 'Campaign dispatched successfully.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function dryRun(Request $request, int $id)
    {
        try {
            $result = $this->campaignService->runCampaign($id, true);

            return response()->json([
                'success' => true,
                'message' => 'Campaign dry run complete.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function analytics(Request $request, int $id)
    {
        try {
            $analytics = $this->campaignService->analytics($id);

            return response()->json([
                'success' => true,
                'message' => 'Campaign analytics retrieved.',
                'data' => ['analytics' => $analytics],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function audience(Request $request)
    {
        $type = $request->query('type', 'all');

        try {
            $audience = $this->campaignService->getAudience($type);

            return response()->json([
                'success' => true,
                'message' => 'Audience retrieved.',
                'data' => ['audience' => $audience],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
