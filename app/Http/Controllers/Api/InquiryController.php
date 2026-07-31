<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inquiry\CreateInquiryRequest;
use App\Services\InquiryActivityService;
use App\Services\InquiryService;
use App\Services\TurnstileService;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function __construct(
        private readonly InquiryService $inquiryService,
        private readonly InquiryActivityService $activityService,
        private readonly TurnstileService $turnstile
    ) {}

    public function create(CreateInquiryRequest $request)
    {
        $this->turnstile->validate($request->all());

        try {
            $inquiry = $this->inquiryService->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Inquiry submitted successfully. We will get back to you shortly.',
                'data' => ['inquiry' => $inquiry],
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
        $inquiries = $this->inquiryService->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Inquiries retrieved.',
            'data' => ['inquiries' => $inquiries],
        ]);
    }

    public function get(Request $request, string $id)
    {
        $inquiry = $this->inquiryService->getById($id);

        if (! $inquiry) {
            return response()->json([
                'success' => false,
                'message' => 'Inquiry not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Inquiry retrieved.',
            'data' => ['inquiry' => $inquiry],
        ]);
    }

    public function update(Request $request, string $id)
    {
        try {
            $inquiry = $this->inquiryService->updateDetails(
                $id,
                $request->input('status'),
                $request->input('appointmentDate'),
                $request->input('appointmentTime'),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Inquiry updated successfully.',
                'data' => ['inquiry' => $inquiry],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $this->inquiryService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Inquiry deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        }
    }

    public function activity(Request $request, string $id)
    {
        try {
            $activities = $this->activityService->getForInquiry($id);

            return response()->json([
                'success' => true,
                'message' => 'Activity retrieved.',
                'data' => ['activities' => $activities],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
