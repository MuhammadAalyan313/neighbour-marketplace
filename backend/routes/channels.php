<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'seller.{sellerId}',
    function ($user, $sellerId) {
        return $user->is_active
            && $user->isSeller()
            && (int) $user->id === (int) $sellerId;
    }
);

Broadcast::channel(
    'buyer.{buyerId}',
    function ($user, $buyerId) {
        return $user->is_active
            && $user->isBuyer()
            && (int) $user->id === (int) $buyerId;
    }
);
