<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\InquiryService;
use Illuminate\Http\Request;

class InquiryLinkController extends Controller
{
    public function __construct(private readonly InquiryService $inquiryService) {}

    public function link(Request $request, string $inquiryId)
    {
        $userId = $request->input('userId');

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'userId is required.',
            ], 400);
        }

        try {
            $this->inquiryService->linkToUser($inquiryId, (int) $userId);

            return response()->json([
                'success' => true,
                'message' => 'Inquiry linked to user successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
