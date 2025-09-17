<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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
                  ->orWhere('supplier', 'like', "%{$search}%")
                  ->orWhere('batch_number', 'like', "%{$search}%")
                  ->orWhere('lot_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('low_stock')) {
            $query->whereRaw('quantity <= minimum_quantity');
        }

        if ($request->has('expired')) {
            $query->where('expiry_date', '<', now());
        }

        if ($request->has('expiring_soon')) {
            $query->where('expiry_date', '>', now())
                  ->where('expiry_date', '<=', now()->addDays(30));
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
            'expiry_date' => 'nullable|date',
            'category' => 'required|in:reagents,consumables,equipment,pathology,cytology,ihc,other',
            'batch_number' => 'nullable|string|max:255',
            'lot_number' => 'nullable|string|max:255',
            'storage_conditions' => 'nullable|string|max:255',
            'hazard_level' => 'required|in:low,medium,high,critical',
            'temperature_range' => 'nullable|string|max:255',
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
        return response()->json($inventoryItem->load('updatedBy'));
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
            'category' => 'required|in:reagents,consumables,equipment,pathology,cytology,ihc,other',
            'batch_number' => 'nullable|string|max:255',
            'lot_number' => 'nullable|string|max:255',
            'storage_conditions' => 'nullable|string|max:255',
            'hazard_level' => 'required|in:low,medium,high,critical',
            'temperature_range' => 'nullable|string|max:255',
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
            'item' => $inventoryItem->load('updatedBy'),
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
            'item' => $inventoryItem->load('updatedBy'),
        ]);
    }

    public function getStats()
    {
        $stats = [
            'total_items' => InventoryItem::count(),
            'low_stock_items' => InventoryItem::whereRaw('quantity <= minimum_quantity')->count(),
            'out_of_stock_items' => InventoryItem::where('quantity', 0)->count(),
            'expired_items' => InventoryItem::where('expiry_date', '<', now())->count(),
            'expiring_soon_items' => InventoryItem::where('expiry_date', '>', now())
                ->where('expiry_date', '<=', now()->addDays(30))->count(),
            'total_value' => InventoryItem::sum(DB::raw('quantity * unit_price')),
            'by_status' => [
                'active' => InventoryItem::where('status', 'active')->count(),
                'low_stock' => InventoryItem::where('status', 'low_stock')->count(),
                'out_of_stock' => InventoryItem::where('status', 'out_of_stock')->count(),
                'expired' => InventoryItem::where('status', 'expired')->count(),
            ],
            'by_category' => [
                'reagents' => InventoryItem::where('category', 'reagents')->count(),
                'consumables' => InventoryItem::where('category', 'consumables')->count(),
                'equipment' => InventoryItem::where('category', 'equipment')->count(),
                'pathology' => InventoryItem::where('category', 'pathology')->count(),
                'cytology' => InventoryItem::where('category', 'cytology')->count(),
                'ihc' => InventoryItem::where('category', 'ihc')->count(),
                'other' => InventoryItem::where('category', 'other')->count(),
            ],
            'critical_alerts' => InventoryItem::where(function($query) {
                $query->where('quantity', 0)
                      ->orWhere('expiry_date', '<', now())
                      ->orWhereRaw('quantity <= minimum_quantity');
            })->count(),
        ];

        return response()->json($stats);
    }

    public function getAlerts()
    {
        $alerts = [];

        // Critical alerts - out of stock
        $outOfStockItems = InventoryItem::where('quantity', 0)->get();
        foreach ($outOfStockItems as $item) {
            $alerts[] = [
                'type' => 'out_of_stock',
                'severity' => 'error',
                'title' => 'Out of Stock',
                'message' => "{$item->name} is completely out of stock",
                'icon' => 'Error',
                'item_id' => $item->id,
                'item_name' => $item->name,
            ];
        }

        // Critical alerts - expired items
        $expiredItems = InventoryItem::where('expiry_date', '<', now())->get();
        foreach ($expiredItems as $item) {
            $alerts[] = [
                'type' => 'expired',
                'severity' => 'error',
                'title' => 'Expired Item',
                'message' => "{$item->name} has expired and should be removed from use",
                'icon' => 'Error',
                'item_id' => $item->id,
                'item_name' => $item->name,
            ];
        }

        // Warning alerts - low stock
        $lowStockItems = InventoryItem::whereRaw('quantity <= minimum_quantity')
            ->where('quantity', '>', 0)
            ->get();
        foreach ($lowStockItems as $item) {
            $alerts[] = [
                'type' => 'low_stock',
                'severity' => 'warning',
                'title' => 'Low Stock',
                'message' => "{$item->name} is running low ({$item->quantity} remaining)",
                'icon' => 'Warning',
                'item_id' => $item->id,
                'item_name' => $item->name,
            ];
        }

        // Warning alerts - expiring soon
        $expiringSoonItems = InventoryItem::where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays(30))
            ->get();
        foreach ($expiringSoonItems as $item) {
            $daysUntilExpiry = now()->diffInDays($item->expiry_date);
            $alerts[] = [
                'type' => 'expiring_soon',
                'severity' => 'warning',
                'title' => 'Expiring Soon',
                'message' => "{$item->name} expires in {$daysUntilExpiry} days",
                'icon' => 'Schedule',
                'item_id' => $item->id,
                'item_name' => $item->name,
            ];
        }

        return response()->json($alerts);
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