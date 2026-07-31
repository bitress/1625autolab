<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    /** @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listItems(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $lowStockOnly = (bool) ($filters['lowStockOnly'] ?? false);

        $query = DB::table('inventory_items as i')
            ->leftJoin('suppliers as s', 's.id', '=', 'i.supplier_id')
            ->select('i.*', 's.name as supplier_name')
            ->where('i.is_active', 1);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('i.sku', 'LIKE', '%'.$search.'%')
                    ->orWhere('i.name', 'LIKE', '%'.$search.'%')
                    ->orWhere('i.category', 'LIKE', '%'.$search.'%');
            });
        }

        if ($lowStockOnly) {
            $query->whereColumn('i.qty_on_hand', '<=', 'i.reorder_point');
        }

        $query->orderBy('i.updated_at', 'desc')->orderBy('i.id', 'desc');

        return $query->get()->map(fn ($row) => $this->formatItem((array) $row))->toArray();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createItem(array $data, ?int $actorUserId = null): array
    {
        $payload = $this->normalizeItemPayload($data);

        $id = DB::table('inventory_items')->insertGetId([
            'sku' => $payload['sku'],
            'name' => $payload['name'],
            'category' => $payload['category'],
            'unit' => $payload['unit'],
            'qty_on_hand' => $payload['qtyOnHand'],
            'reorder_point' => $payload['reorderPoint'],
            'unit_cost' => $payload['unitCost'],
            'supplier_id' => $payload['supplierId'],
            'is_active' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        if ((float) $payload['qtyOnHand'] !== 0.0) {
            $this->recordMovement($id, 'adjustment', (float) $payload['qtyOnHand'], 'Initial stock', 'inventory_item', (string) $id, $actorUserId);
        }

        $item = $this->getItemById($id);
        $this->checkLowStockAlert($item);

        $this->logInventoryActivity(
            'INVENTORY_ITEM_CREATED',
            'inventory_items',
            $id,
            ['after' => $item],
            $actorUserId
        );

        return $item;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateItem(int $id, array $data): array
    {
        $current = $this->getItemById($id);
        $payload = $this->normalizeItemPayload(array_merge($current, $data), true);

        DB::table('inventory_items')->where('id', $id)->update([
            'sku' => $payload['sku'],
            'name' => $payload['name'],
            'category' => $payload['category'],
            'unit' => $payload['unit'],
            'reorder_point' => $payload['reorderPoint'],
            'unit_cost' => $payload['unitCost'],
            'supplier_id' => $payload['supplierId'],
            'is_active' => $payload['isActive'] ? 1 : 0,
            'updated_at' => Carbon::now(),
        ]);

        $item = $this->getItemById($id);
        $this->checkLowStockAlert($item);

        $this->logInventoryActivity(
            'INVENTORY_ITEM_UPDATED',
            'inventory_items',
            $id,
            ['before' => $current, 'after' => $item],
            $this->resolveActorUserId()
        );

        return $item;
    }

    /** @return array<int, array<string, mixed>> */
    public function listMovements(int $limit = 100): array
    {
        $lim = max(1, min(500, $limit));

        $rows = DB::table('inventory_movements as m')
            ->join('inventory_items as i', 'i.id', '=', 'm.item_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.actor_user_id')
            ->select('m.*', 'i.sku', 'i.name as item_name', 'u.name as actor_name')
            ->orderBy('m.created_at', 'desc')
            ->orderBy('m.id', 'desc')
            ->limit($lim)
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => (int) ($row->id ?? 0),
                'itemId' => (int) ($row->item_id ?? 0),
                'itemSku' => (string) ($row->sku ?? ''),
                'itemName' => (string) ($row->item_name ?? ''),
                'movementType' => (string) ($row->movement_type ?? ''),
                'quantityDelta' => (float) ($row->quantity_delta ?? 0),
                'note' => (string) ($row->note ?? ''),
                'referenceType' => isset($row->reference_type) ? (string) $row->reference_type : null,
                'referenceId' => isset($row->reference_id) ? (string) $row->reference_id : null,
                'actorUserId' => isset($row->actor_user_id) ? (int) $row->actor_user_id : null,
                'actorName' => isset($row->actor_name) ? (string) $row->actor_name : null,
                'createdAt' => (string) ($row->created_at ?? ''),
            ];
        })->toArray();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function adjustStock(array $data, ?int $actorUserId = null): array
    {
        $itemId = (int) ($data['itemId'] ?? 0);
        $delta = (float) ($data['quantityDelta'] ?? 0);
        $note = trim((string) ($data['note'] ?? 'Stock adjustment'));

        if ($itemId <= 0) {
            throw new RuntimeException('itemId is required.', 422);
        }
        if ($delta == 0.0) {
            throw new RuntimeException('quantityDelta cannot be zero.', 422);
        }

        DB::beginTransaction();
        try {
            $item = $this->getItemById($itemId, true);
            $nextQty = (float) ($item['qtyOnHand'] ?? 0) + $delta;
            if ($nextQty < 0) {
                throw new RuntimeException('Stock cannot go below zero.', 422);
            }

            DB::table('inventory_items')->where('id', $itemId)->update(['qty_on_hand' => $nextQty]);

            $this->recordMovement($itemId, 'adjustment', $delta, $note, 'inventory_item', (string) $itemId, $actorUserId);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Failed to adjust stock.', 500, $e);
        }

        $updated = $this->getItemById($itemId);
        $this->checkLowStockAlert($updated);

        $this->logInventoryActivity(
            'INVENTORY_STOCK_ADJUSTED',
            'inventory_items',
            $itemId,
            [
                'delta' => $delta,
                'note' => $note,
                'after' => $updated,
            ],
            $actorUserId
        );

        return $updated;
    }

    /** @return array<int, array<string, mixed>> */
    public function listSuppliers(): array
    {
        $rows = DB::table('suppliers')->orderBy('is_active', 'desc')->orderBy('name', 'asc')->get();

        return $rows->map(function ($row) {
            return [
                'id' => (int) ($row->id ?? 0),
                'name' => (string) ($row->name ?? ''),
                'contactPerson' => (string) ($row->contact_person ?? ''),
                'phone' => (string) ($row->phone ?? ''),
                'email' => (string) ($row->email ?? ''),
                'notes' => isset($row->notes) ? (string) $row->notes : null,
                'isActive' => ((int) ($row->is_active ?? 1)) === 1,
                'createdAt' => (string) ($row->created_at ?? ''),
                'updatedAt' => (string) ($row->updated_at ?? ''),
            ];
        })->toArray();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createSupplier(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Supplier name is required.', 422);
        }

        $id = DB::table('suppliers')->insertGetId([
            'name' => mb_substr($name, 0, 180),
            'contact_person' => mb_substr(trim((string) ($data['contactPerson'] ?? '')), 0, 180),
            'phone' => mb_substr(trim((string) ($data['phone'] ?? '')), 0, 40),
            'email' => mb_substr(strtolower(trim((string) ($data['email'] ?? ''))), 0, 180),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'is_active' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $rows = $this->listSuppliers();
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                $this->logInventoryActivity(
                    'INVENTORY_SUPPLIER_CREATED',
                    'suppliers',
                    $id,
                    ['after' => $row],
                    $this->resolveActorUserId()
                );

                return $row;
            }
        }

        throw new RuntimeException('Failed to create supplier.', 500);
    }

    /** @return array<int, array<string, mixed>> */
    public function listPurchaseOrders(int $limit = 100): array
    {
        $lim = max(1, min(300, $limit));

        $rows = DB::table('purchase_orders as po')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('users as u', 'u.id', '=', 'po.created_by')
            ->select('po.*', 's.name as supplier_name', 'u.name as created_by_name')
            ->orderBy('po.created_at', 'desc')
            ->orderBy('po.id', 'desc')
            ->limit($lim)
            ->get();

        $orders = [];
        foreach ($rows as $row) {
            $orderId = (int) ($row->id ?? 0);
            $orders[] = [
                'id' => $orderId,
                'poNumber' => (string) ($row->po_number ?? ''),
                'supplierId' => isset($row->supplier_id) ? (int) $row->supplier_id : null,
                'supplierName' => isset($row->supplier_name) ? (string) $row->supplier_name : null,
                'status' => (string) ($row->status ?? 'draft'),
                'notes' => isset($row->notes) ? (string) $row->notes : null,
                'orderedAt' => isset($row->ordered_at) ? (string) $row->ordered_at : null,
                'expectedAt' => isset($row->expected_at) ? (string) $row->expected_at : null,
                'receivedAt' => isset($row->received_at) ? (string) $row->received_at : null,
                'createdBy' => isset($row->created_by) ? (int) $row->created_by : null,
                'createdByName' => isset($row->created_by_name) ? (string) $row->created_by_name : null,
                'createdAt' => (string) ($row->created_at ?? ''),
                'updatedAt' => (string) ($row->updated_at ?? ''),
                'items' => $this->fetchPurchaseOrderItems($orderId),
            ];
        }

        return $orders;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createPurchaseOrder(array $data, ?int $actorUserId = null): array
    {
        $supplierId = isset($data['supplierId']) ? (int) $data['supplierId'] : null;
        $notes = trim((string) ($data['notes'] ?? ''));
        $expectedAt = trim((string) ($data['expectedAt'] ?? ''));

        $itemsRaw = $data['items'] ?? [];
        if (! is_array($itemsRaw) || count($itemsRaw) === 0) {
            throw new RuntimeException('Purchase order must include at least one item.', 422);
        }

        $poNumber = $this->generatePoNumber();

        DB::beginTransaction();
        try {
            $poId = DB::table('purchase_orders')->insertGetId([
                'po_number' => $poNumber,
                'supplier_id' => $supplierId,
                'status' => 'ordered',
                'notes' => $notes !== '' ? $notes : null,
                'ordered_at' => Carbon::now(),
                'expected_at' => $expectedAt !== '' ? $expectedAt : null,
                'created_by' => $actorUserId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            foreach ($itemsRaw as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $itemId = (int) ($item['itemId'] ?? 0);
                $qty = (float) ($item['quantity'] ?? 0);
                $unitCost = (float) ($item['unitCost'] ?? 0);
                if ($itemId <= 0 || $qty <= 0) {
                    continue;
                }

                DB::table('purchase_order_items')->insert([
                    'purchase_order_id' => $poId,
                    'item_id' => $itemId,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'line_total' => $qty * $unitCost,
                    'received_qty' => 0,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Failed to create purchase order.', 500, $e);
        }

        $all = $this->listPurchaseOrders(100);
        foreach ($all as $row) {
            if ((string) ($row['poNumber'] ?? '') === $poNumber) {
                $this->logInventoryActivity(
                    'INVENTORY_PURCHASE_ORDER_CREATED',
                    'purchase_orders',
                    (int) ($row['id'] ?? 0),
                    ['after' => $row],
                    $actorUserId
                );

                return $row;
            }
        }

        throw new RuntimeException('Failed to fetch purchase order.', 500);
    }

    /** @return array<string, mixed> */
    public function updatePurchaseOrderStatus(int $id, string $status, ?int $actorUserId = null): array
    {
        $allowed = ['draft', 'ordered', 'partially_received', 'received', 'cancelled'];
        $normalized = strtolower(trim($status));
        if (! in_array($normalized, $allowed, true)) {
            throw new RuntimeException('Invalid purchase order status.', 422);
        }

        $updates = [
            'status' => $normalized,
            'updated_at' => Carbon::now(),
        ];

        if ($normalized === 'received') {
            $updates['received_at'] = Carbon::now();
        }

        $affected = DB::table('purchase_orders')->where('id', $id)->update($updates);

        if ($affected === 0) {
            throw new RuntimeException('Purchase order not found.', 404);
        }

        if ($normalized === 'received') {
            $items = $this->fetchPurchaseOrderItems($id);
            foreach ($items as $poItem) {
                $itemId = (int) ($poItem['itemId'] ?? 0);
                $qty = (float) ($poItem['quantity'] ?? 0);
                if ($itemId <= 0 || $qty <= 0) {
                    continue;
                }

                DB::table('inventory_items')->where('id', $itemId)->increment('qty_on_hand', $qty);
                DB::table('purchase_order_items')->where('id', (int) ($poItem['id'] ?? 0))->update(['received_qty' => $qty]);

                $this->recordMovement($itemId, 'purchase', $qty, 'PO received', 'purchase_order', (string) $id, $actorUserId);

                $item = $this->getItemById($itemId);
                $this->checkLowStockAlert($item);
            }
        }

        foreach ($this->listPurchaseOrders(100) as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                $this->logInventoryActivity(
                    'INVENTORY_PURCHASE_ORDER_STATUS_UPDATED',
                    'purchase_orders',
                    $id,
                    [
                        'status' => $normalized,
                        'after' => $row,
                    ],
                    $actorUserId
                );

                return $row;
            }
        }

        throw new RuntimeException('Purchase order not found.', 404);
    }

    /** @return array<int, array<string, mixed>> */
    public function listBookingPartRequirements(string $bookingId): array
    {
        $rows = DB::table('booking_part_requirements as bpr')
            ->leftJoin('inventory_items as i', 'i.id', '=', 'bpr.inventory_item_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'bpr.supplier_id')
            ->select('bpr.*', 'i.sku as inventory_sku', 'i.name as inventory_name', 's.name as supplier_name')
            ->where('bpr.booking_id', $bookingId)
            ->orderBy('bpr.created_at', 'desc')
            ->orderBy('bpr.id', 'desc')
            ->get();

        return $rows->map(fn ($row) => $this->formatPartRequirement((array) $row))->toArray();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createBookingPartRequirement(string $bookingId, array $data, ?int $actorUserId = null): array
    {
        $partName = trim((string) ($data['partName'] ?? ''));
        $qty = (float) ($data['quantity'] ?? 1);
        $inventoryItemId = isset($data['inventoryItemId']) ? (int) $data['inventoryItemId'] : null;
        $supplierId = isset($data['supplierId']) ? (int) $data['supplierId'] : null;
        $note = trim((string) ($data['note'] ?? ''));

        if ($partName === '') {
            throw new RuntimeException('partName is required.', 422);
        }
        if ($qty <= 0) {
            throw new RuntimeException('quantity must be greater than zero.', 422);
        }

        $id = DB::table('booking_part_requirements')->insertGetId([
            'booking_id' => $bookingId,
            'inventory_item_id' => $inventoryItemId,
            'part_name' => mb_substr($partName, 0, 200),
            'quantity' => $qty,
            'status' => 'needed',
            'supplier_id' => $supplierId,
            'po_item_id' => null,
            'note' => mb_substr($note, 0, 255),
            'created_by' => $actorUserId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        foreach ($this->listBookingPartRequirements($bookingId) as $req) {
            if ((int) ($req['id'] ?? 0) === $id) {
                $this->logInventoryActivity(
                    'INVENTORY_BOOKING_PART_REQUIREMENT_CREATED',
                    'bookings',
                    $bookingId,
                    ['requirement' => $req],
                    $actorUserId
                );

                return $req;
            }
        }

        throw new RuntimeException('Failed to create booking part requirement.', 500);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateBookingPartRequirement(string $bookingId, int $requirementId, array $data): array
    {
        $before = null;
        foreach ($this->listBookingPartRequirements($bookingId) as $req) {
            if ((int) ($req['id'] ?? 0) === $requirementId) {
                $before = $req;
                break;
            }
        }

        $status = strtolower(trim((string) ($data['status'] ?? '')));
        $allowed = ['needed', 'ordered', 'arrived', 'installed', 'cancelled'];
        if ($status !== '' && ! in_array($status, $allowed, true)) {
            throw new RuntimeException('Invalid requirement status.', 422);
        }

        $updates = ['updated_at' => Carbon::now()];

        if (array_key_exists('status', $data)) {
            $updates['status'] = $status;
        }
        if (array_key_exists('note', $data)) {
            $updates['note'] = mb_substr(trim((string) $data['note']), 0, 255);
        }
        if (array_key_exists('supplierId', $data)) {
            $updates['supplier_id'] = $data['supplierId'] !== null ? (int) $data['supplierId'] : null;
        }
        if (array_key_exists('poItemId', $data)) {
            $updates['po_item_id'] = $data['poItemId'] !== null ? (int) $data['poItemId'] : null;
        }

        if (count($updates) === 1) { // only updated_at
            throw new RuntimeException('No changes provided.', 422);
        }

        $affected = DB::table('booking_part_requirements')
            ->where('id', $requirementId)
            ->where('booking_id', $bookingId)
            ->update($updates);

        if ($affected === 0) {
            throw new RuntimeException('Part requirement not found.', 404);
        }

        foreach ($this->listBookingPartRequirements($bookingId) as $req) {
            if ((int) ($req['id'] ?? 0) === $requirementId) {
                $this->logInventoryActivity(
                    'INVENTORY_BOOKING_PART_REQUIREMENT_UPDATED',
                    'bookings',
                    $bookingId,
                    [
                        'before' => $before,
                        'after' => $req,
                    ],
                    $this->resolveActorUserId()
                );

                return $req;
            }
        }

        throw new RuntimeException('Part requirement not found.', 404);
    }

    /** @return array<int, array<string, mixed>> */
    public function listLowStockAlerts(string $status = 'open', int $limit = 100): array
    {
        $normalizedStatus = strtolower(trim($status));
        if (! in_array($normalizedStatus, ['open', 'resolved', 'all'], true)) {
            $normalizedStatus = 'open';
        }

        $query = DB::table('inventory_reorder_alerts as a')
            ->join('inventory_items as i', 'i.id', '=', 'a.item_id')
            ->select('a.*', 'i.sku', 'i.name as item_name')
            ->orderBy('a.created_at', 'desc')
            ->orderBy('a.id', 'desc')
            ->limit(max(1, min(300, $limit)));

        if ($normalizedStatus !== 'all') {
            $query->where('a.status', $normalizedStatus);
        }

        return $query->get()->map(function ($row) {
            return [
                'id' => (int) ($row->id ?? 0),
                'itemId' => (int) ($row->item_id ?? 0),
                'itemSku' => (string) ($row->sku ?? ''),
                'itemName' => (string) ($row->item_name ?? ''),
                'status' => (string) ($row->status ?? 'open'),
                'qtySnapshot' => (float) ($row->qty_snapshot ?? 0),
                'reorderPointSnapshot' => (float) ($row->reorder_point_snapshot ?? 0),
                'message' => (string) ($row->message ?? ''),
                'createdAt' => (string) ($row->created_at ?? ''),
                'resolvedAt' => isset($row->resolved_at) ? (string) $row->resolved_at : null,
            ];
        })->toArray();
    }

    /** @return array<string, mixed> */
    private function getItemById(int $id, bool $forUpdate = false): array
    {
        $query = DB::table('inventory_items as i')
            ->leftJoin('suppliers as s', 's.id', '=', 'i.supplier_id')
            ->select('i.*', 's.name as supplier_name')
            ->where('i.id', $id);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();
        if (! $row) {
            throw new RuntimeException('Inventory item not found.', 404);
        }

        return $this->formatItem((array) $row);
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatItem(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'sku' => (string) ($row['sku'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'unit' => (string) ($row['unit'] ?? 'pcs'),
            'qtyOnHand' => (float) ($row['qty_on_hand'] ?? 0),
            'reorderPoint' => (float) ($row['reorder_point'] ?? 0),
            'unitCost' => (float) ($row['unit_cost'] ?? 0),
            'supplierId' => isset($row['supplier_id']) ? (int) $row['supplier_id'] : null,
            'supplierName' => isset($row['supplier_name']) ? (string) $row['supplier_name'] : null,
            'isActive' => ((int) ($row['is_active'] ?? 1)) === 1,
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeItemPayload(array $data, bool $isUpdate = false): array
    {
        $sku = strtoupper(trim((string) ($data['sku'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $category = trim((string) ($data['category'] ?? ''));
        $unit = trim((string) ($data['unit'] ?? 'pcs'));
        $qtyOnHand = (float) ($data['qtyOnHand'] ?? ($data['qty_on_hand'] ?? 0));
        $reorderPoint = (float) ($data['reorderPoint'] ?? ($data['reorder_point'] ?? 0));
        $unitCost = (float) ($data['unitCost'] ?? ($data['unit_cost'] ?? 0));
        $supplierId = isset($data['supplierId']) ? (int) $data['supplierId'] : (isset($data['supplier_id']) ? (int) $data['supplier_id'] : null);
        $isActive = array_key_exists('isActive', $data) ? (bool) $data['isActive'] : true;

        if ($sku === '') {
            throw new RuntimeException('SKU is required.', 422);
        }
        if ($name === '') {
            throw new RuntimeException('Item name is required.', 422);
        }
        if ($qtyOnHand < 0) {
            throw new RuntimeException('qtyOnHand cannot be negative.', 422);
        }
        if ($reorderPoint < 0) {
            throw new RuntimeException('reorderPoint cannot be negative.', 422);
        }
        if ($unitCost < 0) {
            throw new RuntimeException('unitCost cannot be negative.', 422);
        }

        return [
            'sku' => mb_substr($sku, 0, 80),
            'name' => mb_substr($name, 0, 200),
            'category' => mb_substr($category, 0, 120),
            'unit' => mb_substr($unit !== '' ? $unit : 'pcs', 0, 40),
            'qtyOnHand' => $qtyOnHand,
            'reorderPoint' => $reorderPoint,
            'unitCost' => $unitCost,
            'supplierId' => $supplierId,
            'isActive' => $isActive,
            'isUpdate' => $isUpdate,
        ];
    }

    private function recordMovement(
        int $itemId,
        string $movementType,
        float $quantityDelta,
        string $note,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?int $actorUserId = null
    ): void {
        DB::table('inventory_movements')->insert([
            'item_id' => $itemId,
            'movement_type' => $movementType,
            'quantity_delta' => $quantityDelta,
            'note' => mb_substr($note, 0, 255),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'actor_user_id' => $actorUserId,
            'created_at' => Carbon::now(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchPurchaseOrderItems(int $purchaseOrderId): array
    {
        $rows = DB::table('purchase_order_items as poi')
            ->join('inventory_items as i', 'i.id', '=', 'poi.item_id')
            ->select('poi.*', 'i.sku', 'i.name as item_name')
            ->where('poi.purchase_order_id', $purchaseOrderId)
            ->orderBy('poi.id', 'asc')
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => (int) ($row->id ?? 0),
                'itemId' => (int) ($row->item_id ?? 0),
                'itemSku' => (string) ($row->sku ?? ''),
                'itemName' => (string) ($row->item_name ?? ''),
                'quantity' => (float) ($row->quantity ?? 0),
                'unitCost' => (float) ($row->unit_cost ?? 0),
                'lineTotal' => (float) ($row->line_total ?? 0),
                'receivedQty' => (float) ($row->received_qty ?? 0),
            ];
        })->toArray();
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatPartRequirement(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'bookingId' => (string) ($row['booking_id'] ?? ''),
            'inventoryItemId' => isset($row['inventory_item_id']) ? (int) $row['inventory_item_id'] : null,
            'inventorySku' => isset($row['inventory_sku']) ? (string) $row['inventory_sku'] : null,
            'inventoryName' => isset($row['inventory_name']) ? (string) $row['inventory_name'] : null,
            'partName' => (string) ($row['part_name'] ?? ''),
            'quantity' => (float) ($row['quantity'] ?? 0),
            'status' => (string) ($row['status'] ?? 'needed'),
            'supplierId' => isset($row['supplier_id']) ? (int) $row['supplier_id'] : null,
            'supplierName' => isset($row['supplier_name']) ? (string) $row['supplier_name'] : null,
            'poItemId' => isset($row['po_item_id']) ? (int) $row['po_item_id'] : null,
            'note' => (string) ($row['note'] ?? ''),
            'createdBy' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $item */
    private function checkLowStockAlert(array $item): void
    {
        $itemId = (int) ($item['id'] ?? 0);
        if ($itemId <= 0) {
            return;
        }

        $qty = (float) ($item['qtyOnHand'] ?? 0);
        $reorderPoint = (float) ($item['reorderPoint'] ?? 0);
        $name = (string) ($item['name'] ?? 'Item');
        $sku = (string) ($item['sku'] ?? '');

        if ($qty > $reorderPoint) {
            DB::table('inventory_reorder_alerts')
                ->where('item_id', $itemId)
                ->where('status', 'open')
                ->update(['status' => 'resolved', 'resolved_at' => Carbon::now()]);

            return;
        }

        $exists = DB::table('inventory_reorder_alerts')
            ->where('item_id', $itemId)
            ->where('status', 'open')
            ->exists();

        if ($exists) {
            return;
        }

        $message = $name.' ('.$sku.') is low on stock: '.$qty.' left, reorder point is '.$reorderPoint.'.';

        DB::table('inventory_reorder_alerts')->insert([
            'item_id' => $itemId,
            'status' => 'open',
            'qty_snapshot' => $qty,
            'reorder_point_snapshot' => $reorderPoint,
            'message' => mb_substr($message, 0, 255),
            'created_at' => Carbon::now(),
        ]);

        if (class_exists(NotificationJobQueueService::class)) {
            (new NotificationJobQueueService)->dispatch('inventory_low_stock', [
                'itemId' => $itemId,
                'itemName' => $name,
                'sku' => $sku,
                'qtyOnHand' => $qty,
                'reorderPoint' => $reorderPoint,
                'message' => $message,
            ]);
        }
    }

    private function generatePoNumber(): string
    {
        $date = date('Ymd');
        $prefix = 'PO-'.$date.'-';

        $last = DB::table('purchase_orders')
            ->where('po_number', 'LIKE', $prefix.'%')
            ->orderBy('id', 'desc')
            ->value('po_number');

        $next = 1;
        if (is_string($last) && preg_match('/-(\d{3,})$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logInventoryActivity(
        string $description,
        string $subjectType,
        int|string $subjectId,
        array $properties = [],
        ?int $actorUserId = null
    ): void {
        try {
            if (function_exists('activity')) {
                $logger = activity()->forSubject($subjectType, (string) $subjectId);

                $resolvedActorUserId = $actorUserId ?? $this->resolveActorUserId();
                if ($resolvedActorUserId !== null && $resolvedActorUserId > 0) {
                    $logger->byUser($resolvedActorUserId);
                }

                if ($properties !== []) {
                    $logger->withProperties($properties);
                }

                $logger->log($description, 'inventory');
            }
        } catch (\Throwable $e) {
            error_log('[InventoryService] Activity logging failed: '.$e->getMessage());
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
