<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FaqService;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function __construct(private readonly FaqService $faqService) {}

    public function list(Request $request)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $activeOnly = ! in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        $faqs = $this->faqService->getAll($activeOnly);

        return response()->json([
            'success' => true,
            'message' => 'FAQs retrieved.',
            'data' => ['faqs' => $faqs],
        ]);
    }

    public function create(Request $request)
    {
        try {
            $faq = $this->faqService->create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'FAQ created.',
                'data' => ['faq' => $faq],
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
            $faq = $this->faqService->update($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'FAQ updated.',
                'data' => ['faq' => $faq],
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
            $this->faqService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'FAQ deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
