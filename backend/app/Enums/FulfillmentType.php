<?php

namespace App\Enums;

enum FulfillmentType: string
{
    case PICKUP = 'pickup';
    case SELLER_DELIVERY = 'seller_delivery';
}