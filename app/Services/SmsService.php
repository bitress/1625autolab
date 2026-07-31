<?php

declare(strict_types=1);

namespace App\Services;

class SmsService
{
    /**
     * Send an appointment reminder SMS.
     *
     * @param  array<string, mixed>  $booking
     */
    public function appointmentReminder(array $booking): void
    {
        throw new \RuntimeException('SmsService::appointmentReminder() not implemented.', 501);
    }
}
