<?php

namespace App\Enums;

enum NotificationType: string
{
    case ORDER_CREATED = 'order_created';
    case ORDER_STATUS_UPDATED = 'order_status_updated';
}