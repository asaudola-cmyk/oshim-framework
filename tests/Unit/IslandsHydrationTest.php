<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Islands\Island;
use Oshim\Ui\Islands\HydrationStrategy;

final class IslandsHydrationTest extends TestCase
{
    public function testIslandRenderingWithHydrationStrategies(): void
    {
        $islandVisible = Island::make(
            'InteractiveChart',
            '<div class="chart">SVG Chart Content</div>',
            ['interval' => '1m', 'metric' => 'cpu']
        )->clientVisible();

        $html = $islandVisible->render();

        $this->assertStringContainsString('<oshim-island', $html);
        $this->assertStringContainsString('data-island="InteractiveChart"', $html);
        $this->assertStringContainsString('data-strategy="visible"', $html);
        $this->assertStringContainsString('&quot;interval&quot;:&quot;1m&quot;', $html);
        $this->assertStringContainsString('SVG Chart Content', $html);

        $islandIdle = Island::make('ChatBot', '<p>Chat</p>')->clientIdle();
        $this->assertSame(HydrationStrategy::IDLE, $islandIdle->getStrategy());
        $this->assertStringContainsString('data-strategy="idle"', $islandIdle->render());

        $islandLoad = Island::make('LiveCart', '<p>Cart</p>')->clientLoad();
        $this->assertSame(HydrationStrategy::LOAD, $islandLoad->getStrategy());
        $this->assertStringContainsString('data-strategy="load"', $islandLoad->render());
    }
}
