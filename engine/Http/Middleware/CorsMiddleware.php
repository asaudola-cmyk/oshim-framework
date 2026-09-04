<?php
declare(strict_types=1);

namespace Oshim\Http\Middleware;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Closure;

class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next, ...$args): Response
    {
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $response = Response::noContent(204);
            return $this->addCorsHeaders($response);
        }

        $response = $next($request);
        return $this->addCorsHeaders($response);
    }

    protected function addCorsHeaders(Response $response): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, X-XSRF-TOKEN')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
