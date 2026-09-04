<?php
declare(strict_types=1);

namespace Plugins\OshimBilling;

use Oshim\Ecosystem\PluginInterface;
use Oshim\Container\Container;
use Oshim\Http\Router\Router;

/**
 * 👑 OshimBilling Sovereign Plugin
 * 
 * WHY: Provides zero-dependency Stripe Billing integration.
 * Prevents the need to install the massive 10MB+ stripe-php SDK via Composer.
 */
class Plugin implements PluginInterface
{
    public function getName(): string
    {
        return 'oshim/billing';
    }

    public function getRequestedPermissions(): array
    {
        return ['network.outbound']; // Requires talking to Stripe APIs
    }

    public function register(Container $container): void
    {
        $container->singleton(StripeClient::class, function() {
            $secretKey = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_mock';
            return new StripeClient($secretKey);
        });
    }

    public function boot(Container $container): void
    {
        if ($container->has(Router::class)) {
            $router = $container->get(Router::class);
            // Setup a default webhook endpoint for developers to easily intercept
            $router->post('/oshim/billing/webhook', [WebhookController::class, 'handle']);
        }
    }
}
