<?php

namespace App\Mail;

use App\Models\InventoryPurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InventoryPurchaseOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public InventoryPurchaseOrder $purchaseOrder) {}

    public function build(): self
    {
        $purchaseOrder = $this->purchaseOrder->loadMissing(['vendor', 'inventoryItem']);
        $vendorName = e($purchaseOrder->vendor?->restaurant_name ?: $purchaseOrder->vendor?->name ?: 'Tavlo vendor');
        $ingredientName = e($purchaseOrder->inventoryItem?->name ?: 'Inventory item');
        $quantity = e(rtrim(rtrim(number_format((float) $purchaseOrder->quantity, 3, '.', ''), '0'), '.'));
        $unit = e($purchaseOrder->unit);
        $unitCost = e(number_format((float) $purchaseOrder->unit_cost, 2, '.', ''));
        $totalCost = e(number_format((float) $purchaseOrder->quantity * (float) $purchaseOrder->unit_cost, 2, '.', ''));
        $currency = e($purchaseOrder->currency);
        $deliveryDate = e($purchaseOrder->estimated_delivery_date?->toDateString() ?: 'Not specified');
        $notes = $purchaseOrder->notes ? nl2br(e($purchaseOrder->notes)) : 'None';

        return $this
            ->subject("Purchase order {$purchaseOrder->purchase_order_public_id} from {$vendorName}")
            ->html(<<<HTML
                <h2>Purchase Order {$purchaseOrder->purchase_order_public_id}</h2>
                <p><strong>Restaurant:</strong> {$vendorName}</p>
                <p><strong>Ingredient:</strong> {$ingredientName}</p>
                <p><strong>Quantity:</strong> {$quantity} {$unit}</p>
                <p><strong>Unit cost:</strong> {$currency} {$unitCost}</p>
                <p><strong>Total:</strong> {$currency} {$totalCost}</p>
                <p><strong>Estimated delivery:</strong> {$deliveryDate}</p>
                <p><strong>Notes:</strong><br>{$notes}</p>
            HTML);
    }
}
