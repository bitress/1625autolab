<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function create(Request $request)
    {
        try {
            $order = $this->orderService->create($request->all(), $request->user()?->id);

            return response()->json([
                'order' => $order,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function mine(Request $request)
    {
        $orders = $this->orderService->listMine($request->user()->id);

        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function get(Request $request, int $id)
    {
        $payload = $request->user() ? $request->user()->toArray() : [];
        $role = $payload['role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'owner', 'manager', 'staff'], true);

        try {
            // If admin, they can view any order (pass null for userId)
            // If not, they can only view their own
            $userId = $isAdmin ? null : ($request->user()?->id);
            $order = $this->orderService->getById($id, $userId, ! $isAdmin);

            return response()->json([
                'order' => $order,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], ((int) $e->getCode() >= 400 && (int) $e->getCode() <= 599) ? (int) $e->getCode() : 404);
        }
    }

    public function adminList(Request $request)
    {
        $filters = $request->except(['pageSize', 'page']);
        $pageSize = (int) $request->query('pageSize', '20');
        $page = (int) $request->query('page', '1');

        $result = $this->orderService->listAll($filters, $pageSize, $page);

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved.',
            'data' => $result, // typically contains 'orders' and 'pagination'
        ]);
    }

    public function adminStatusUpdate(Request $request, int $id)
    {
        try {
            $order = $this->orderService->updateStatus($id, (string) $request->input('status'));

            return response()->json([
                'order' => $order,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function adminTrackingUpdate(Request $request, int $id)
    {
        try {
            $order = $this->orderService->updateTracking(
                $id,
                (string) $request->input('courierName', ''),
                (string) $request->input('trackingNumber', '')
            );

            return response()->json([
                'order' => $order,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function adminPaymentUpdate(Request $request, int $id)
    {
        try {
            $order = $this->orderService->updatePaymentStatus($id, (string) $request->input('paymentStatus'));

            return response()->json([
                'order' => $order,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
