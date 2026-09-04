<?php
declare(strict_types=1);

namespace Oshim\Http\Middleware;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Exceptions\UnauthorizedHttpException;
use Closure;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next, ...$args): Response
    {
        $user = $request->user();

        // If user not set on request, check session
        if ($user === null && $request->session() !== null) {
            $userId = $request->session()->get('user_id');
            if ($userId !== null) {
                // Construct basic user object if user model not yet bound
                $user = (object)[
                    'id'    => $userId,
                    'role'  => $request->session()->get('user_role', 'client'),
                    'email' => $request->session()->get('user_email', ''),
                ];
                $request->setUser($user);
            }
        }

        if ($user === null) {
            if ($request->wantsJson() || $request->isAjax() || str_starts_with($request->getPath(), '/api/')) {
                return Response::json(['error' => 'Unauthorized'], 401);
            }
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
