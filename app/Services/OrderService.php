<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * OrderService
 *
 * Handles checkout, stock deduction, and order tracking for products.
 */
class OrderService
{
    /** @var string[] */
    private const STATUSES = [
        'pending',
        'confirmed',
        'preparing',
        'ready_for_pickup',
        'out_for_delivery',
        'completed',
        'cancelled',
    ];

    /** @var array<string, string[]> */
    private const STATUS_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready_for_pickup', 'out_for_delivery', 'cancelled'],
        'ready_for_pickup' => ['completed', 'cancelled'],
        'out_for_delivery' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    /** @var array<string, string[]> */
    private const FULFILLMENT_ALLOWED_STATUSES = [
        'courier' => ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'completed', 'cancelled'],
        'walk_in' => ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'completed', 'cancelled'],
    ];

    /** @var string[] */
    private const PAYMENT_STATUSES = ['unpaid', 'paid', 'cod'];

    /**
     * Create an order and atomically deduct stock.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, ?int $userId = null): array
    {
        $items = $data['items'] ?? [];
        if (! is_array($items) || count($items) === 0) {
            throw new RuntimeException('At least one cart item is required.', 422);
        }

        $customerName = trim((string) ($data['customerName'] ?? ''));
        $customerEmail = strtolower(trim((string) ($data['customerEmail'] ?? '')));
        $customerPhone = trim((string) ($data['customerPhone'] ?? ''));
        $fulfillment = strtolower(trim((string) ($data['fulfillmentType'] ?? 'courier')));

        if ($customerName === '' || $customerEmail === '' || $customerPhone === '') {
            throw new RuntimeException('Customer name, email, and phone are required.', 422);
        }
        if (! filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Customer email is invalid.', 422);
        }
        if (! in_array($fulfillment, ['courier', 'walk_in'], true)) {
            throw new RuntimeException('Invalid fulfillment type.', 422);
        }

        $address = trim((string) ($data['deliveryAddress'] ?? ''));
        $city = trim((string) ($data['deliveryCity'] ?? ''));
        $province = trim((string) ($data['deliveryProvince'] ?? ''));
        $postalCode = trim((string) ($data['deliveryPostalCode'] ?? ''));
        if ($fulfillment === 'courier' && $address === '') {
            throw new RuntimeException('Delivery address is required for courier orders.', 422);
        }

        $shippingFee = max(0.0, (float) ($data['shippingFee'] ?? ($fulfillment === 'courier' ? 150 : 0)));
        $notes = trim((string) ($data['notes'] ?? ''));

        DB::beginTransaction();

        try {
            $lineItems = [];
            $subtotal = 0.0;

            foreach ($items as $rawItem) {
                if (! is_array($rawItem)) {
                    throw new RuntimeException('Invalid cart item payload.', 422);
                }

                $productIdRaw = (string) ($rawItem['productId'] ?? '');
                if ($productIdRaw === '') {
                    throw new RuntimeException('Each item must include a productId.', 422);
                }

                // Assuming ProductService is also migrated
                $productId = (new ProductService)->resolveId($productIdRaw);

                $quantity = (int) ($rawItem['quantity'] ?? 1);
                if ($quantity < 1) {
                    throw new RuntimeException('Quantity must be at least 1.', 422);
                }

                $productRow = $this->dbLockProduct($productId);
                if ((int) ($productRow['is_active'] ?? 0) !== 1) {
                    throw new RuntimeException('One of the selected products is no longer available.', 409);
                }

                $variationId = isset($rawItem['variationId']) && $rawItem['variationId'] !== null
                    ? (int) $rawItem['variationId']
                    : null;

                $unitPrice = (float) ($productRow['price'] ?? 0);
                $variationName = '';
                $trackStock = (int) ($productRow['track_stock'] ?? 1) === 1;
                $stockQty = (int) ($productRow['stock_qty'] ?? 0);

                if ($variationId !== null) {
                    $variation = $this->dbLockVariation($productId, $variationId);
                    $variationName = (string) ($variation['name'] ?? '');
                    $variationPrice = trim((string) ($variation['price'] ?? ''));
                    if ($variationPrice !== '' && is_numeric($variationPrice)) {
                        $unitPrice = (float) $variationPrice;
                    }
                    $trackStock = (int) ($variation['track_stock'] ?? 1) === 1;
                    $stockQty = (int) ($variation['stock_qty'] ?? 0);
                }

                if ($trackStock && $stockQty < $quantity) {
                    throw new RuntimeException('Insufficient stock for one or more items.', 409);
                }

                if ($trackStock) {
                    if ($variationId !== null) {
                        $this->dbDeductVariationStock($variationId, $quantity);
                    } else {
                        $this->dbDeductProductStock($productId, $quantity);
                    }
                }

                $lineSubtotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineSubtotal;

                $lineItems[] = [
                    'product_id' => $productId,
                    'variation_id' => $variationId,
                    'product_name' => (string) ($productRow['name'] ?? ''),
                    'variation_name' => $variationName,
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'quantity' => $quantity,
                    'subtotal' => number_format($lineSubtotal, 2, '.', ''),
                ];
            }

            $orderNumber = $this->generateOrderNumber();
            $total = round($subtotal + $shippingFee, 2);

            $orderId = DB::table('product_orders')->insertGetId([
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'fulfillment_type' => $fulfillment,
                'delivery_address' => $address !== '' ? $address : null,
                'delivery_city' => $city,
                'delivery_province' => $province,
                'delivery_postal_code' => $postalCode,
                'status' => 'pending',
                'payment_status' => $fulfillment === 'courier' ? 'unpaid' : 'cod',
                'courier_name' => '',
                'tracking_number' => '',
                'notes' => $notes !== '' ? $notes : null,
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'shipping_fee' => number_format($shippingFee, 2, '.', ''),
                'total_amount' => number_format($total, 2, '.', ''),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            foreach ($lineItems as &$line) {
                $line['order_id'] = $orderId;
            }
            unset($line);

            DB::table('product_order_items')->insert($lineItems);

            DB::commit();

            $order = $this->getById($orderId, $userId, $userId === null ? null : true);

            $this->logOrderActivity(
                $order,
                'ORDER_CREATED', // Replace with proper ActivityEvents if needed
                [
                    'fulfillmentType' => $fulfillment,
                    'itemsCount' => count($lineItems),
                    'subtotal' => $subtotal,
                    'shippingFee' => $shippingFee,
                    'totalAmount' => $total,
                ],
                $userId
            );

            if (class_exists(NotificationJobQueueService::class)) {
                (new NotificationJobQueueService)->dispatch('order_created', [
                    'order' => $order,
                ]);
            }

            return $order;
        } catch (Throwable $e) {
            DB::rollBack();
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Failed to place order: '.$e->getMessage(), 500, $e);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listMine(int $userId): array
    {
        $rows = DB::table('product_orders')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $rows->map(fn ($row) => $this->mapOrderRow((array) $row, $this->fetchItems((int) $row->id)))->toArray();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{orders: array<int, array<string, mixed>>, total: int, page: int, pageSize: int}
     */
    public function listAll(array $filters = [], int $pageSize = 25, int $page = 1): array
    {
        $pageSize = max(1, min(100, $pageSize));
        $page = max(1, $page);

        $query = DB::table('product_orders');

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        $paymentStatus = trim((string) ($filters['paymentStatus'] ?? ''));
        if ($paymentStatus !== '' && in_array($paymentStatus, self::PAYMENT_STATUSES, true)) {
            $query->where('payment_status', $paymentStatus);
        }

        $fulfillmentType = trim((string) ($filters['fulfillmentType'] ?? ''));
        if ($fulfillmentType !== '' && in_array($fulfillmentType, ['courier', 'walk_in'], true)) {
            $query->where('fulfillment_type', $fulfillmentType);
        }

        $search = trim((string) ($filters['query'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', '%'.$search.'%')
                    ->orWhere('customer_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('customer_email', 'LIKE', '%'.$search.'%')
                    ->orWhere('customer_phone', 'LIKE', '%'.$search.'%');
            });
        }

        $createdFrom = trim((string) ($filters['createdFrom'] ?? ''));
        if ($createdFrom !== '') {
            $query->whereDate('created_at', '>=', $createdFrom);
        }

        $createdTo = trim((string) ($filters['createdTo'] ?? ''));
        if ($createdTo !== '') {
            $query->whereDate('created_at', '<=', $createdTo);
        }

        $total = $query->count();

        $rows = $query->orderBy('created_at', 'desc')
            ->forPage($page, $pageSize)
            ->get();

        $orders = $rows->map(fn ($row) => $this->mapOrderRow((array) $row, $this->fetchItems((int) $row->id)))->toArray();

        return ['orders' => $orders, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    /**
     * @return array<string, mixed>
     */
    public function getById(int $id, ?int $requestingUserId = null, ?bool $mustOwn = null): array
    {
        $row = DB::table('product_orders')->where('id', $id)->first();
        if (! $row) {
            throw new RuntimeException('Order not found.', 404);
        }

        if ($mustOwn === true) {
            $ownerId = (int) ($row->user_id ?? 0);
            if ($requestingUserId === null || $ownerId !== $requestingUserId) {
                throw new RuntimeException('You are not authorized to view this order.', 403);
            }
        }

        return $this->mapOrderRow((array) $row, $this->fetchItems((int) $row->id));
    }

    /** @return array<string, mixed> */
    public function updateStatus(int $id, string $status): array
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('Invalid order status.', 422);
        }

        $order = $this->getById($id);
        $currentStatus = (string) ($order['status'] ?? 'pending');
        $fulfillmentType = (string) ($order['fulfillmentType'] ?? 'courier');

        $allowedForFulfillment = self::FULFILLMENT_ALLOWED_STATUSES[$fulfillmentType] ?? self::FULFILLMENT_ALLOWED_STATUSES['courier'];
        if (! in_array($status, $allowedForFulfillment, true)) {
            throw new RuntimeException('This status is not valid for the selected fulfillment type.', 422);
        }

        if ($status === $currentStatus) {
            return $order;
        }

        $allowedNext = self::STATUS_TRANSITIONS[$currentStatus] ?? [];
        if (! in_array($status, $allowedNext, true)) {
            throw new RuntimeException('Invalid order status transition.', 422);
        }

        $affected = DB::table('product_orders')->where('id', $id)->update([
            'status' => $status,
            'updated_at' => Carbon::now(),
        ]);

        if ($affected === 0) {
            throw new RuntimeException('Order not found.', 404);
        }

        $updated = $this->getById($id);

        $this->logOrderActivity(
            $updated,
            'ORDER_STATUS_CHANGED',
            [
                'previousStatus' => $currentStatus,
                'nextStatus' => $status,
            ]
        );

        if (class_exists(NotificationJobQueueService::class)) {
            (new NotificationJobQueueService)->dispatch('order_status_changed', [
                'order' => $updated,
                'previousStatus' => $currentStatus,
            ]);
        }

        return $updated;
    }

    /** @return array<string, mixed> */
    public function updateTracking(int $id, string $courierName, string $trackingNumber): array
    {
        $affected = DB::table('product_orders')
            ->where('id', $id)
            ->update([
                'courier_name' => trim($courierName),
                'tracking_number' => trim($trackingNumber),
                'updated_at' => Carbon::now(),
            ]);

        if ($affected === 0) {
            throw new RuntimeException('Order not found.', 404);
        }

        $updated = $this->getById($id);

        $this->logOrderActivity(
            $updated,
            'ORDER_TRACKING_UPDATED',
            [
                'courierName' => trim($courierName),
                'trackingNumber' => trim($trackingNumber),
            ]
        );

        if (class_exists(NotificationJobQueueService::class)) {
            (new NotificationJobQueueService)->dispatch('order_tracking_updated', [
                'order' => $updated,
            ]);
        }

        return $updated;
    }

    /** @return array<string, mixed> */
    public function updatePaymentStatus(int $id, string $paymentStatus): array
    {
        $normalized = trim(strtolower($paymentStatus));
        if (! in_array($normalized, self::PAYMENT_STATUSES, true)) {
            throw new RuntimeException('Invalid payment status.', 422);
        }

        $affected = DB::table('product_orders')->where('id', $id)->update([
            'payment_status' => $normalized,
            'updated_at' => Carbon::now(),
        ]);

        if ($affected === 0) {
            throw new RuntimeException('Order not found.', 404);
        }

        $updated = $this->getById($id);

        $this->logOrderActivity(
            $updated,
            'ORDER_PAYMENT_STATUS_UPDATED',
            ['paymentStatus' => $normalized]
        );

        return $updated;
    }

    /** @return array<string, mixed> */
    private function dbLockProduct(int $productId): array
    {
        $row = DB::table('products')->where('id', $productId)->lockForUpdate()->first();
        if (! $row) {
            throw new RuntimeException('Product not found.', 404);
        }

        return (array) $row;
    }

    /** @return array<string, mixed> */
    private function dbLockVariation(int $productId, int $variationId): array
    {
        $row = DB::table('product_variations')
            ->where('id', $variationId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            throw new RuntimeException('Selected variation was not found.', 404);
        }

        return (array) $row;
    }

    private function dbDeductProductStock(int $productId, int $qty): void
    {
        DB::table('products')->where('id', $productId)->decrement('stock_qty', $qty);
    }

    private function dbDeductVariationStock(int $variationId, int $qty): void
    {
        DB::table('product_variations')->where('id', $variationId)->decrement('stock_qty', $qty);
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchItems(int $orderId): array
    {
        $rows = DB::table('product_order_items')->where('order_id', $orderId)->orderBy('id', 'asc')->get();

        return $rows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'productId' => (int) $row->product_id,
                'variationId' => isset($row->variation_id) && $row->variation_id !== null ? (int) $row->variation_id : null,
                'productName' => (string) ($row->product_name ?? ''),
                'variationName' => (string) ($row->variation_name ?? ''),
                'unitPrice' => (float) ($row->unit_price ?? 0),
                'quantity' => (int) ($row->quantity ?? 1),
                'subtotal' => (float) ($row->subtotal ?? 0),
            ];
        })->toArray();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function mapOrderRow(array $row, array $items): array
    {
        return [
            'id' => (int) $row['id'],
            'orderNumber' => (string) ($row['order_number'] ?? ''),
            'userId' => isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'customerName' => (string) ($row['customer_name'] ?? ''),
            'customerEmail' => (string) ($row['customer_email'] ?? ''),
            'customerPhone' => (string) ($row['customer_phone'] ?? ''),
            'fulfillmentType' => (string) ($row['fulfillment_type'] ?? 'courier'),
            'deliveryAddress' => isset($row['delivery_address']) ? (string) $row['delivery_address'] : null,
            'deliveryCity' => (string) ($row['delivery_city'] ?? ''),
            'deliveryProvince' => (string) ($row['delivery_province'] ?? ''),
            'deliveryPostalCode' => (string) ($row['delivery_postal_code'] ?? ''),
            'status' => (string) ($row['status'] ?? 'pending'),
            'paymentStatus' => (string) ($row['payment_status'] ?? 'unpaid'),
            'courierName' => (string) ($row['courier_name'] ?? ''),
            'trackingNumber' => (string) ($row['tracking_number'] ?? ''),
            'notes' => isset($row['notes']) ? (string) $row['notes'] : null,
            'subtotal' => (float) ($row['subtotal'] ?? 0),
            'shippingFee' => (float) ($row['shipping_fee'] ?? 0),
            'totalAmount' => (float) ($row['total_amount'] ?? 0),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
            'items' => $items,
        ];
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $properties
     */
    private function logOrderActivity(
        array $order,
        string $description,
        array $properties = [],
        ?int $explicitActorUserId = null
    ): void {
        try {
            if (! function_exists('activity')) {
                return;
            }

            $orderId = isset($order['id']) ? (int) $order['id'] : 0;
            if ($orderId <= 0) {
                return;
            }

            $logger = activity()->forSubject('product_orders', $orderId);

            $actorUserId = $explicitActorUserId;
            if ($actorUserId === null) {
                $actorUserId = isset($order['userId']) && $order['userId'] !== null ? (int) $order['userId'] : null;
            }
            if ($actorUserId === null) {
                $actorUserId = $this->resolveActorUserId();
            }
            if ($actorUserId !== null && $actorUserId > 0) {
                $logger->byUser($actorUserId);
            }

            $logger->withProperties($properties)->log($description, 'orders');
        } catch (Throwable $e) {
            error_log('[OrderService] Activity logging failed: '.$e->getMessage());
        }
    }

    private function resolveActorUserId(): ?int
    {
        try {
            $payload = Auth::user();
            $userId = (int) ($payload['sub'] ?? 0);

            return $userId > 0 ? $userId : null;
        } catch (Throwable) {
            return null;
        }
    }
}
