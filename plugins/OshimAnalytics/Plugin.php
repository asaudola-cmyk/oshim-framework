<?php
declare(strict_types=1);

namespace Plugins\OshimAnalytics;

use Oshim\Ecosystem\PluginInterface;
use Oshim\Container\Container;
use Oshim\Http\Router\Router;
use Oshim\Database\Schema\Schema;
use Oshim\Database\Schema\Blueprint;

/**
 * 👑 OshimAnalytics Sovereign Plugin
 * 
 * WHY: Provides ultra-fast native traffic analytics without relying on Google Analytics.
 * It strictly tracks pageviews in the local database.
 */
class Plugin implements PluginInterface
{
    public function getName(): string
    {
        return 'oshim/analytics';
    }

    public function getRequestedPermissions(): array
    {
        return ['database.write', 'database.read'];
    }

    public function register(Container $container): void
    {
        // Bind the Analytics service so developers can use it manually if they want
        $container->singleton(AnalyticsTracker::class, function() {
            return new AnalyticsTracker();
        });
    }

    public function boot(Container $container): void
    {
        // 1. Ensure the tracking table exists. 
        // Edge Case: If database connection fails, catch it gracefully to not break the app.
        try {
            if (!Schema::connection()->hasTable('oshim_analytics_events')) {
                Schema::connection()->create('oshim_analytics_events', function (Blueprint $table) {
                    $table->id();
                    $table->string('path');
                    $table->string('method');
                    $table->string('ip_address');
                    $table->text('user_agent')->nullable();
                    $table->float('execution_time_ms')->default(0);
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            // Native logging if DB setup fails
            error_log("OshimAnalytics: Failed to verify/create analytics table - " . $e->getMessage());
        }

        // 2. Register Global Middleware to intercept all requests
        if ($container->has(Router::class)) {
            $router = $container->get(Router::class);
            $router->use(new AnalyticsMiddleware($container->get(AnalyticsTracker::class)));
        }
    }
}
