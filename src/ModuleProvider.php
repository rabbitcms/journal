<?php
declare(strict_types = 1);
namespace RabbitCMS\Journal;

use Illuminate\Support\ServiceProvider;
use RabbitCMS\Journal\Listeners\DBEventsListener;

/**
 * Class ModuleProvider.
 */
class ModuleProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(DBEventsListener::class);
        $this->app->make('events')->subscribe(DBEventsListener::class);
    }
}
