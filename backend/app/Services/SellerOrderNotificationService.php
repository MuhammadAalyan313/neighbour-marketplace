<?php

namespace App\Services;

use App\Models\Order;

class SellerOrderNotificationService
{
     

public function whatsappUrl(Order $order): ?string
{
    $order->loadMissing([
        'store.owner',
        'items',
    ]);

    $phone = preg_replace(
        '/\D+/',
        '',
        (string) $order->store?->owner?->phone
    );

    if (empty($phone)) {
        return null;
    }

    // Normalize Pakistani phone numbers.
    if (preg_match('/^03\d{9}$/', $phone)) {
        $phone = '92' . substr($phone, 1);
    } elseif (preg_match('/^3\d{9}$/', $phone)) {
        $phone = '92' . $phone;
    } elseif (preg_match('/^0092\d{10}$/', $phone)) {
        $phone = substr($phone, 2);
    }

    // International WhatsApp number validation.
    if (! preg_match('/^[1-9]\d{7,14}$/', $phone)) {
        return null;
    }

    return 'https://wa.me/'
        . $phone
        . '?text='
        . rawurlencode(
            $this->buildMessage($order)
        );
}


private function buildMessage(Order $order): string
{
    $items = $order->items
        ->map(function ($item) {
            return $item->product_title
                . ' x'
                . $item->quantity
                . ' = '
                . $item->subtotal;
        })
        ->implode("\n");

    $fulfillment = $order->fulfillment_type instanceof \BackedEnum
        ? $order->fulfillment_type->value
        : $order->fulfillment_type;

    return "New Marketplace Order\n\n"
        . "Order: {$order->order_number}\n"
        . "Buyer: {$order->buyer_name}\n"
        . "House: {$order->buyer_house_number}\n"
        . "Items:\n{$items}\n\n"
        . "Total: {$order->total_amount}\n"
        . "Fulfillment: {$fulfillment}\n"
        . "Payment: {$order->payment_method}";
}



public function buyerReceiptWhatsappUrl(
    Order $order
): ?string {
    // Explicit buyer number: default is now seller.
    if (empty($order->buyer_phone)) {
        return null;
    }

    return app(OrderReceiptService::class)
        ->whatsappUrl(
            $order,
            $order->buyer_phone
        );
}



}