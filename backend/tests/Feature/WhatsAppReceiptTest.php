<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderReceiptService;

it('targets each order store owner and does not send to the buyer by default', function () {
    $ownerA = new User(['phone' => '03001234567']);
    $ownerB = new User(['phone' => '03007654321']);
    $storeA = new Store(['store_name' => 'Store A']);
    $storeB = new Store(['store_name' => 'Store B']);
    $storeA->setRelation('owner', $ownerA);
    $storeB->setRelation('owner', $ownerB);

    $receipt = app(OrderReceiptService::class);
    foreach ([[$storeA, '923001234567'], [$storeB, '923007654321']] as [$store, $number]) {
        $order = new Order([
            'order_number' => 'ORD-TEST', 'buyer_name' => 'Buyer',
            'buyer_phone' => '03009999999', 'subtotal' => '100.00',
            'discount_amount' => '0.00', 'delivery_fee' => '0.00',
            'platform_fee' => '0.00', 'total_amount' => '100.00',
            'payment_method' => 'cash',
        ]);
        $order->setRelation('store', $store);
        $order->setRelation('items', collect([new OrderItem([
            'product_title' => 'Milk', 'quantity' => 1, 'subtotal' => '100.00',
        ])]));
        $url = $receipt->whatsappUrl($order);
        expect($url)->toStartWith('https://wa.me/'.$number.'?text=');
        expect(urldecode($url))->toContain('ORD-TEST');
        expect($url)->not->toContain('923009999999');
    }
});

it('supports explicitly sending a buyer receipt to buyer phone', function () {
    $order = new Order([
        'buyer_phone' => '03009999999', 'order_number' => 'ORD-BUYER',
        'buyer_name' => 'Buyer', 'subtotal' => '0.00',
        'discount_amount' => '0.00', 'delivery_fee' => '0.00',
        'platform_fee' => '0.00', 'total_amount' => '0.00',
        'payment_method' => 'cash',
    ]);
    $order->setRelation('items', collect());
    $order->setRelation('store', new Store(['store_name' => 'Store']));
    expect(app(OrderReceiptService::class)->whatsappUrl($order, $order->buyer_phone))
        ->toStartWith('https://wa.me/923009999999?text=');
});
