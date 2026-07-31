<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    public function daily(Request $request)
    {
        // Equivalent to $this->cronService->runDaily()
        // Here we can dispatch Laravel Commands or call jobs
        // Since we are migrating, we can call a Command if created, or log it.
        // As requested by user, we might use Artisan::call()
        try {
            Artisan::call('app:cron-daily');
            $output = Artisan::output();

            return response()->json([
                'output' => trim($output),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function processQueue(Request $request)
    {
        try {
            // Usually queue is processed by a long-running worker.
            // If we need a web hook to process one batch, we can call:
            Artisan::call('queue:work', ['--stop-when-empty' => true]);
            $output = Artisan::output();

            return response()->json([
                'output' => trim($output),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function notificationQueue(Request $request)
    {
        try {
            Artisan::call('app:cron-notification-queue');
            $output = Artisan::output();

            return response()->json([
                'output' => trim($output),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function waitlistAutofill(Request $request)
    {
        try {
            Artisan::call('app:cron-waitlist-autofill');
            $output = Artisan::output();

            return response()->json([
                'output' => trim($output),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function appointmentReminders(Request $request)
    {
        try {
            Artisan::call('app:cron-appointment-reminders');
            $output = Artisan::output();

            return response()->json([
                'output' => trim($output),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
