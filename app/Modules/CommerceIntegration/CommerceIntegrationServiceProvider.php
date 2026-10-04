<?php

namespace App\Modules\CommerceIntegration;

use App\Modules\CommerceIntegration\Commands\ExpireReservationsCommand;
use App\Modules\CommerceIntegration\Services\CommerceCatalogService;
use App\Modules\CommerceIntegration\Services\InventoryAvailabilityService;
use App\Modules\CommerceIntegration\Services\InventoryConfirmationService;
use App\Modules\CommerceIntegration\Services\InventoryMovementService;
use App\Modules\CommerceIntegration\Services\InventoryReservationService;
use App\Modules\CommerceIntegration\Services\MappingRegistryService;
use Illuminate\Support\ServiceProvider;

class CommerceIntegrationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/commerce.php', 'commerce');

        $this->app->singleton(InventoryAvailabilityService::class);
        $this->app->singleton(InventoryMovementService::class);
        $this->app->singleton(InventoryReservationService::class);
        $this->app->singleton(InventoryConfirmationService::class);
        $this->app->singleton(CommerceCatalogService::class);
        $this->app->singleton(MappingRegistryService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load API routes
        if (file_exists(__DIR__ . '/Routes/commerce_api.php')) {
            $this->loadRoutesFrom(__DIR__ . '/Routes/commerce_api.php');
        }

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireReservationsCommand::class,
            ]);
        }
    }
}
