<?php
declare(strict_types=1);

namespace Oshim\Http\Middleware;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Closure;

interface MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response, optionally delegating to $next.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response;
}
