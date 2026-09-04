<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Signals\Signal;
use Oshim\Ui\Signals\Computed;
use Oshim\Ui\Signals\Effect;
use Oshim\Ui\Signals\SignalDomBinder;

final class SignalsReactivityTest extends TestCase
{
    public function testSignalGetSetAndSubscribe(): void
    {
        $count = Signal::make(10, 'sig-count');
        $this->assertSame(10, $count->get());

        $notified = false;
        $newValCaptured = null;
        $count->subscribe(function ($newVal) use (&$notified, &$newValCaptured) {
            $notified = true;
            $newValCaptured = $newVal;
        });

        $count->set(25);
        $this->assertSame(25, $count->get());
        $this->assertTrue($notified);
        $this->assertSame(25, $newValCaptured);
    }

    public function testComputedDerivedSignal(): void
    {
        $price = Signal::make(100);
        $taxRate = Signal::make(0.05);

        $total = Computed::make(function () use ($price, $taxRate) {
            return $price->get() * (1 + $taxRate->get());
        });

        $this->assertSame(105.0, $total->get());

        // Update dependency
        $price->set(200);
        $this->assertSame(210.0, $total->get());
    }

    public function testSignalDomBinderAndPatch(): void
    {
        $sig = Signal::make('Running', 'sig-status');
        $html = SignalDomBinder::bindText($sig, 'span', ['class' => 'status-pill']);

        $this->assertStringContainsString('data-oshim-sig="sig-status"', $html);
        $this->assertStringContainsString('class="status-pill"', $html);
        $this->assertStringContainsString('Running', $html);

        $patch = SignalDomBinder::createPatch($sig);
        $this->assertSame('PATCH_SIGNAL_VALUE', $patch['type']);
        $this->assertSame('sig-status', $patch['signal_id']);
        $this->assertSame('Running', $patch['value']);
    }
}
