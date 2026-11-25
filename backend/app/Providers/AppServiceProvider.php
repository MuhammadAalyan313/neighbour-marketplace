<?php

namespace App\Providers;

// use App\Events\OrderCreated;
// use App\Listeners\ProcessOrderCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        // Event::listen(
        //     OrderCreated::class,
        //     ProcessOrderCreated::class
        // );
    }
}