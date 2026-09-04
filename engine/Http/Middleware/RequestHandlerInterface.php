<?php
declare(strict_types=1);

namespace Oshim\Http\Middleware;

use Oshim\Http\Request;
use Oshim\Http\Response;

interface RequestHandlerInterface
{
    public function handle(Request $request): Response;
}
