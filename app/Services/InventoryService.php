<?php

declare(strict_types=1);

namespace App\Services;

class InventoryService
{
    /**
     * @param  array<string, string|bool>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function listItems(array $filters): array
    {
        throw new \RuntimeException('InventoryService::listItems() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createItem(array $data, ?int $actorId): array
    {
        throw new \RuntimeException('InventoryService::createItem() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateItem(int $id, array $data): array
    {
        throw new \RuntimeException('InventoryService::updateItem() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function listMovements(int $limit): array
    {
        throw new \RuntimeException('InventoryService::listMovements() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function adjustStock(array $data, ?int $actorId): array
    {
        throw new \RuntimeException('InventoryService::adjustStock() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function listLowStockAlerts(string $status, int $limit): array
    {
        throw new \RuntimeException('InventoryService::listLowStockAlerts() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function listSuppliers(): array
    {
        throw new \RuntimeException('InventoryService::listSuppliers() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createSupplier(array $data): array
    {
        throw new \RuntimeException('InventoryService::createSupplier() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function listPurchaseOrders(int $limit): array
    {
        throw new \RuntimeException('InventoryService::listPurchaseOrders() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPurchaseOrder(array $data, ?int $actorId): array
    {
        throw new \RuntimeException('InventoryService::createPurchaseOrder() not implemented.', 501);
    }

    /** @return array<string, mixed> */
    public function updatePurchaseOrderStatus(int $id, string $status, ?int $actorId): array
    {
        throw new \RuntimeException('InventoryService::updatePurchaseOrderStatus() not implemented.', 501);
    }

    /** @return array<int, array<string, mixed>> */
    public function listBookingPartRequirements(string $bookingId): array
    {
        throw new \RuntimeException('InventoryService::listBookingPartRequirements() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createBookingPartRequirement(string $bookingId, array $data, ?int $actorId): array
    {
        throw new \RuntimeException('InventoryService::createBookingPartRequirement() not implemented.', 501);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateBookingPartRequirement(string $bookingId, int $reqId, array $data): array
    {
        throw new \RuntimeException('InventoryService::updateBookingPartRequirement() not implemented.', 501);
    }
}
