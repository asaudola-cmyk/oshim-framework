<?php
declare(strict_types=1);

namespace Tests\Unit\Http;

use Oshim\Testing\TestCase;
use Oshim\Http\Router\Router;
use Oshim\Http\Router\Route;
use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Http\Middleware\MiddlewareInterface;
use Oshim\Container\Container;
use InvalidArgumentException;
use Closure;

class RouterTest extends TestCase
{
    private Router $router;
    private Container $container;

    public function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        $this->router = new Router($this->container);
    }

    public function testAllHttpVerbsRegistration(): void
    {
        $this->router->get('/get-route', fn() => 'GET');
        $this->router->post('/post-route', fn() => 'POST');
        $this->router->put('/put-route', fn() => 'PUT');
        $this->router->delete('/delete-route', fn() => 'DELETE');
        $this->router->patch('/patch-route', fn() => 'PATCH');
        $this->router->options('/options-route', fn() => 'OPTIONS');
        $this->router->any('/any-route', fn() => 'ANY');
        $this->router->match(['GET', 'POST'], '/match-route', fn() => 'MATCH');

        $routes = $this->router->getRoutes();
        $this->assertTrue(count($routes) >= 8);

        // Test GET also allows HEAD
        $headReq = Request::create('HEAD', '/get-route');
        $headRes = $this->router->dispatch($headReq);
        $this->assertSame(200, $headRes->getStatusCode());
    }

    public function testDynamicRouteParametersAndWhereConstraints(): void
    {
        $this->router->get('/vps/{id}', function (Request $req, string $id) {
            return Response::json(['vps_id' => $id]);
        })->whereNumber('id');

        $this->router->get('/users/{name}', function (Request $req, string $name) {
            return Response::json(['username' => $name]);
        })->whereAlpha('name');

        $this->router->get('/items/{uuid}', function (Request $req, string $uuid) {
            return Response::json(['uuid' => $uuid]);
        })->whereUuid('uuid');

        // Valid number
        $res1 = $this->router->dispatch(Request::create('GET', '/vps/4096'));
        $this->assertSame(200, $res1->getStatusCode());
        $this->assertStringContainsString('"vps_id":"4096"', $res1->getContent());

        // Invalid number returns 404
        $res2 = $this->router->dispatch(Request::create('GET', '/vps/invalid_node', server: ['HTTP_ACCEPT' => 'application/json']));
        $this->assertSame(404, $res2->getStatusCode());

        // Valid alpha
        $res3 = $this->router->dispatch(Request::create('GET', '/users/johndoe'));
        $this->assertSame(200, $res3->getStatusCode());

        // Valid UUID
        $res4 = $this->router->dispatch(Request::create('GET', '/items/550e8400-e29b-41d4-a716-446655440000'));
        $this->assertSame(200, $res4->getStatusCode());
    }

    public function testOptionalAndWildcardParameters(): void
    {
        $this->router->get('/catalog/{category?}', function (Request $req) {
            return Response::json(['cat' => $req->routeParam('category', 'all')]);
        });

        $this->router->get('/assets/*filepath', function (Request $req, string $filepath) {
            return Response::json(['file' => $filepath]);
        });

        $res1 = $this->router->dispatch(Request::create('GET', '/catalog/laptops'));
        $this->assertStringContainsString('"cat":"laptops"', $res1->getContent());

        $res2 = $this->router->dispatch(Request::create('GET', '/catalog'));
        $this->assertStringContainsString('"cat":"all"', $res2->getContent());

        $res3 = $this->router->dispatch(Request::create('GET', '/assets/css/themes/dark.css'));
        $this->assertStringContainsString('"file":"css/themes/dark.css"', $res3->getContent());
    }

    public function testNestedRouteGroupsAndMiddlewareOrder(): void
    {
        $log = [];

        $mw1 = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$l) {}
            public function handle(Request $req, Closure $next): Response {
                $this->l[] = 'MW1_IN';
                $r = $next($req);
                $this->l[] = 'MW1_OUT';
                return $r;
            }
        };

        $mw2 = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$l) {}
            public function handle(Request $req, Closure $next): Response {
                $this->l[] = 'MW2_IN';
                $r = $next($req);
                $this->l[] = 'MW2_OUT';
                return $r;
            }
        };

        $this->router->group(['prefix' => '/api', 'middleware' => [$mw1]], function (Router $r) use ($mw2, &$log) {
            $r->group(['prefix' => '/v1', 'middleware' => [$mw2]], function (Router $r2) use (&$log) {
                $r2->get('/status', function () use (&$log) {
                    $log[] = 'ACTION';
                    return Response::json(['ok' => true]);
                });
            });
        });

        $res = $this->router->dispatch(Request::create('GET', '/api/v1/status'));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame(['MW1_IN', 'MW2_IN', 'ACTION', 'MW2_OUT', 'MW1_OUT'], $log);
    }

    public function testNamedRoutesAndUrlGeneration(): void
    {
        $this->router->nameRoute('user.edit', $this->router->get('/users/{id}/edit', fn() => 'edit'));
        $this->router->nameRoute('docs.view', $this->router->get('/docs/{section}/{page?}', fn() => 'docs'));

        $url1 = $this->router->route('user.edit', ['id' => 42]);
        $this->assertSame('/users/42/edit', $url1);

        $url2 = $this->router->route('user.edit', ['id' => 42, 'tab' => 'security']);
        $this->assertSame('/users/42/edit?tab=security', $url2);

        $url3 = $this->router->route('docs.view', ['section' => 'api', 'page' => 'auth']);
        $this->assertSame('/docs/api/auth', $url3);

        $url4 = $this->router->route('docs.view', ['section' => 'api']);
        $this->assertSame('/docs/api', $url4);

        $this->assertThrows(function () {
            $this->router->route('unregistered.route');
        }, InvalidArgumentException::class);
    }

    public function testMethodNotAllowedAndNotFoundHandling(): void
    {
        $this->router->post('/submit/form', fn() => Response::json(['saved' => true]));

        // Method not allowed (405)
        $res405 = $this->router->dispatch(Request::create('GET', '/submit/form', server: ['HTTP_ACCEPT' => 'application/json']));
        $this->assertSame(405, $res405->getStatusCode());
        $this->assertSame('POST', $res405->getHeaders()->get('allow'));

        // Not Found (404)
        $res404 = $this->router->dispatch(Request::create('GET', '/does-not-exist', server: ['HTTP_ACCEPT' => 'application/json']));
        $this->assertSame(404, $res404->getStatusCode());
    }

    public function testActionAutoConversions(): void
    {
        $this->router->get('/array-res', fn() => ['type' => 'array']);
        $this->router->get('/string-res', fn() => '<h1>HTML Content</h1>');
        $this->router->get('/response-res', fn() => Response::make('custom', 201));

        $res1 = $this->router->dispatch(Request::create('GET', '/array-res'));
        $this->assertSame(200, $res1->getStatusCode());
        $this->assertStringContainsString('application/json', $res1->getHeaders()->get('content-type'));

        $res2 = $this->router->dispatch(Request::create('GET', '/string-res'));
        $this->assertSame(200, $res2->getStatusCode());
        $this->assertStringContainsString('text/html', $res2->getHeaders()->get('content-type'));

        $res3 = $this->router->dispatch(Request::create('GET', '/response-res'));
        $this->assertSame(201, $res3->getStatusCode());
        $this->assertSame('custom', $res3->getContent());
    }
}
