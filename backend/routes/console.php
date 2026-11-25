
<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Notification Outbox Dispatcher
|--------------------------------------------------------------------------
| Pending notifications ko queue mein dispatch karega.
*/

Schedule::command('notifications:dispatch-pending')
    ->everyMinute()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Database Queue Worker
|--------------------------------------------------------------------------
| Queued notification jobs process karega.
| Shared hosting ke liye bounded worker.
*/

Schedule::command(
    'queue:work database --stop-when-empty --max-time=40 --tries=5'
)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('subscriptions:expire')
    ->hourly()
    ->withoutOverlapping();

    Schedule::command('otp:cleanup')
    ->daily()
    ->withoutOverlapping();

    Schedule::command('orders:expire-unpaid')
    ->everyFiveMinutes()
    ->withoutOverlapping();