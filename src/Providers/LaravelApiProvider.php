<?php

namespace NSRosenqvist\ApiToolkit\Providers;

use NSRosenqvist\ApiToolkit\Manager;
use Illuminate\Support\ServiceProvider;

class LaravelApiProvider extends ServiceProvider
{
    /**
     * Register manager in dependency container
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Manager::class, function ($app) {
            return new Manager();
        });
    }
}
