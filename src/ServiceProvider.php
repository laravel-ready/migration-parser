<?php

namespace LaravelReady\MigrationParser;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

use LaravelReady\MigrationParser\Services\MigrationParserService;

final class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap of package services
     *
     * @return void
     */
    public function boot(Router $router): void
    {
        $this->bootPublishes();

    }

    /**
     * Register any application services
     *
     * @return void
     */
    public function register(): void
    {
        // package config file
        $this->mergeConfigFrom(__DIR__ . '/../config/migration-parser.php', 'migration-parser');

        // register package service
        $this->app->singleton('migration-parser', function () {
            return new MigrationParserService();
        });
    }

    /**
     * Publishes resources on boot
     *
     * @return void
     */
    private function bootPublishes(): void
    {
        // package configs
        $this->publishes([
            __DIR__ . '/../config/migration-parser.php' => $this->app->configPath('migration-parser.php'),
        ], 'migration-parser-config');

    }

}
