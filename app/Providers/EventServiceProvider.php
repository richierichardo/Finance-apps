<?php

namespace App\Providers;

use App\Events\TransactionCreated;
use App\Events\TransactionDeleted;
use App\Events\TransactionUpdated;
use App\Listeners\InvalidateDashboardCache;
use App\Listeners\UpdateWalletBalance;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        TransactionCreated::class => [
            UpdateWalletBalance::class,
            InvalidateDashboardCache::class,
        ],
        TransactionUpdated::class => [
            UpdateWalletBalance::class,
            InvalidateDashboardCache::class,
        ],
        TransactionDeleted::class => [
            UpdateWalletBalance::class,
            InvalidateDashboardCache::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
