<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Contact\SendContactRequest;
use App\Services\NotificationJobQueueService;
use App\Services\TurnstileService;

class ContactController extends Controller
{
    public function __construct(
        private readonly NotificationJobQueueService $jobQueue,
        private readonly TurnstileService $turnstile
    ) {}

    public function send(SendContactRequest $request)
    {
        $this->turnstile->validate($request->all());

        try {
            $data = $request->validated();

            // Dispatch notification job directly as it was done in Router.php
            $this->jobQueue->dispatch('contact_message_received', $data);

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully. We will get back to you shortly.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
