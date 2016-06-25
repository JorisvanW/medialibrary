<?php

namespace CipeMotion\Medialibrary;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use CipeMotion\Medialibrary\Entities\File;
use CipeMotion\Medialibrary\Observers\FileObserver;

class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../resources/config/medialibrary.php' => config_path('medialibrary.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../resources/migrations/' => database_path('migrations'),
        ], 'migrations');

        $this->attachObservers();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../resources/config/medialibrary.php', 'medialibrary');
    }

    /**
     * Attach model observers.
     */
    protected function attachObservers()
    {
        File::observe(new FileObserver);
    }
}
