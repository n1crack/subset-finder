<?php

namespace Ozdemir\SubsetFinder;

use Illuminate\Support\ServiceProvider;
use Ozdemir\SubsetFinder\Facades\SubsetFinder as SubsetFinderFacade;

class SubsetFinderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('subset-finder', function ($app) {
            return new SubsetFinder(
                collect(),
                new SubsetCollection(),
                SubsetFinderConfig::default()
            );
        });

        $this->app->alias('subset-finder', SubsetFinder::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration file if needed
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/subset-finder.php' => config_path('subset-finder.php'),
            ], 'subset-finder-config');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['subset-finder'];
    }
}
