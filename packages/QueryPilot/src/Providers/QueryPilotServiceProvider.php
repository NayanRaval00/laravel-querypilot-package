<?php

namespace QueryPilot\Providers;

use Illuminate\Support\ServiceProvider;
use QueryPilot\QueryPilotAgent;

class QueryPilotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config with app config
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/querypilot.php',
            'querypilot'
        );

        // Bind the agent into the container
        $this->app->bind(QueryPilotAgent::class, fn() => new QueryPilotAgent());
    }

    public function boot(): void
    {
        // Let users publish the config with: php artisan vendor:publish --tag=querypilot-config
        $this->publishes([
            __DIR__ . '/../../config/querypilot.php' => config_path('querypilot.php'),
        ], 'querypilot-config');
    }
}
