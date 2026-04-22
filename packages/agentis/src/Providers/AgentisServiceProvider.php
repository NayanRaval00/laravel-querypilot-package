<?php

namespace Agentis\Providers;

use Agentis\AgentisAgent;
use Illuminate\Support\ServiceProvider;

class AgentisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config with app config
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/agentis.php',
            'agentis'
        );

        // Bind the agent into the container
        $this->app->bind(AgentisAgent::class, fn() => new AgentisAgent());
    }

    public function boot(): void
    {
        // Let users publish the config with: php artisan vendor:publish --tag=agentis-config
        $this->publishes([
            __DIR__ . '/../../config/agentis.php' => config_path('agentis.php'),
        ], 'agentis-config');
    }
}
