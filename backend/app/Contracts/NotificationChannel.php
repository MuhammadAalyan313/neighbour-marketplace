<?php

namespace App\Contracts;

use App\Models\NotificationOutbox;

interface NotificationChannel
{
    public function send(
        NotificationOutbox $notification
    ): void;
}