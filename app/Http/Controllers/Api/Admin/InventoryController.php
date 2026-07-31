<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function itemList(Request $request)
    {
        $filters = $request->all();
        $items = $this->inventoryService->listItems($filters);

        return response()->json([
            'success' => true,
            'message' => 'Inventory items retrieved.',
            'data' => ['items' => $items],
        ]);
    }

    public function itemCreate(Request $request)
    {
        try {
            $item = $this->inventoryService->createItem($request->all(), $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Inventory item created.',
                'data' => ['item' => $item],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function itemUpdate(Request $request, int $id)
    {
        try {
            $item = $this->inventoryService->updateItem($id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Inventory item updated.',
                'data' => ['item' => $item],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function movementList(Request $request)
    {
        $limit = $request->query('limit') ? (int) $request->query('limit') : 100;
        $movements = $this->inventoryService->listMovements($limit);

        return response()->json([
            'success' => true,
            'message' => 'Inventory movements retrieved.',
            'data' => ['movements' => $movements],
        ]);
    }

    public function adjust(Request $request)
    {
        try {
            $movement = $this->inventoryService->adjustStock($request->all(), $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted.',
                'data' => ['movement' => $movement],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function alertList(Request $request)
    {
        $status = $request->query('status', 'active');
        $limit = $request->query('limit') ? (int) $request->query('limit') : 50;
        $alerts = $this->inventoryService->listLowStockAlerts((string) $status, $limit);

        return response()->json([
            'success' => true,
            'message' => 'Stock alerts retrieved.',
            'data' => ['alerts' => $alerts],
        ]);
    }

    public function supplierList(Request $request)
    {
        $suppliers = $this->inventoryService->listSuppliers();

        return response()->json([
            'success' => true,
            'message' => 'Suppliers retrieved.',
            'data' => ['suppliers' => $suppliers],
        ]);
    }

    public function supplierCreate(Request $request)
    {
        try {
            $supplier = $this->inventoryService->createSupplier($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Supplier created.',
                'data' => ['supplier' => $supplier],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function purchaseOrderList(Request $request)
    {
        $limit = $request->query('limit') ? (int) $request->query('limit') : 50;
        $orders = $this->inventoryService->listPurchaseOrders($limit);

        return response()->json([
            'success' => true,
            'message' => 'Purchase orders retrieved.',
            'data' => ['orders' => $orders],
        ]);
    }

    public function purchaseOrderCreate(Request $request)
    {
        try {
            $order = $this->inventoryService->createPurchaseOrder($request->all(), $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order created.',
                'data' => ['order' => $order],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function purchaseOrderStatus(Request $request, int $id)
    {
        try {
            $order = $this->inventoryService->updatePurchaseOrderStatus(
                $id,
                (string) $request->input('status', ''),
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Purchase order status updated.',
                'data' => ['order' => $order],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
