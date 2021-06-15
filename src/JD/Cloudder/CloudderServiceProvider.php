<?php

namespace JD\Cloudder;

use Illuminate\Support\ServiceProvider;

/**
 * Class CloudderServiceProvider
 *
 * @package JD\Cloudder
 */
class CloudderServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = FALSE;

    /**
     * Bootstrap classes for packages.
     *
     * @return void
     */
    public function boot()
    {
        $source = realpath(__DIR__ . '/../../../config/cloudder.php');

        if (class_exists('Illuminate\Foundation\Application', FALSE))
        {
            $this->publishes([$source => config_path('cloudder.php')]);
        }
        $this->mergeConfigFrom($source, 'cloudder');

        $this->app['JD\Cloudder\Cloudder'] = function ($app)
        {
            return $app['cloudder'];
        };
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $app = $this->app;
        $this->app->singleton('cloudder', function() use ($app) {
            return new CloudinaryWrapper($app['config']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['cloudder'];
    }
}
