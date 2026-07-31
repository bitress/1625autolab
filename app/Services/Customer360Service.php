<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class Customer360Service
{
    /** @return array<string, mixed> */
    public function getByUserId(int $userId, int $limit = 25): array
    {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid customer id.', 422);
        }

        $lim = max(5, min(100, $limit));

        $profile = $this->fetchProfile($userId);
        $vehicles = $this->fetchVehicles($userId, $lim);
        $bookings = $this->fetchBookings($userId, $lim);
        $orders = $this->fetchOrders($userId, $lim);
        $reviews = $this->fetchReviews($userId, $lim);
        $spend = $this->fetchSpend($userId);
        $communications = $this->fetchCommunications($userId, (string) ($profile['email'] ?? ''), $lim);

        return [
            'profile' => $profile,
            'vehicles' => $vehicles,
            'bookings' => $bookings,
            'orders' => $orders,
            'reviews' => $reviews,
            'spend' => $spend,
            'communications' => $communications,
        ];
    }

    /** @return array<string, mixed> */
    private function fetchProfile(int $userId): array
    {
        $row = DB::table('users')
            ->select('id', 'name', 'email', 'phone', 'role', 'is_active', 'created_at')
            ->where('id', $userId)
            ->where('role', 'client')
            ->first();

        if (! $row) {
            throw new RuntimeException('Customer not found.', 404);
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => (string) ($row->name ?? ''),
            'email' => (string) ($row->email ?? ''),
            'phone' => (string) ($row->phone ?? ''),
            'role' => (string) ($row->role ?? 'client'),
            'isActive' => ((int) ($row->is_active ?? 1)) === 1,
            'createdAt' => (string) ($row->created_at ?? ''),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchVehicles(int $userId, int $limit): array
    {
        $rows = DB::table('client_vehicles')
            ->select('id', 'make', 'model', 'year', 'license_plate', 'vin', 'image_url', 'created_at', 'updated_at')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => (int) ($row->id ?? 0),
                'make' => (string) ($row->make ?? ''),
                'model' => (string) ($row->model ?? ''),
                'year' => (string) ($row->year ?? ''),
                'licensePlate' => isset($row->license_plate) ? (string) $row->license_plate : null,
                'vin' => isset($row->vin) ? (string) $row->vin : null,
                'imageUrl' => isset($row->image_url) ? (string) $row->image_url : null,
                'createdAt' => (string) ($row->created_at ?? ''),
                'updatedAt' => (string) ($row->updated_at ?? ''),
            ];
        })->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchBookings(int $userId, int $limit): array
    {
        $rows = DB::table('bookings as b')
            ->leftJoin('services as s', 's.id', '=', 'b.service_id')
            ->select(
                'b.id',
                'b.reference_number',
                DB::raw('COALESCE(s.title, "Service") AS service_name'),
                'b.appointment_date',
                'b.appointment_time',
                'b.status',
                'b.created_at',
                'b.updated_at'
            )
            ->where('b.user_id', $userId)
            ->orderBy('b.created_at', 'desc')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => (string) ($row->id ?? ''),
                'referenceNumber' => isset($row->reference_number) ? (string) $row->reference_number : null,
                'serviceName' => (string) ($row->service_name ?? ''),
                'appointmentDate' => (string) ($row->appointment_date ?? ''),
                'appointmentTime' => (string) ($row->appointment_time ?? ''),
                'status' => (string) ($row->status ?? ''),
                'createdAt' => (string) ($row->created_at ?? ''),
                'updatedAt' => (string) ($row->updated_at ?? ''),
            ];
        })->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchOrders(int $userId, int $limit): array
    {
        $rows = DB::table('product_orders')
            ->select('id', 'order_number', 'status', 'payment_status', 'total_amount', 'created_at', 'updated_at')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => (int) ($row->id ?? 0),
                'orderNumber' => (string) ($row->order_number ?? ''),
                'status' => (string) ($row->status ?? ''),
                'paymentStatus' => (string) ($row->payment_status ?? ''),
                'totalAmount' => (float) ($row->total_amount ?? 0),
                'createdAt' => (string) ($row->created_at ?? ''),
                'updatedAt' => (string) ($row->updated_at ?? ''),
            ];
        })->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchReviews(int $userId, int $limit): array
    {
        $rows = DB::table('booking_reviews as r')
            ->join('bookings as b', 'b.id', '=', 'r.booking_id')
            ->leftJoin('services as s', 's.id', '=', 'b.service_id')
            ->select(
                'r.id',
                'r.booking_id',
                'r.rating',
                'r.review',
                'r.is_approved',
                'r.created_at',
                DB::raw('COALESCE(s.title, "Service") AS service_name'),
                'b.vehicle_info'
            )
            ->where('r.user_id', $userId)
            ->orderBy('r.created_at', 'desc')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => (int) ($row->id ?? 0),
                'bookingId' => (string) ($row->booking_id ?? ''),
                'serviceName' => (string) ($row->service_name ?? ''),
                'vehicleInfo' => (string) ($row->vehicle_info ?? ''),
                'rating' => (int) ($row->rating ?? 0),
                'review' => isset($row->review) ? (string) $row->review : null,
                'isApproved' => ((int) ($row->is_approved ?? 0)) === 1,
                'createdAt' => (string) ($row->created_at ?? ''),
            ];
        })->toArray();
    }

    /** @return array<string, mixed> */
    private function fetchSpend(int $userId): array
    {
        $bookingRow = DB::selectOne(
            'SELECT
                COUNT(*) AS total_bookings,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS completed_bookings,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS bookings_30d,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS bookings_90d
             FROM bookings
             WHERE user_id = ?',
            [$userId]
        );

        $orderRow = DB::selectOne(
            'SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(total_amount), 0) AS lifetime_spend,
                COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END), 0) AS spend_30d,
                COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN total_amount ELSE 0 END), 0) AS spend_90d,
                COALESCE(AVG(total_amount), 0) AS avg_order_value
             FROM product_orders
             WHERE user_id = ?',
            [$userId]
        );

        return [
            'lifetimeSpend' => (float) ($orderRow->lifetime_spend ?? 0),
            'spend30d' => (float) ($orderRow->spend_30d ?? 0),
            'spend90d' => (float) ($orderRow->spend_90d ?? 0),
            'avgOrderValue' => (float) ($orderRow->avg_order_value ?? 0),
            'totalOrders' => (int) ($orderRow->total_orders ?? 0),
            'totalBookings' => (int) ($bookingRow->total_bookings ?? 0),
            'completedBookings' => (int) ($bookingRow->completed_bookings ?? 0),
            'bookings30d' => (int) ($bookingRow->bookings_30d ?? 0),
            'bookings90d' => (int) ($bookingRow->bookings_90d ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchCommunications(int $userId, string $email, int $limit): array
    {
        $events = [];

        $notifications = DB::table('notifications')
            ->select('id', 'type', 'title', 'message', 'is_read', 'created_at')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        foreach ($notifications as $row) {
            $events[] = [
                'source' => 'inapp',
                'event' => (string) ($row->type ?? ''),
                'title' => (string) ($row->title ?? ''),
                'message' => (string) ($row->message ?? ''),
                'status' => ((int) ($row->is_read ?? 0)) === 1 ? 'read' : 'unread',
                'createdAt' => (string) ($row->created_at ?? ''),
            ];
        }

        if ($email !== '') {
            $jobs = DB::table('notification_jobs')
                ->select('event', 'status', 'created_at', 'processed_at')
                ->where('payload', 'LIKE', '%"'.$email.'"%')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            foreach ($jobs as $row) {
                $events[] = [
                    'source' => 'queue',
                    'event' => (string) ($row->event ?? ''),
                    'title' => 'Queued notification',
                    'message' => 'Delivery status: '.(string) ($row->status ?? 'queued'),
                    'status' => (string) ($row->status ?? 'queued'),
                    'createdAt' => (string) ($row->processed_at ?? $row->created_at ?? ''),
                ];
            }
        }

        usort($events, function (array $a, array $b): int {
            return strcmp((string) ($b['createdAt'] ?? ''), (string) ($a['createdAt'] ?? ''));
        });

        return array_slice($events, 0, $limit);
    }
}
