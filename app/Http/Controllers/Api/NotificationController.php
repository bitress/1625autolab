<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly UserNotificationService $notificationService) {}

    public function list(Request $request)
    {
        $payload = $request->user()->toArray();
        $isAdmin = in_array($payload['role'] ?? '', ['admin', 'owner'], true);

        $notifications = $this->notificationService->getForViewer($isAdmin, $request->user()->id);
        $unreadCount = $this->notificationService->getUnreadCount($isAdmin, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved.',
            'data' => [
                'notifications' => $notifications,
                'unreadCount' => $unreadCount,
            ],
        ]);
    }

    public function read(Request $request, int $id)
    {
        $payload = $request->user()->toArray();
        $isAdmin = in_array($payload['role'] ?? '', ['admin', 'owner'], true);

        try {
            $this->notificationService->markRead($id, $isAdmin, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function readAll(Request $request)
    {
        $payload = $request->user()->toArray();
        $isAdmin = in_array($payload['role'] ?? '', ['admin', 'owner'], true);

        try {
            $this->notificationService->markAllRead($isAdmin, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read.',
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
        $payload = $request->user()->toArray();
        $isAdmin = in_array($payload['role'] ?? '', ['admin', 'owner'], true);

        try {
            $this->notificationService->delete($id, $isAdmin, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
