<?php

namespace App\Services;

class DiscountService
{
    public function calculate(
        array $cartItems,
        int $storeId
    ): string {

        /*
        |--------------------------------------------------------------------------
        | V1
        |--------------------------------------------------------------------------
        | No automatic discount.
        |
        | Future:
        | - Coupon codes
        | - Flat discount
        | - Percentage discount
        | - Buy X Get Y
        | - Free delivery
        | - Store-specific promotions
        */

        return '0.00';
    }
}