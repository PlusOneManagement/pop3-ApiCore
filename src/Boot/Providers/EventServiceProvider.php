<?php

namespace Core\Boot\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected function discoverEventsWithin()
    {
        return [
            $this->app->path('Listeners'),
            config('modules.paths.modules') ?: base_path('modules'),
        ];
    }
}
