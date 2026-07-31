<?php

declare(strict_types=1);

namespace App\Services;

class StatsService
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly InquiryService $inquiryService
    ) {}

    public function getDashboardStats(): array
    {
        $stats = $this->bookingService->getStats();
        $inquiryStats = $this->inquiryService->getStats();

        $stats = array_merge($stats, $inquiryStats);

        $stats['totalAppointments'] = ($stats['totalBookings'] ?? 0) + ($stats['totalInquiries'] ?? 0);
        $stats['activeAppointments'] = ($stats['activeBookings'] ?? 0) + ($stats['activeInquiries'] ?? 0);
        $stats['todayAppointments'] = ($stats['todayBookings'] ?? 0) + ($stats['todayInquiries'] ?? 0);
        $stats['todayActiveAppointments'] = ($stats['todayPending'] ?? 0) + ($stats['todayPendingInquiries'] ?? 0);

        return $stats;
    }
}
