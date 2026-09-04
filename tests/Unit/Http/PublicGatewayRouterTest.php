<?php
declare(strict_types=1);

namespace Tests\Unit\Http;

use Oshim\Testing\TestCase;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Router\Router;
use App\Controllers\AppController;

class PublicGatewayRouterTest extends TestCase
{
    public function testRouterDispatchesCorePages(): void
    {
        $router = new Router();
        $router->get('/', fn() => Response::html(AppController::index()));
        $router->get('/vps', fn() => Response::html(AppController::vps()));
        $router->get('/ai', fn() => Response::html(AppController::ai()));

        $reqHome = Request::create('GET', '/');
        $resHome = $router->dispatch($reqHome);
        $this->assertSame(200, $resHome->getStatusCode());
        $this->assertStringContainsString('<!DOCTYPE html>', $resHome->getContent());

        $reqVps = Request::create('GET', '/vps');
        $resVps = $router->dispatch($reqVps);
        $this->assertSame(200, $resVps->getStatusCode());
        $this->assertStringContainsString('MicroVM', $resVps->getContent());

        $reqAi = Request::create('GET', '/ai');
        $resAi = $router->dispatch($reqAi);
        $this->assertSame(200, $resAi->getStatusCode());
        $this->assertStringContainsString('AI', $resAi->getContent());
    }
}
