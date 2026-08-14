<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class InventoryConsumptionService
{
    public function deductForCompletedOrder(Order $order): int
    {
        $order->loadMissing('vendor.inventorySettings');
        $vendor = $order->vendor;

        if (! $vendor || ! $this->isEnabled($vendor)) {
            return 0;
        }

        return DB::transaction(function () use ($order, $vendor): int {
            $orderIsCompleted = in_array($order->status, Order::COMPLETED_STATUSES, true);
            $cartItems = CartItem::query()
                ->whereNull('inventory_deducted_at')
                ->when(! $orderIsCompleted, function ($query) {
                    $query->where(function ($completedItemQuery) {
                        $completedItemQuery
                            ->whereNotNull('served_at')
                            ->orWhereNotNull('picked_up_at');
                    });
                })
                ->where(function ($query) use ($order) {
                    $query->where('order_id', $order->id)
                        ->orWhereJsonContains('shared_order_ids', $order->id);
                })
                ->with(['menuItem.recipeIngredients'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $movementCount = 0;
            foreach ($cartItems as $cartItem) {
                $movementCount += $this->deductCartItem($vendor, $order, $cartItem);
                $cartItem->update(['inventory_deducted_at' => now()]);
            }

            return $movementCount;
        });
    }

    private function deductCartItem(Vendor $vendor, Order $order, CartItem $cartItem): int
    {
        $menuItem = $cartItem->menuItem;
        if (! $menuItem) {
            return 0;
        }

        $removedNames = collect($cartItem->removed_items ?? [])
            ->map(fn ($entry) => is_array($entry) ? ($entry['name'] ?? '') : $entry)
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn ($name) => mb_strtolower(trim($name)))
            ->all();
        $movementCount = 0;

        foreach ($menuItem->recipeIngredients->sortBy('inventory_item_id') as $recipeIngredient) {
            $inventoryItem = InventoryItem::query()
                ->whereKey($recipeIngredient->inventory_item_id)
                ->where('vendor_id', $vendor->id)
                ->where('track_stock', true)
                ->lockForUpdate()
                ->first();

            if (! $inventoryItem || in_array(mb_strtolower($inventoryItem->name), $removedNames, true)) {
                continue;
            }

            if (InventoryStockMovement::query()
                ->where('cart_item_id', $cartItem->id)
                ->where('inventory_item_id', $inventoryItem->id)
                ->exists()) {
                continue;
            }

            $perServing = $this->convertQuantity(
                (float) $recipeIngredient->quantity,
                (string) $recipeIngredient->unit,
                (string) $inventoryItem->unit,
            );
            if ($perServing === null) {
                report(new \RuntimeException(
                    "Cannot convert recipe unit {$recipeIngredient->unit} to inventory unit {$inventoryItem->unit} "
                    ."for inventory item {$inventoryItem->id}."
                ));

                continue;
            }

            $requestedDeduction = round($perServing * (int) $cartItem->quantity, 2);
            if ($requestedDeduction <= 0) {
                continue;
            }

            $quantityBefore = (float) $inventoryItem->quantity;
            $quantityAfter = round($quantityBefore - $requestedDeduction, 2);
            if (! $this->allowsNegativeStock($vendor)) {
                $quantityAfter = max(0, $quantityAfter);
            }

            $actualChange = round($quantityAfter - $quantityBefore, 3);
            if ($actualChange === 0.0) {
                continue;
            }

            $inventoryItem->update(['quantity' => $quantityAfter]);
            InventoryStockMovement::create([
                'vendor_id' => $vendor->id,
                'inventory_item_id' => $inventoryItem->id,
                'order_id' => $order->id,
                'cart_item_id' => $cartItem->id,
                'type' => 'order',
                'source' => 'Order '.($order->order_number ?: $order->order_public_id ?: '#'.$order->id),
                'quantity_change' => $actualChange,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'actor_name' => 'Auto',
                'note' => "{$menuItem->name} × {$cartItem->quantity}",
            ]);
            $movementCount++;
        }

        return $movementCount;
    }

    private function isEnabled(Vendor $vendor): bool
    {
        $settings = $vendor->inventorySettings;
        $stored = $settings?->settings ?? [];
        $general = is_array($stored['general'] ?? null) ? $stored['general'] : [];

        $trackingEnabled = $general['enableInventoryTracking'] ?? true;
        $deductionEnabled = $general['enableAutoStockDeduction']
            ?? $settings?->link_menu_items
            ?? true;

        return (bool) $trackingEnabled && (bool) $deductionEnabled;
    }

    private function allowsNegativeStock(Vendor $vendor): bool
    {
        $stored = $vendor->inventorySettings?->settings ?? [];
        $general = is_array($stored['general'] ?? null) ? $stored['general'] : [];

        return (bool) ($general['allowNegativeStock'] ?? false);
    }

    private function convertQuantity(float $quantity, string $fromUnit, string $toUnit): ?float
    {
        $from = $this->unitDefinition($fromUnit);
        $to = $this->unitDefinition($toUnit);

        if ($from['normalized'] === $to['normalized']) {
            return $quantity;
        }
        if ($from['family'] === null || $from['family'] !== $to['family']) {
            return null;
        }

        return ($quantity * $from['factor']) / $to['factor'];
    }

    /** @return array{normalized: string, family: ?string, factor: float} */
    private function unitDefinition(string $unit): array
    {
        $normalized = mb_strtolower(trim($unit));
        $definitions = [
            'g' => ['mass', 1.0],
            'gram' => ['mass', 1.0],
            'grams' => ['mass', 1.0],
            'kg' => ['mass', 1000.0],
            'kilogram' => ['mass', 1000.0],
            'kilograms' => ['mass', 1000.0],
            'ml' => ['volume', 1.0],
            'milliliter' => ['volume', 1.0],
            'milliliters' => ['volume', 1.0],
            'l' => ['volume', 1000.0],
            'liter' => ['volume', 1000.0],
            'liters' => ['volume', 1000.0],
            'piece' => ['count', 1.0],
            'pieces' => ['count', 1.0],
            'pc' => ['count', 1.0],
            'pcs' => ['count', 1.0],
            'each' => ['count', 1.0],
        ];

        [$family, $factor] = $definitions[$normalized] ?? [null, 1.0];

        return compact('normalized', 'family', 'factor');
    }
}
