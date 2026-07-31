<?php

declare(strict_types=1);

namespace App\Services;

class NotificationJobQueueService
{
    /**
     * Dispatch an async notification job.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $event, array $payload, ?\DateTimeInterface $runAfter = null): void
    {
        throw new \RuntimeException('NotificationJobQueueService::dispatch() not implemented.', 501);
    }

    /**
     * Dispatch a notification synchronously (immediately).
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatchNow(string $event, array $payload): void
    {
        throw new \RuntimeException('NotificationJobQueueService::dispatchNow() not implemented.', 501);
    }

    /**
     * Process pending notification jobs up to $limit.
     *
     * @return array<string, mixed>
     */
    public function processPending(?int $limit = null): array
    {
        throw new \RuntimeException('NotificationJobQueueService::processPending() not implemented.', 501);
    }

    /**
     * Return summary counts by status.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        throw new \RuntimeException('NotificationJobQueueService::getSummary() not implemented.', 501);
    }

    /**
     * List jobs by status.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listJobs(string $status, int $limit): array
    {
        throw new \RuntimeException('NotificationJobQueueService::listJobs() not implemented.', 501);
    }

    /**
     * Return queue health metrics.
     *
     * @return array<string, mixed>
     */
    public function getHealth(?int $warnAfterSeconds): array
    {
        throw new \RuntimeException('NotificationJobQueueService::getHealth() not implemented.', 501);
    }

    /**
     * Replay failed jobs (all or a single job when $id provided).
     *
     * @return array<string, mixed>
     */
    public function replayFailed(?int $id, int $limit): array
    {
        throw new \RuntimeException('NotificationJobQueueService::replayFailed() not implemented.', 501);
    }

    /**
     * Calculate the timestamp for a 3-hour-before-appointment reminder.
     * Returns null when no valid date/time is present or the window has passed.
     */
    public static function calculateReminderRunAfter(string $date, string $time, int $hoursBeforeH): ?\DateTimeInterface
    {
        // TODO: parse $date + $time, subtract $hoursBeforeH hours, return DateTime or null.
        throw new \RuntimeException('NotificationJobQueueService::calculateReminderRunAfter() not implemented.', 501);
    }
}
