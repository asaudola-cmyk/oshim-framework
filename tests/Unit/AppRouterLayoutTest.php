<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Router\AppRouter;
use Oshim\Ui\Router\Layout;
use Oshim\Ui\Router\Page;

final class AppRouterLayoutTest extends TestCase
{
    public function testNestedLayoutAndPageResolution(): void
    {
        $rootLayout = Layout::make('root', function ($slot) {
            return "<div id=\"root-shell\"><nav>Global Nav</nav><main>{$slot}</main></div>";
        });

        $dashboardLayout = Layout::make('dashboard', function ($slot) {
            return "<div id=\"dashboard-shell\"><aside>Sidebar</aside><section>{$slot}</section></div>";
        }, $rootLayout);

        $router = new AppRouter();
        $router->setRootLayout($rootLayout);

        $router->page('/dashboard', function () {
            return '<h1>Dashboard Overview</h1>';
        }, $dashboardLayout, 'Dashboard');

        $router->page('/vps/[id]', function ($params) {
            return "<h2>VPS Details: {$params['id']}</h2>";
        }, $dashboardLayout, 'VPS Details');

        // Test static route resolve
        $res1 = $router->resolve('/dashboard');
        $this->assertNotNull($res1);
        $fullHtml1 = $res1['page']->renderFull();
        $this->assertStringContainsString('Global Nav', $fullHtml1);
        $this->assertStringContainsString('Sidebar', $fullHtml1);
        $this->assertStringContainsString('Dashboard Overview', $fullHtml1);

        // Test dynamic route segment resolve
        $res2 = $router->resolve('/vps/node-dhaka-01');
        $this->assertNotNull($res2);
        $this->assertSame('node-dhaka-01', $res2['params']['id']);
        $fullHtml2 = $res2['page']->renderFull($res2['params']);
        $this->assertStringContainsString('VPS Details: node-dhaka-01', $fullHtml2);
        $this->assertStringContainsString('Global Nav', $fullHtml2);
    }
}
