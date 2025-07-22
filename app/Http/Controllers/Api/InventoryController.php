<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryItem::with('updatedBy');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('supplier', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('low_stock')) {
            $query->whereRaw('quantity <= minimum_quantity');
        }

        if ($request->has('expired')) {
            $query->where('expiry_date', '<', now());
        }

        $items = $query->latest()->paginate(15);

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:50',
            'quantity' => 'required|integer|min:0',
            'minimum_quantity' => 'required|integer|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'expiry_date' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['updated_by'] = $request->user()->id;
        $data['status'] = $this->determineStatus($data['quantity'], $data['minimum_quantity'], $data['expiry_date'] ?? null);

        $item = InventoryItem::create($data);

        return response()->json([
            'message' => 'Inventory item created successfully',
            'item' => $item->load('updatedBy'),
        ], 201);
    }

    public function show(InventoryItem $inventoryItem)
    {
        $inventoryItem->load('updatedBy');
        return response()->json($inventoryItem);
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:50',
            'quantity' => 'required|integer|min:0',
            'minimum_quantity' => 'required|integer|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'expiry_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['updated_by'] = $request->user()->id;
        $data['status'] = $this->determineStatus($data['quantity'], $data['minimum_quantity'], $data['expiry_date'] ?? null);

        $inventoryItem->update($data);

        return response()->json([
            'message' => 'Inventory item updated successfully',
            'item' => $inventoryItem->fresh()->load('updatedBy'),
        ]);
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        $inventoryItem->delete();

        return response()->json([
            'message' => 'Inventory item deleted successfully',
        ]);
    }

    public function adjustQuantity(Request $request, InventoryItem $inventoryItem)
    {
        $validator = Validator::make($request->all(), [
            'adjustment' => 'required|integer',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newQuantity = $inventoryItem->quantity + $request->adjustment;
        
        if ($newQuantity < 0) {
            return response()->json([
                'message' => 'Quantity cannot be negative',
            ], 422);
        }

        $inventoryItem->update([
            'quantity' => $newQuantity,
            'updated_by' => $request->user()->id,
            'status' => $this->determineStatus($newQuantity, $inventoryItem->minimum_quantity, $inventoryItem->expiry_date),
        ]);

        return response()->json([
            'message' => 'Quantity adjusted successfully',
            'item' => $inventoryItem->fresh()->load('updatedBy'),
        ]);
    }

    public function getStats()
    {
        $stats = [
            'total_items' => InventoryItem::count(),
            'low_stock_items' => InventoryItem::whereRaw('quantity <= minimum_quantity')->count(),
            'out_of_stock_items' => InventoryItem::where('quantity', 0)->count(),
            'expired_items' => InventoryItem::where('expiry_date', '<', now())->count(),
            'total_value' => InventoryItem::sum(DB::raw('quantity * unit_price')),
            'by_status' => [
                'active' => InventoryItem::where('status', 'active')->count(),
                'low_stock' => InventoryItem::where('status', 'low_stock')->count(),
                'out_of_stock' => InventoryItem::where('status', 'out_of_stock')->count(),
                'expired' => InventoryItem::where('status', 'expired')->count(),
            ],
        ];

        return response()->json($stats);
    }

    public function getLowStockItems()
    {
        $items = InventoryItem::whereRaw('quantity <= minimum_quantity')
            ->with('updatedBy')
            ->orderBy('quantity', 'asc')
            ->get();

        return response()->json($items);
    }

    public function getExpiredItems()
    {
        $items = InventoryItem::where('expiry_date', '<', now())
            ->with('updatedBy')
            ->orderBy('expiry_date', 'asc')
            ->get();

        return response()->json($items);
    }

    private function determineStatus($quantity, $minimumQuantity, $expiryDate = null)
    {
        // Check if expired
        if ($expiryDate && $expiryDate < now()) {
            return 'expired';
        }

        // Check if out of stock
        if ($quantity <= 0) {
            return 'out_of_stock';
        }

        // Check if low stock
        if ($quantity <= $minimumQuantity) {
            return 'low_stock';
        }

        return 'active';
    }

    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'required|exists:inventory_items,id',
            'items.*.quantity' => 'required|integer|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updatedItems = [];

        foreach ($request->items as $itemData) {
            $item = InventoryItem::find($itemData['id']);
            
            $updateData = [
                'quantity' => $itemData['quantity'],
                'updated_by' => $request->user()->id,
            ];

            if (isset($itemData['unit_price'])) {
                $updateData['unit_price'] = $itemData['unit_price'];
            }

            $updateData['status'] = $this->determineStatus(
                $itemData['quantity'],
                $item->minimum_quantity,
                $item->expiry_date
            );

            $item->update($updateData);
            $updatedItems[] = $item->load('updatedBy');
        }

        return response()->json([
            'message' => 'Items updated successfully',
            'items' => $updatedItems,
        ]);
    }
} 