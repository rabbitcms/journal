<?php
declare(strict_types = 1);
namespace RabbitCMS\Journal;

use ABC\Modules\Journal\Listeners\DBEventsListener;
use Illuminate\Support\ServiceProvider;

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
