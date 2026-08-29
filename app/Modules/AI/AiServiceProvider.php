<?php

namespace App\Modules\AI;

use App\Modules\AI\Services\AiManager;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/ai.php', 'ai');

        $this->app->singleton(AiManager::class, function () {
            return new AiManager();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load module routes
        if (file_exists(__DIR__ . '/Routes/ai.php')) {
            $this->loadRoutesFrom(__DIR__ . '/Routes/ai.php');
        }
    }
}
