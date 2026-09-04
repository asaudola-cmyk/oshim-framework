<?php
declare(strict_types=1);

namespace Oshim\Http\Middleware;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Exceptions\ForbiddenHttpException;
use Oshim\Security\Rbac;
use Closure;

class RbacMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Unauthorized'], 401);
            }
            return Response::redirect('/login');
        }

        if (empty($roles)) {
            return $next($request);
        }

        if (!Rbac::hasRole($user, $roles)) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Forbidden: Insufficient role permissions.'], 403);
            }
            throw new ForbiddenHttpException('Forbidden: Insufficient role permissions.');
        }

        return $next($request);
    }
}
