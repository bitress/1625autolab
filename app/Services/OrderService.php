<?php

declare(strict_types=1);

namespace App\Services;

class OrderService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?int $userId): array
    {
        throw new \RuntimeException('OrderService::create() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function listMine(int $userId): array
    {
        throw new \RuntimeException('OrderService::listMine() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function getById(int $orderId, ?int $userId, ?bool $activeOnly): array
    {
        throw new \RuntimeException('OrderService::getById() not implemented.', 501);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    public function listAll(array $filters, int $pageSize, int $page): array
    {
        throw new \RuntimeException('OrderService::listAll() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function updateStatus(int $orderId, string $status): array
    {
        throw new \RuntimeException('OrderService::updateStatus() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function updateTracking(int $orderId, string $courierName, string $trackingNumber): array
    {
        throw new \RuntimeException('OrderService::updateTracking() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function updatePaymentStatus(int $orderId, string $paymentStatus): array
    {
        throw new \RuntimeException('OrderService::updatePaymentStatus() not implemented.', 501);
    }
}
