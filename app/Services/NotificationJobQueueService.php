<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lightweight DB-backed queue for notification side effects (email/SMS/in-app).
 *
 * Queue mode is enabled via config('app.notification_queue_enabled', true).
 * If queueing is unavailable, events are executed inline as a safe fallback.
 */
class NotificationJobQueueService
{
    private bool $queueEnabled;

    public function __construct()
    {
        $this->queueEnabled = config('app.notification_queue_enabled', true);
    }

    /** @param array<string, mixed> $payload */
    public function dispatch(string $event, array $payload, ?string $runAfter = null): void
    {
        if (! $this->queueEnabled) {
            if ($runAfter !== null && strtotime($runAfter) > time()) {
                return;
            }
            $this->handleNow($event, $payload);

            return;
        }

        DB::table('notification_jobs')->insert([
            'event' => $event,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'queued',
            'run_after' => $runAfter ?? Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        if (PHP_SAPI !== 'cli' && ($runAfter === null || strtotime($runAfter) <= time())) {
            try {
                $this->processPending(1);
            } catch (\Throwable $e) {
                error_log('[NotificationJobQueueService] Immediate queue processing failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Calculate 3-hour prior run_after timestamp for an appointment date and time.
     */
    public static function calculateReminderRunAfter(string $dateStr, string $timeStr, int $hoursPrior = 3): ?string
    {
        $dateStr = trim($dateStr);
        $timeStr = trim($timeStr);
        if ($dateStr === '' || $timeStr === '') {
            return null;
        }

        try {
            $dateTimeStr = $dateStr.' '.$timeStr;
            $dt = new DateTimeImmutable($dateTimeStr, new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila'));
            $reminderDt = $dt->modify("-{$hoursPrior} hours");

            $runAfter = $reminderDt->format('Y-m-d H:i:s');
            if (strtotime($runAfter) > time()) {
                return $runAfter;
            }
        } catch (\Throwable $e) {
            error_log('[NotificationJobQueueService] Failed to parse appointment datetime: '.$e->getMessage());
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    public function dispatchNow(string $event, array $payload): void
    {
        $this->handleNow($event, $payload);
    }

    public function processPending(?int $limit = null): array
    {
        $batch = $limit ?? config('app.notification_queue_batch_size', 25);
        if ($batch < 1) {
            $batch = 1;
        }

        $stats = ['processed' => 0, 'failed' => 0, 'retried' => 0];

        $jobs = DB::table('notification_jobs')
            ->whereIn('status', ['queued', 'retry'])
            ->where('run_after', '<=', Carbon::now())
            ->orderBy('id', 'asc')
            ->limit($batch)
            ->get();

        foreach ($jobs as $job) {
            $jobId = (int) ($job->id ?? 0);
            if ($jobId <= 0) {
                continue;
            }

            $affected = DB::table('notification_jobs')
                ->where('id', $jobId)
                ->whereIn('status', ['queued', 'retry'])
                ->update([
                    'status' => 'processing',
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => Carbon::now(),
                ]);

            if ($affected === 0) {
                continue;
            }

            $attempts = ((int) ($job->attempts ?? 0)) + 1;
            $maxAttempts = max(1, (int) ($job->max_attempts ?? 5));
            $event = (string) ($job->event ?? '');
            $payload = json_decode((string) ($job->payload ?? '{}'), true);
            if (! is_array($payload)) {
                $payload = [];
            }

            try {
                $this->handleNow($event, $payload);

                DB::table('notification_jobs')
                    ->where('id', $jobId)
                    ->update([
                        'status' => 'done',
                        'processed_at' => Carbon::now(),
                        'last_error' => null,
                        'updated_at' => Carbon::now(),
                    ]);

                $stats['processed']++;
            } catch (\Throwable $e) {
                if ($attempts >= $maxAttempts) {
                    DB::table('notification_jobs')
                        ->where('id', $jobId)
                        ->update([
                            'status' => 'failed',
                            'last_error' => mb_substr($e->getMessage(), 0, 2000),
                            'updated_at' => Carbon::now(),
                        ]);

                    if ($event === 'marketing_campaign_message') {
                        $this->markCampaignRecipientStatus($payload, 'failed', mb_substr($e->getMessage(), 0, 1000));
                    }
                    $stats['failed']++;
                } else {
                    $retryDelay = max(15, config('app.notification_queue_retry_delay_seconds', 60));

                    DB::table('notification_jobs')
                        ->where('id', $jobId)
                        ->update([
                            'status' => 'retry',
                            'run_after' => Carbon::now()->addSeconds($retryDelay),
                            'last_error' => mb_substr($e->getMessage(), 0, 2000),
                            'updated_at' => Carbon::now(),
                        ]);

                    $stats['retried']++;
                }
            }
        }

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listJobs(string $status = '', int $limit = 100): array
    {
        $safeLimit = max(1, min(500, $limit));
        $allowed = ['queued', 'processing', 'retry', 'done', 'failed'];

        $query = DB::table('notification_jobs')->orderBy('id', 'desc')->limit($safeLimit);

        if ($status !== '' && in_array($status, $allowed, true)) {
            $query->where('status', $status);
        }

        $rows = $query->get();

        return $rows->map(function ($row) {
            $payload = json_decode((string) ($row->payload ?? '{}'), true);
            if (! is_array($payload)) {
                $payload = [];
            }

            return [
                'id' => (int) ($row->id ?? 0),
                'event' => (string) ($row->event ?? ''),
                'status' => (string) ($row->status ?? ''),
                'attempts' => (int) ($row->attempts ?? 0),
                'maxAttempts' => (int) ($row->max_attempts ?? 0),
                'runAfter' => (string) ($row->run_after ?? ''),
                'lastError' => isset($row->last_error) ? (string) $row->last_error : null,
                'createdAt' => (string) ($row->created_at ?? ''),
                'updatedAt' => (string) ($row->updated_at ?? ''),
                'processedAt' => isset($row->processed_at) ? (string) $row->processed_at : null,
                'payload' => $payload,
            ];
        })->toArray();
    }

    /** @return array<string, mixed> */
    public function getSummary(): array
    {
        $counts = ['queued' => 0, 'processing' => 0, 'retry' => 0, 'failed' => 0, 'done' => 0];

        $statusCounts = DB::table('notification_jobs')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->get();

        foreach ($statusCounts as $row) {
            $status = (string) ($row->status ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) ($row->cnt ?? 0);
            }
        }

        $lastProcessedAt = DB::table('notification_jobs')->max('processed_at');

        $oldestPendingAt = DB::table('notification_jobs')
            ->whereIn('status', ['queued', 'retry', 'processing'])
            ->min('created_at');

        $lastFailure = DB::table('notification_jobs')
            ->select('id', 'event', 'last_error', 'updated_at')
            ->where('status', 'failed')
            ->orderBy('updated_at', 'desc')
            ->first();

        return [
            'counts' => $counts,
            'lastProcessedAt' => $lastProcessedAt,
            'oldestPendingAt' => $oldestPendingAt,
            'lastFailure' => $lastFailure ? [
                'id' => (int) ($lastFailure->id ?? 0),
                'event' => (string) ($lastFailure->event ?? ''),
                'lastError' => isset($lastFailure->last_error) ? (string) $lastFailure->last_error : null,
                'updatedAt' => (string) ($lastFailure->updated_at ?? ''),
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function getHealth(?int $warnAfterSeconds = null): array
    {
        $threshold = $warnAfterSeconds ?? 300;
        if ($threshold < 30) {
            $threshold = 30;
        }

        $summary = $this->getSummary();
        $counts = is_array($summary['counts'] ?? null) ? $summary['counts'] : [];
        $pendingCount = (int) ($counts['queued'] ?? 0) + (int) ($counts['retry'] ?? 0);

        $lastProcessedAt = (string) ($summary['lastProcessedAt'] ?? '');
        $secondsSinceLastProcessed = null;
        if ($lastProcessedAt !== '' && strtotime($lastProcessedAt) !== false) {
            $secondsSinceLastProcessed = max(0, time() - (int) strtotime($lastProcessedAt));
        }

        $warning = false;
        $message = 'Queue worker appears healthy.';

        if ($pendingCount > 0) {
            if ($secondsSinceLastProcessed === null) {
                $warning = true;
                $message = 'Queue has pending jobs but no processed jobs have been recorded yet.';
            } elseif ($secondsSinceLastProcessed > $threshold) {
                $warning = true;
                $message = 'Queue has pending jobs and worker has not processed jobs recently.';
            }
        }

        return [
            'warning' => $warning,
            'message' => $message,
            'warnAfterSeconds' => $threshold,
            'secondsSinceLastProcessed' => $secondsSinceLastProcessed,
            'pendingCount' => $pendingCount,
            'summary' => $summary,
        ];
    }

    /** @return array<string, mixed> */
    public function replayFailed(?int $jobId = null, int $limit = 50): array
    {
        $ids = [];
        if ($jobId !== null && $jobId > 0) {
            $row = DB::table('notification_jobs')
                ->where('id', $jobId)
                ->where('status', 'failed')
                ->first();

            if ($row) {
                $ids[] = (int) $row->id;
            }
        } else {
            $safeLimit = max(1, min(200, $limit));
            $rows = DB::table('notification_jobs')
                ->where('status', 'failed')
                ->orderBy('id', 'asc')
                ->limit($safeLimit)
                ->get();

            foreach ($rows as $row) {
                $ids[] = (int) ($row->id ?? 0);
            }
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        }

        if (count($ids) === 0) {
            return ['replayed' => 0, 'ids' => []];
        }

        $replayed = 0;
        foreach ($ids as $id) {
            $affected = DB::table('notification_jobs')
                ->where('id', $id)
                ->where('status', 'failed')
                ->update([
                    'status' => 'retry',
                    'run_after' => Carbon::now(),
                    'attempts' => 0,
                    'last_error' => null,
                    'processed_at' => null,
                    'updated_at' => Carbon::now(),
                ]);

            if ($affected > 0) {
                $replayed++;
            }
        }

        $this->logQueueActivity(
            'NOTIFICATION_QUEUE_REPLAY_FAILED',
            [
                'requestedJobId' => $jobId,
                'limit' => $limit,
                'replayed' => $replayed,
                'jobIds' => $ids,
            ]
        );

        return [
            'replayed' => $replayed,
            'ids' => $ids,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function handleNow(string $event, array $payload): void
    {
        switch ($event) {
            case 'booking_created':
                $booking = is_array($payload['booking'] ?? null) ? $payload['booking'] : [];
                (new NotificationService)->bookingCreated($booking);
                $sms = new SmsService;
                $sms->bookingCreated($booking);
                $sms->bookingCreatedAdmin($booking);

                $vehicle = trim((string) ($booking['vehicleInfo'] ?? ''));
                $svcName = (string) ($booking['serviceName'] ?? '');
                $message = (string) ($booking['name'] ?? 'A customer')
                    .' booked '.$svcName
                    .($vehicle !== '' ? ' · '.$vehicle : '');
                $this->notifyRolesInApp(
                    ['owner', 'admin', 'manager'],
                    'new_booking',
                    'new_booking',
                    'New Booking Received',
                    $message,
                    ['bookingId' => $booking['id'] ?? null]
                );

                return;

            case 'booking_status_changed':
                $booking = is_array($payload['booking'] ?? null) ? $payload['booking'] : [];
                (new NotificationService)->bookingStatusChanged($booking);
                $status = (string) ($booking['status'] ?? '');
                if ($status === 'confirmed') {
                    (new SmsService)->bookingConfirmed($booking);
                } else {
                    (new SmsService)->bookingStatusChanged($booking);
                }

                $uid = (int) ($booking['userId'] ?? 0);
                if ($uid > 0) {
                    $prefSvc = new NotificationPreferencesService;
                    if ($prefSvc->inappEnabled($uid, 'status_changed')) {
                        $label = ucwords(str_replace('_', ' ', $status));
                        $svcName = (string) ($booking['serviceName'] ?? 'your service');
                        (new UserNotificationService)->createForUser(
                            $uid,
                            'status_changed',
                            'Booking Status: '.$label,
                            'Your booking for '.$svcName.' is now '.$label.'.',
                            ['bookingId' => $booking['id'] ?? null, 'status' => $status]
                        );
                    }
                }

                return;

            case 'inquiry_status_changed':
                $inquiry = is_array($payload['inquiry'] ?? null) ? $payload['inquiry'] : [];
                if (empty($inquiry)) {
                    return;
                }
                (new NotificationService)->inquiryStatusChanged($inquiry);
                (new SmsService)->inquiryStatusChanged($inquiry);

                return;

            case 'booking_awaiting_parts':
                $booking = is_array($payload['booking'] ?? null) ? $payload['booking'] : [];
                (new NotificationService)->bookingAwaitingParts($booking);

                $uid = (int) ($booking['userId'] ?? 0);
                if ($uid > 0) {
                    $prefSvc = new NotificationPreferencesService;
                    if ($prefSvc->inappEnabled($uid, 'parts_update')) {
                        $svcName = (string) ($booking['serviceName'] ?? 'your service');
                        (new UserNotificationService)->createForUser(
                            $uid,
                            'parts_update',
                            'Job On Hold - Awaiting Parts',
                            'Your '.$svcName.' job is on hold while we wait for parts to arrive.',
                            ['bookingId' => $booking['id'] ?? null]
                        );
                    }
                }

                return;

            case 'build_update_created':
                $booking = is_array($payload['booking'] ?? null) ? $payload['booking'] : [];
                $update = is_array($payload['update'] ?? null) ? $payload['update'] : [];
                (new NotificationService)->buildUpdateCreated($booking, $update);

                $uid = (int) ($booking['userId'] ?? 0);
                if ($uid > 0) {
                    $prefSvc = new NotificationPreferencesService;
                    if ($prefSvc->inappEnabled($uid, 'build_update')) {
                        $svcName = (string) ($booking['serviceName'] ?? 'your service');
                        $note = trim((string) ($update['note'] ?? ''));
                        $snippet = $note !== '' ? ': '.mb_strimwidth($note, 0, 60, '...') : '';
                        (new UserNotificationService)->createForUser(
                            $uid,
                            'build_update',
                            'Build Progress Update',
                            'New update on your '.$svcName.' job'.$snippet,
                            ['bookingId' => $booking['id'] ?? null]
                        );
                    }
                }

                return;

            case 'order_created':
                $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
                $uid = (int) ($order['userId'] ?? 0);
                $prefSvc = new NotificationPreferencesService;
                $notificationSvc = new NotificationService;

                if ($uid <= 0 || $prefSvc->emailEnabled($uid, 'order_created')) {
                    $notificationSvc->orderCreatedCustomer($order);
                }

                if ($uid > 0 && $prefSvc->inappEnabled($uid, 'order_created')) {
                    $orderNumber = (string) ($order['orderNumber'] ?? 'your order');
                    (new UserNotificationService)->createForUser(
                        $uid,
                        'order_created',
                        'Order Received',
                        'Your order '.$orderNumber.' has been received and is now pending confirmation.',
                        ['orderId' => $order['id'] ?? null, 'orderNumber' => $orderNumber]
                    );
                }

                $adminUsers = $this->usersForPermissionOrRoles('products:manage', ['owner', 'admin']);
                $orderNumber = (string) ($order['orderNumber'] ?? '');
                $customerName = (string) ($order['customerName'] ?? 'A customer');
                $message = $customerName.' placed order '.$orderNumber.'.';
                $this->notifyUsersInApp(
                    $adminUsers,
                    'new_order',
                    'new_order',
                    'New Order Placed',
                    $message,
                    ['orderId' => $order['id'] ?? null, 'orderNumber' => $orderNumber]
                );

                $emailRecipients = [];
                foreach ($adminUsers as $user) {
                    $adminUserId = (int) ($user['id'] ?? 0);
                    $adminEmail = strtolower(trim((string) ($user['email'] ?? '')));
                    if ($adminUserId <= 0 || $adminEmail === '' || ! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }
                    if (! $prefSvc->emailEnabled($adminUserId, 'new_order')) {
                        continue;
                    }
                    $emailRecipients[] = $adminEmail;
                }

                $notificationSvc->orderCreatedAdmin($order, $emailRecipients);

                return;

            case 'order_status_changed':
                $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
                $uid = (int) ($order['userId'] ?? 0);
                $prefSvc = new NotificationPreferencesService;

                if ($uid <= 0 || $prefSvc->emailEnabled($uid, 'order_status')) {
                    (new NotificationService)->orderStatusChangedCustomer($order);
                }

                if ($uid > 0 && $prefSvc->inappEnabled($uid, 'order_status')) {
                    $status = (string) ($order['status'] ?? 'pending');
                    $label = $this->labelOrderStatus($status);
                    $orderNumber = (string) ($order['orderNumber'] ?? 'your order');
                    (new UserNotificationService)->createForUser(
                        $uid,
                        'order_status',
                        'Order Status: '.$label,
                        'Your order '.$orderNumber.' is now '.$label.'.',
                        ['orderId' => $order['id'] ?? null, 'orderNumber' => $orderNumber, 'status' => $status]
                    );
                }

                return;

            case 'order_tracking_updated':
                $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
                $uid = (int) ($order['userId'] ?? 0);
                $prefSvc = new NotificationPreferencesService;

                if ($uid <= 0 || $prefSvc->emailEnabled($uid, 'order_tracking')) {
                    (new NotificationService)->orderTrackingUpdatedCustomer($order);
                }

                if ($uid > 0 && $prefSvc->inappEnabled($uid, 'order_tracking')) {
                    $orderNumber = (string) ($order['orderNumber'] ?? 'your order');
                    $trackingNumber = trim((string) ($order['trackingNumber'] ?? ''));
                    $courierName = trim((string) ($order['courierName'] ?? 'Courier'));
                    $message = $trackingNumber !== ''
                        ? 'Tracking for '.$orderNumber.' was updated to '.$trackingNumber.' via '.$courierName.'.'
                        : 'Delivery details for '.$orderNumber.' were updated.';
                    (new UserNotificationService)->createForUser(
                        $uid,
                        'order_tracking',
                        'Order Tracking Updated',
                        $message,
                        [
                            'orderId' => $order['id'] ?? null,
                            'orderNumber' => $orderNumber,
                            'trackingNumber' => $trackingNumber,
                            'courierName' => $courierName,
                        ]
                    );
                }

                return;

            case 'staff_assignment_sms_email':
                $booking = is_array($payload['booking'] ?? null) ? $payload['booking'] : [];
                $techPhone = (string) ($payload['techPhone'] ?? '');
                $techEmail = (string) ($payload['techEmail'] ?? '');
                $techName = (string) ($payload['techName'] ?? '');
                $techUserId = isset($payload['techUserId']) ? (int) $payload['techUserId'] : null;
                if ($techPhone !== '') {
                    (new SmsService)->staffAssigned($booking, $techPhone, $techName, $techUserId);
                }
                if ($techEmail !== '') {
                    (new NotificationService)->staffAssigned($booking, $techEmail, $techName);
                }

                return;

            case 'password_reset':
                (new NotificationService)->passwordReset(
                    (string) ($payload['email'] ?? ''),
                    (string) ($payload['resetUrl'] ?? '')
                );

                return;

            case 'contact_message':
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                (new NotificationService)->contactMessage($data);

                return;

            case 'customer_inquiry':
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                (new NotificationService)->customerInquiryAdmin($data);
                (new SmsService)->customerInquiryAdmin($data);
                try {
                    $customerEmail = strtolower(trim((string) ($data['emailAddress'] ?? $data['email'] ?? '')));
                    $customerPhone = trim((string) ($data['contactNumber'] ?? $data['phone'] ?? ''));

                    if ($customerEmail !== '') {
                        (new NotificationService)->customerInquiryCustomer($data);
                    }

                    if ($customerPhone !== '') {
                        (new SmsService)->customerInquiryCustomer($data);
                    }
                } catch (\Throwable $e) {
                    error_log('[NotificationJobQueueService] customer inquiry customer notification failed: '.$e->getMessage());
                }

                try {
                    (new UserNotificationService)->createForAdmin(
                        'inquiry',
                        'New Customer Inquiry',
                        'A new inquiry has been submitted by '.($data['fullName'] ?? $data['name'] ?? 'a customer').'.',
                        ['inquiryId' => $data['id'] ?? null]
                    );
                } catch (\Throwable $e) {
                    error_log('[NotificationJobQueueService] customer inquiry in-app notification failed: '.$e->getMessage());
                }

                return;

            case 'appointment_reminder_3h':
                $data = is_array($payload['data'] ?? null) ? $payload['data'] : (is_array($payload['booking'] ?? null) ? $payload['booking'] : []);
                if (empty($data)) {
                    return;
                }

                $apptDateStr = trim((string) ($data['appointmentDate'] ?? $data['appointment_date'] ?? ''));
                $apptTimeStr = trim((string) ($data['appointmentTime'] ?? $data['appointment_time'] ?? ''));
                if ($apptDateStr !== '' && $apptTimeStr !== '') {
                    try {
                        $apptDt = new DateTimeImmutable(
                            $apptDateStr.' '.$apptTimeStr,
                            new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila')
                        );
                        $minutesUntilAppt = (int) round(($apptDt->getTimestamp() - time()) / 60);
                        if ($minutesUntilAppt < 60) {
                            error_log(
                                '[NotificationJobQueueService] Skipping stale appointment_reminder_3h job: '
                                ."appointment is {$minutesUntilAppt} min away (threshold: 60 min)."
                            );

                            return;
                        }
                    } catch (\Throwable $e) {
                        error_log('[NotificationJobQueueService] Could not parse appointment datetime for staleness check: '.$e->getMessage());
                    }
                }

                try {
                    (new NotificationService)->appointmentReminder3hCustomer($data);
                    (new SmsService)->appointmentReminder3hCustomer($data);
                } catch (\Throwable $e) {
                    error_log('[NotificationJobQueueService] 3h appointment reminder customer failed: '.$e->getMessage());
                }

                try {
                    $adminUsers = $this->usersForPermissionOrRoles('bookings:manage', ['owner', 'admin']);
                    $emailRecipients = [];
                    foreach ($adminUsers as $user) {
                        $adminEmail = strtolower(trim((string) ($user['email'] ?? '')));
                        if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                            $emailRecipients[] = $adminEmail;
                        }
                    }

                    (new NotificationService)->appointmentReminder3hAdmin($data, $emailRecipients);
                    (new SmsService)->appointmentReminder3hAdmin($data);

                    $customerName = (string) ($data['fullName'] ?? $data['name'] ?? 'A customer');
                    $appointmentTime = (string) ($data['appointmentTime'] ?? $data['appointment_time'] ?? '');
                    $appointmentDate = (string) ($data['appointmentDate'] ?? $data['appointment_date'] ?? '');
                    $message = "Reminder: Appointment for {$customerName} is scheduled in 3 hours ({$appointmentDate} at {$appointmentTime}).";

                    $this->notifyUsersInApp(
                        $adminUsers,
                        'appointment_reminder',
                        'appointment_reminder',
                        'Upcoming Appointment Reminder (In 3 Hours)',
                        $message,
                        ['inquiry' => $data]
                    );
                } catch (\Throwable $e) {
                    error_log('[NotificationJobQueueService] 3h appointment reminder admin failed: '.$e->getMessage());
                }

                return;

            case 'waitlist_slot_available':
                $name = (string) ($payload['name'] ?? 'there');
                $email = (string) ($payload['email'] ?? '');
                $phone = (string) ($payload['phone'] ?? '');
                $date = (string) ($payload['date'] ?? '');
                $time = (string) ($payload['time'] ?? '');
                $claimUrl = (string) ($payload['claimUrl'] ?? '');
                $claimWindow = max(5, (int) ($payload['claimWindowMinutes'] ?? 30));
                $userId = isset($payload['userId']) ? (int) $payload['userId'] : 0;

                if ($userId > 0) {
                    $prefs = new NotificationPreferencesService;
                    if ($prefs->inappEnabled($userId, 'slot_available')) {
                        (new UserNotificationService)->createForUser(
                            $userId,
                            'slot_available',
                            'Slot Available!',
                            'A slot has opened on '.$date.' at '.$time.'. Book now before it is taken!',
                            ['slotDate' => $date, 'slotTime' => $time, 'claimUrl' => $claimUrl]
                        );
                    }
                }

                if ($phone !== '') {
                    (new SmsService)->waitlistSlotAvailable([
                        'name' => $name,
                        'phone' => $phone,
                        'date' => $date,
                        'time' => $time,
                    ]);
                }

                if ($email !== '') {
                    (new NotificationService)->sendWaitlistSlotAvailable(
                        $name,
                        $email,
                        $date,
                        $time,
                        $claimUrl,
                        $claimWindow
                    );
                }

                return;

            case 'admin_security_alert':
                $email = strtolower(trim((string) ($payload['email'] ?? '')));
                $ipAddress = (string) ($payload['ipAddress'] ?? '');
                $this->notifyRolesInApp(
                    ['owner', 'admin', 'manager'],
                    'security_alert',
                    'security_alert',
                    'Suspicious Login Pattern',
                    'Repeated login failures detected for '.$email.' from IP '.($ipAddress !== '' ? $ipAddress : 'unknown').'.',
                    ['email' => $email, 'ipAddress' => $ipAddress]
                );

                return;

            case 'marketing_campaign_message':
                $channel = strtolower(trim((string) ($payload['channel'] ?? 'inapp')));
                $userId = (int) ($payload['userId'] ?? 0);
                $name = trim((string) ($payload['name'] ?? 'Customer'));
                $email = strtolower(trim((string) ($payload['email'] ?? '')));
                $phone = trim((string) ($payload['phone'] ?? ''));
                $title = trim((string) ($payload['title'] ?? 'Special Offer'));
                $message = trim((string) ($payload['message'] ?? ''));
                $ctaUrl = trim((string) ($payload['ctaUrl'] ?? ''));

                if ($message === '') {
                    throw new RuntimeException('Campaign message payload is empty.');
                }

                if ($channel === 'inapp') {
                    if ($userId <= 0) {
                        throw new RuntimeException('In-app campaign message requires a userId.');
                    }
                    (new UserNotificationService)->createForUser(
                        $userId,
                        'order_created',
                        $title,
                        $message,
                        ['ctaUrl' => $ctaUrl, 'campaignId' => $payload['campaignId'] ?? null]
                    );
                } elseif ($channel === 'email') {
                    if ($email === '') {
                        throw new RuntimeException('Email campaign message requires an email address.');
                    }
                    (new NotificationService)->marketingCampaignMessage(
                        $email,
                        $name,
                        $title,
                        $message,
                        $ctaUrl,
                        isset($payload['messageHtml']) ? (string) $payload['messageHtml'] : null
                    );
                } elseif ($channel === 'sms') {
                    if ($phone === '') {
                        throw new RuntimeException('SMS campaign message requires a phone number.');
                    }
                    (new SmsService)->marketingCampaignMessage($phone, $message.($ctaUrl !== '' ? ' '.$ctaUrl : ''));
                } else {
                    throw new RuntimeException('Unknown campaign channel: '.$channel);
                }

                $this->markCampaignRecipientStatus($payload, 'sent', null);

                return;

            case 'inventory_low_stock':
                $itemName = (string) ($payload['itemName'] ?? 'Inventory item');
                $sku = (string) ($payload['sku'] ?? '');
                $qty = (string) ($payload['qtyOnHand'] ?? '0');
                $reorderPoint = (string) ($payload['reorderPoint'] ?? '0');
                $message = (string) ($payload['message'] ?? ($itemName.' is low on stock.'));

                $this->notifyRolesInApp(
                    ['owner', 'admin', 'manager'],
                    'new_order',
                    'new_order',
                    'Low Stock Alert',
                    $message,
                    [
                        'itemName' => $itemName,
                        'sku' => $sku,
                        'qtyOnHand' => $qty,
                        'reorderPoint' => $reorderPoint,
                    ]
                );

                return;

            default:
                if ($event === 'marketing_campaign_message') {
                    $this->markCampaignRecipientStatus($payload, 'failed', 'Unhandled campaign event.');
                }
                throw new RuntimeException('Unknown notification job event: '.$event);
        }
    }

    /** @param array<string, mixed> $payload */
    private function markCampaignRecipientStatus(array $payload, string $status, ?string $error): void
    {
        $runId = isset($payload['runId']) ? (int) $payload['runId'] : 0;
        $campaignId = isset($payload['campaignId']) ? (int) $payload['campaignId'] : 0;
        $channel = strtolower(trim((string) ($payload['channel'] ?? '')));
        $recipient = '';

        if ($channel === 'inapp') {
            $recipient = (string) ((int) ($payload['userId'] ?? 0));
        } elseif ($channel === 'email') {
            $recipient = strtolower(trim((string) ($payload['email'] ?? '')));
        } elseif ($channel === 'sms') {
            $recipient = trim((string) ($payload['phone'] ?? ''));
        }

        if ($runId <= 0 || $campaignId <= 0 || $channel === '' || $recipient === '') {
            return;
        }

        DB::table('marketing_campaign_recipients')
            ->where('run_id', $runId)
            ->where('campaign_id', $campaignId)
            ->where('channel', $channel)
            ->where('recipient', $recipient)
            ->where('status', 'queued')
            ->orderBy('id', 'asc')
            ->limit(1)
            ->update([
                'status' => $status,
                'error_text' => $error,
                'processed_at' => Carbon::now(),
            ]);
    }

    private function labelOrderStatus(string $status): string
    {
        return ucwords(str_replace('_', ' ', trim($status)));
    }

    /**
     * @param  string[]  $roles
     * @return array<int, array<string, mixed>>
     */
    private function usersForPermissionOrRoles(string $permission, array $roles = []): array
    {
        $normalizedRoles = array_values(array_unique(array_filter(
            array_map(static fn (string $role): string => strtolower(trim($role)), $roles),
            static fn (string $role): bool => $role !== ''
        )));

        $rows = DB::table('users as u')
            ->leftJoin('roles as r', 'r.role_key', '=', 'u.role')
            ->select('u.id', 'u.name', 'u.email', 'u.role', 'r.permissions_json')
            ->where(function ($q) {
                $q->whereNull('u.is_active')->orWhere('u.is_active', 1);
            })
            ->get();

        $matches = [];
        $seen = [];
        foreach ($rows as $row) {
            $userId = (int) ($row->id ?? 0);
            if ($userId <= 0 || isset($seen[$userId])) {
                continue;
            }

            $role = strtolower(trim((string) ($row->role ?? '')));
            $decoded = json_decode((string) ($row->permissions_json ?? '[]'), true);
            $permissions = is_array($decoded)
                ? array_values(array_filter(array_map('strval', $decoded), static fn (string $value): bool => $value !== ''))
                : [];

            if (! in_array($permission, $permissions, true) && ! in_array($role, $normalizedRoles, true)) {
                continue;
            }

            $seen[$userId] = true;
            $matches[] = [
                'id' => $userId,
                'name' => (string) ($row->name ?? ''),
                'email' => (string) ($row->email ?? ''),
                'role' => $role,
            ];
        }

        return $matches;
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @param  array<string, mixed>|null  $data
     */
    private function notifyUsersInApp(
        array $users,
        string $prefType,
        string $type,
        string $title,
        string $message,
        ?array $data = null
    ): void {
        if (count($users) === 0) {
            return;
        }

        $prefs = new NotificationPreferencesService;
        $notifications = new UserNotificationService;

        foreach ($users as $user) {
            $uid = (int) ($user['id'] ?? 0);
            if ($uid <= 0 || ! $prefs->inappEnabled($uid, $prefType)) {
                continue;
            }

            $notifications->createForUser($uid, $type, $title, $message, $data);
        }
    }

    /**
     * @param  string[]  $roles
     * @param  array<string, mixed>|null  $data
     */
    private function notifyRolesInApp(
        array $roles,
        string $prefType,
        string $type,
        string $title,
        string $message,
        ?array $data = null
    ): void {
        if (count($roles) === 0) {
            return;
        }

        $userIds = DB::table('users')
            ->whereIn('role', $roles)
            ->where(function ($q) {
                $q->whereNull('is_active')->orWhere('is_active', 1);
            })
            ->pluck('id')
            ->toArray();

        $prefs = new NotificationPreferencesService;
        $notifications = new UserNotificationService;

        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0) {
                continue;
            }
            if (! $prefs->inappEnabled($uid, $prefType)) {
                continue;
            }
            $notifications->createForUser($uid, $type, $title, $message, $data);
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logQueueActivity(string $description, array $properties = []): void
    {
        try {
            if (function_exists('activity')) {
                $logger = activity()->forSubject('notification_jobs', 0);

                $actorUserId = $this->resolveActorUserId();
                if ($actorUserId !== null) {
                    $logger->byUser($actorUserId);
                }

                if ($properties !== []) {
                    $logger->withProperties($properties);
                }

                $logger->log($description, 'notification_queue');
            }
        } catch (\Throwable $e) {
            error_log('[NotificationJobQueueService] Activity logging failed: '.$e->getMessage());
        }
    }

    private function resolveActorUserId(): ?int
    {
        try {
            $user = Auth::user();

            return $user ? (int) $user->id : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
