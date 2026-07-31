<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily Jobs
Schedule::command('app:cron-daily')->daily();

// Notification Queue
Schedule::command('app:cron-notification-queue')->everyMinute();

// Waitlist Autofill
Schedule::command('app:cron-waitlist-autofill')->hourly();

// Appointment Reminders
Schedule::command('app:cron-appointment-reminders')->dailyAt('08:00');

// Queue Worker (Poor man's daemon)
Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();
