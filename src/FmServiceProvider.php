<?php

declare(strict_types=1);

namespace VmEngine\Fm;

use Illuminate\Support\ServiceProvider;
use VmEngine\Fm\Console\Commands\FmSetup;
use VmEngine\Synapse\Traits\AutoRegistersComponents;

class FmServiceProvider extends ServiceProvider
{
    use AutoRegistersComponents;

    public function register(): void
    {
        $this->registerComponents();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fm');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'fm');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([FmSetup::class]);
        }
    }
}
