<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case WHATSAPP = 'whatsapp';
    case PUSH = 'push';
    case EMAIL = 'email';
}