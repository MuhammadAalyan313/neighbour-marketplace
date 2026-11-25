<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
}