<?php
declare(strict_types=1);

namespace Oshim\Http\Middleware;

use Oshim\Container\Container;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Session\Session;
use Oshim\Http\Session\SessionStoreInterface;
use Oshim\Http\Session\EncryptedFileSessionStore;
use Oshim\Http\Cookie\Cookie;
use Closure;

class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected ?Container $container = null
    ) {
        $this->container ??= Container::getInstance();
    }

    public function handle(Request $request, Closure $next, ...$args): Response
    {
        $appKey = $_ENV['APP_KEY'] ?? 'oshim_secret_app_key_32_bytes_long_12345';
        $storagePath = defined('OSHIM_STORAGE_PATH') ? OSHIM_STORAGE_PATH . '/sessions' : dirname(__DIR__, 3) . '/storage/sessions';

        /** @var SessionStoreInterface $store */
        $store = $this->container->has(SessionStoreInterface::class)
            ? $this->container->get(SessionStoreInterface::class)
            : new EncryptedFileSessionStore($storagePath, $appKey);

        $session = new Session($store, $appKey, 7200);

        $sessionId = $request->cookie('oshim_session');
        $session->start($sessionId);

        $request->setSession($session);
        $this->container->instance(Session::class, $session);

        $response = $next($request);

        $session->save();

        $cookie = new Cookie(
            name: 'oshim_session',
            value: $session->getId(),
            expire: time() + 7200,
            path: '/',
            domain: '',
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'Lax'
        );

        $response->withCookie($cookie);

        return $response;
    }
}
