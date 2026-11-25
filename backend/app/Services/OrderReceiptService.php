<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;

class OrderReceiptService
{
    public function subject(Order $order): string
    {
        return "Order Receipt #{$order->order_number}";
    }

    public function body(Order $order): string
    {
        $order->loadMissing(['items', 'store']);

        $items = $order->items
            ->map(function ($item) {
                return sprintf(
                    '%s x%d - %s',
                    $item->product_title,
                    $item->quantity,
                    $item->subtotal
                );
            })
            ->implode("\n");

        return implode("\n", [
            'MARKETPLACE ORDER RECEIPT',
            '',
            "Order: {$order->order_number}",
            "Store: {$order->store?->store_name}",
            "Customer: {$order->buyer_name}",
            '',
            'ITEMS',
            $items,
            '',
            "Subtotal: {$order->subtotal}",
            "Discount: {$order->discount_amount}",
            "Delivery: {$order->delivery_fee}",
            "Platform Fee: {$order->platform_fee}",
            "Total: {$order->total_amount}",
            '',
            'Order Status: '.Str::headline(
                $order->status?->value ?? 'pending'
            ),
            'Payment Method: '.Str::headline(
                (string) $order->payment_method
            ),
            'Payment Status: '.Str::headline(
                $order->payment_status?->value ?? 'pending'
            ),
            '',
            'This receipt confirms your order details.',
            'It is not proof of payment unless payment is marked paid.',
            '',
            'Thank you for shopping with us.',
        ]);
    }


public function whatsappUrl(
    Order $order,
    ?string $phone = null
): ?string {
    /*
    |--------------------------------------------------------------------------
    | Resolve WhatsApp Recipient
    |--------------------------------------------------------------------------
    |
    | Explicit phone takes priority.
    | Otherwise send order details to this order's store owner.
    |
    */

    if ($phone === null) {
        $order->loadMissing('store.owner');

        $phone = $order->store?->owner?->phone;
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize Phone Number
    |--------------------------------------------------------------------------
    */

    $phone = preg_replace(
        '/\D+/',
        '',
        (string) $phone
    );

    if (empty($phone)) {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Pakistani Phone Number Normalization
    |--------------------------------------------------------------------------
    |
    | 03001234567  => 923001234567
    | 3001234567   => 923001234567
    | 923001234567 => 923001234567
    | +923001234567 => 923001234567
    |
    */

    if (preg_match('/^03\d{9}$/', $phone)) {
        $phone = '92' . substr($phone, 1);
    } elseif (preg_match('/^3\d{9}$/', $phone)) {
        $phone = '92' . $phone;
    } elseif (preg_match('/^0092\d{10}$/', $phone)) {
        $phone = substr($phone, 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Validate International Phone Number
    |--------------------------------------------------------------------------
    */

    if (! preg_match('/^[1-9]\d{7,14}$/', $phone)) {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Build Order-Specific WhatsApp Receipt
    |--------------------------------------------------------------------------
    |
    | Each order belongs to one store.
    | Multi-store checkout automatically generates
    | a separate URL for every store order.
    |
    */

    return 'https://wa.me/' . $phone
        . '?text=' . rawurlencode(
            $this->body($order)
        );
}

public function data(Order $order): array
{
    $order->loadMissing(['items', 'store']);

    return [
        'order_number' => $order->order_number,
        'store_name' => $order->store?->store_name,
        'buyer_name' => $order->buyer_name,

        'items' => $order->items->map(function ($item) {
            return [
                'product_title' => $item->product_title,
                'quantity' => (int) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'subtotal' => (string) $item->subtotal,
            ];
        })->values()->all(),

        'subtotal' => (string) $order->subtotal,
        'discount_amount' => (string) $order->discount_amount,
        'delivery_fee' => (string) $order->delivery_fee,
        'platform_fee' => (string) $order->platform_fee,
        'total_amount' => (string) $order->total_amount,

        'fulfillment_type' =>
            $order->fulfillment_type instanceof \BackedEnum
                ? $order->fulfillment_type->value
                : $order->fulfillment_type,

        'payment_method' => $order->payment_method,
        'payment_status' =>
            $order->payment_status?->value,

        'order_status' => $order->status?->value,
    ];
}

public function message(Order $order): string
{
    return $this->body($order);
}

}
