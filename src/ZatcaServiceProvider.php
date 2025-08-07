<?php

namespace Hazem\Zatca;

use Illuminate\Support\ServiceProvider;
use Hazem\Zatca\Services\{
    DeviceRegistrationService,
    CSRGenerator,
    ZatcaAPI
};

class ZatcaServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register main ZATCA service
        $this->app->singleton('zatca', function ($app) {
            return new ZatcaService(config('zatca.live', false));
        });

        // Register device service
        $this->app->singleton('zatca.device', function ($app) {
            $live = config('zatca.live', false);

            return new DeviceRegistrationService(
                new CSRGenerator([], $live),
                new ZatcaAPI($live),
                $live
            );
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/zatca.php' => config_path('zatca.php'),
            __DIR__.'/../database/migrations' => database_path('migrations')
        ], 'zatca');

        $this->mergeConfigFrom(
            __DIR__.'/../config/zatca.php', 'zatca'
        );

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
