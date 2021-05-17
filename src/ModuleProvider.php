<?php

declare(strict_types=1);

namespace RabbitCMS\Journal;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use RabbitCMS\Journal\Subscribers\DBEventsSubscriber;

class ModuleProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->extend(Dispatcher::class, function (Dispatcher $events) {
            $events->subscribe(DBEventsSubscriber::class);

            return $events;
        });

    }
}
