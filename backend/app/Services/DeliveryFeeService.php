<?php

namespace App\Services;

use App\Enums\FulfillmentType;

class DeliveryFeeService
{
    public function calculate(
        FulfillmentType $fulfillmentType,
        int $storeId
    ): string {

        if ($fulfillmentType === FulfillmentType::PICKUP) {
            return '0.00';
        }

        /*
        |--------------------------------------------------------------------------
        | V1
        |--------------------------------------------------------------------------
        | Seller delivery fee is currently zero.
        |
        | Future:
        | - Store-specific delivery fee
        | - Society zones
        | - Free delivery promotions
        | - Distance-based fee
        */

        return '0.00';
    }
}