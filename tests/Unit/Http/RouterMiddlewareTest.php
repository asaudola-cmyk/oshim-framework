<?php
declare(strict_types=1);

namespace Tests\Unit\Http;

use Oshim\Testing\TestCase;
use Oshim\Http\Router\Router;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Middleware\Pipeline;
use Oshim\Http\Middleware\CorsMiddleware;
use Oshim\Http\Middleware\CsrfMiddleware;
use Oshim\Http\Middleware\SecurityHeadersMiddleware;
use Oshim\Http\Session\Session;
use Oshim\Http\Session\EncryptedFileSessionStore;
use Oshim\Container\Container;
use Closure;

class RouterMiddlewareTest extends TestCase
{
    protected Router $router;
    protected Container $container;

    public function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        $this->router = new Router($this->container);
    }

    public function testStaticRouteDispatch(): void
    {
        $this->router->get('/health', function () {
            return Response::json(['status' => 'healthy']);
        });

        $request = Request::create('GET', '/health');
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('healthy', $response->getContent());
    }

    public function testDynamicRouteParameterExtraction(): void
    {
        $this->router->get('/client/vps/{id}/status', function (Request $request, string $id) {
            return Response::json(['instance_id' => $id]);
        })->whereNumber('id');

        $request = Request::create('GET', '/client/vps/1042/status');
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('"instance_id":"1042"', $response->getContent());
    }

    public function testRouteGroupsWithPrefixAndMiddleware(): void
    {
        $executionOrder = [];

        $dummyMiddleware = new class($executionOrder) implements \Oshim\Http\Middleware\MiddlewareInterface {
            private array $order;
            public function __construct(array &$order) { $this->order = &$order; }
            public function handle(Request $request, Closure $next): Response {
                $this->order[] = 'group_mw_in';
                $resp = $next($request);
                $this->order[] = 'group_mw_out';
                return $resp;
            }
        };

        $this->router->group(['prefix' => '/admin', 'middleware' => [$dummyMiddleware]], function (Router $r) use (&$executionOrder) {
            $r->get('/dashboard', function () use (&$executionOrder) {
                $executionOrder[] = 'action';
                return Response::html("<h1>Admin Dashboard</h1>");
            });
        });

        $request = Request::create('GET', '/admin/dashboard');
        $response = $this->router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['group_mw_in', 'action', 'group_mw_out'], $executionOrder);
    }

    public function testMethodNotAllowedReturns405(): void
    {
        $this->router->post('/api/servers', function () {
            return Response::json(['created' => true]);
        });

        $request = Request::create('GET', '/api/servers', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = $this->router->dispatch($request);

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertNotNull($response->getHeaders()->get('allow'));
    }

    public function testRouteNotFoundReturns404(): void
    {
        $request = Request::create('GET', '/unregistered/random/path', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = $this->router->dispatch($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCorsMiddlewareAddsHeaders(): void
    {
        $cors = new CorsMiddleware();
        $request = Request::create('OPTIONS', '/api/data');

        $response = $cors->handle($request, fn() => Response::make());

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('*', $response->getHeaders()->get('access-control-allow-origin'));
    }

    public function testSecurityHeadersMiddlewareAttachesHeaders(): void
    {
        $sec = new SecurityHeadersMiddleware();
        $request = Request::create('GET', '/');

        $response = $sec->handle($request, fn() => Response::html("OK"));

        $this->assertEquals('nosniff', $response->getHeaders()->get('x-content-type-options'));
        $this->assertEquals('SAMEORIGIN', $response->getHeaders()->get('x-frame-options'));
    }

    public function testCsrfMiddlewareBlocksInvalidToken(): void
    {
        $store = new EncryptedFileSessionStore(sys_get_temp_dir() . '/oshim_test_sess', 'test_key_32_bytes_long_12345678');
        $session = new Session($store, 'test_key_32_bytes_long_12345678');
        $session->start();

        $request = Request::create(
            method: 'POST',
            uri: '/settings',
            parameters: ['_csrf_token' => 'invalid_csrf_token_value'],
            server: ['HTTP_ACCEPT' => 'application/json']
        );
        $request->setSession($session);

        $csrf = new CsrfMiddleware();
        $response = $csrf->handle($request, fn() => Response::json(['status' => 'ok']));

        $this->assertEquals(419, $response->getStatusCode());
    }
}
