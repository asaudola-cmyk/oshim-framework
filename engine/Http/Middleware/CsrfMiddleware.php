<?php
declare(strict_types=1);

namespace Oshim\Http\Middleware;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Cookie\Cookie;
use Oshim\Http\Exceptions\CsrfTokenMismatchException;
use Closure;

class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next, ...$args): Response
    {
        $session = $request->session();

        // If session is active and method is state-mutating
        if ($session !== null && !in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $submittedToken = $request->input('_csrf_token')
                ?? $request->header('x-csrf-token')
                ?? $request->header('x-xsrf-token');

            $sessionToken = $session->token();

            if (!is_string($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
                if ($request->wantsJson()) {
                    return Response::json(['error' => 'CSRF token mismatch.'], 419);
                }
                throw new CsrfTokenMismatchException('CSRF token mismatch.');
            }
        }

        $response = $next($request);

        // Attach readable XSRF-TOKEN cookie for client JS
        if ($session !== null) {
            $response->withCookie(new Cookie(
                name: 'XSRF-TOKEN',
                value: $session->token(),
                expire: 0,
                path: '/',
                domain: '',
                secure: $request->isSecure(),
                httpOnly: false, // Accessible by JavaScript reactive client
                sameSite: 'Lax'
            ));
        }

        return $response;
    }
}
