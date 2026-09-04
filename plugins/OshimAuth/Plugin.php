<?php
declare(strict_types=1);

namespace Plugins\OshimAuth;

use Oshim\Ecosystem\PluginInterface;
use Oshim\Container\Container;
use Oshim\Http\Router\Router;

class Plugin implements PluginInterface
{
    public function getName(): string
    {
        return 'oshim/auth';
    }

    public function getRequestedPermissions(): array
    {
        return ['database.read', 'database.write', 'session.start'];
    }

    public function register(Container $container): void
    {
        // Ecosystem providing a service
        $container->bind('auth.manager', function() {
            return new AuthManager(); // Hypothetical internal class
        });
    }

    public function boot(Container $container): void
    {
        // Ecosystem providing automatic routes
        if ($container->has(Router::class)) {
            $router = $container->get(Router::class);
            
            // Developer Freedom: Developer can override this route in their own routes/web.php
            // because App routes load AFTER plugins, or developer can intercept it.
            $router->get('/oshim-login', function() {
                return 'Native OSHIM Auth System (Plugin)';
            });
        }
    }
}

// Internal Ecosystem Class
class AuthManager {
    public function check(): bool { return false; }
}
