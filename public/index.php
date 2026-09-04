<?php
declare(strict_types=1);

/**
 * 👑 OSHIM Sovereign Web Gateway & Soft SPA Router
 * Official Framework Portal & Interactive Documentation Hub
 */
$basePath = dirname(__DIR__);
require_once $basePath . '/engine/Bootstrap.php';
$container = \Oshim\Bootstrap::boot($basePath);

use App\Controllers\AppController;
use App\Controllers\ShowcaseController;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Router\RouteFacade;
use Oshim\Http\Router\Router;
use Oshim\Ui\Router\AppRouter;
use Oshim\Ui\Showcase\SovereignShowcaseLayout;

$request = Request::capture();

// 1. Initialize AppRouter for Sovereign UI & Soft SPA Navigation
$appRouter = new AppRouter();
$appRouter->page('/', fn() => AppController::index(), null, 'OSHIM Sovereign Framework');
$appRouter->page('/showcase', fn() => SovereignShowcaseLayout::renderFullPage(), null, 'Commercial Showcase — OSHIM');
$appRouter->page('/app', fn() => SovereignShowcaseLayout::renderFullPage(), null, 'Commercial Showcase — OSHIM');
$appRouter->page('/docs', fn() => AppController::docs(), null, 'Documentation — OSHIM');
$appRouter->page('/docs/cli', fn() => AppController::cliDocs(), null, 'CLI Reference (36) — OSHIM');
$appRouter->page('/docs/benchmarks', fn() => AppController::benchmarks(), null, 'Benchmarks — OSHIM');
$appRouter->page('/docs/plugins', fn() => AppController::plugins(), null, 'Plugins & Sandbox — OSHIM');
$appRouter->page('/docs/ai', fn() => AppController::aiStudio(), null, 'AI Studio — OSHIM');
$appRouter->page('/ai', fn() => AppController::aiStudio(), null, 'AI Studio — OSHIM');
$appRouter->page('/vps', fn() => AppController::docs(), null, 'Documentation — OSHIM');
$appRouter->page('/client/dashboard', fn() => AppController::docs(), null, 'Documentation — OSHIM');

// 2. Initialize Core HTTP Router & API Endpoints
$router = new Router($container);
RouteFacade::setRouter($router);
$container->instance('router', $router);
$container->instance(Router::class, $router);

// Server Actions Endpoint
$router->post('/_oshim/action', function (Request $req) {
    $body = json_decode($req->getContent(), true) ?? $req->all();
    $res = AppController::handleAction($body);
    return Response::json($res);
});

// PDF Invoice Download
$router->get('/invoice/download', function () {
    return AppController::getPdfInvoiceResponse();
});

// Core page fallbacks on Router
$router->get('/', fn() => Response::html(AppController::index()));
$router->get('/showcase', [ShowcaseController::class, 'index']);
$router->get('/app', [ShowcaseController::class, 'index']);
$router->get('/docs', fn() => Response::html(AppController::docs()));
$router->get('/docs/cli', fn() => Response::html(AppController::cliDocs()));
$router->get('/docs/benchmarks', fn() => Response::html(AppController::benchmarks()));
$router->get('/docs/plugins', fn() => Response::html(AppController::plugins()));
$router->get('/docs/ai', fn() => Response::html(AppController::aiStudio()));
$router->get('/ai', fn() => Response::html(AppController::aiStudio()));
$router->get('/vps', fn() => Response::html(AppController::docs()));
$router->get('/client/dashboard', fn() => Response::html(AppController::docs()));

// Load external routes if present
$routesFile = $basePath . '/routes/web.php';
if (file_exists($routesFile)) {
    (function (Router $router) use ($routesFile) {
        require_once $routesFile;
    })($router);
}

// 3. Unified Dispatch: AppRouter first for UI, fallback to Router
$response = $appRouter->dispatch($request);
if ($response === null) {
    $response = $router->dispatch($request);
}

$response->send();
