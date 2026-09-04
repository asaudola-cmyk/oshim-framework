<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Testing\TestCase;
use Oshim\Ui\Animation\AnimationVariant;
use Oshim\Ui\Animation\Keyframes;
use Oshim\Ui\Animation\Motion;
use Oshim\Ui\Animation\MotionElement;
use Oshim\Ui\Animation\MotionRuntime;
use Oshim\Ui\Animation\MotionTimeline;
use Oshim\Ui\Animation\ScrollTrigger;
use Oshim\Ui\Animation\Spring;
use Oshim\Ui\Animation\Stagger;
use Oshim\Ui\Dsl\Element;

/**
 * High-Rigor Unit Test Suite for Server-Driven Spring Animations.
 */
class ServerAnimationTest extends TestCase
{
    // ==========================================
    // 1. Spring Physics Parameter & ODE Tests
    // ==========================================

    public function testSpringPhysicsParametersAndRegimes(): void
    {
        // Underdamped regime (zeta < 1): stiffness 100, damping 5, mass 1
        // zeta = 5 / (2 * sqrt(100)) = 5 / 20 = 0.25
        $underdamped = new Spring(100.0, 5.0, 1.0);
        $this->assertEquals(100.0, $underdamped->getStiffness());
        $this->assertEquals(5.0, $underdamped->getDamping());
        $this->assertEquals(1.0, $underdamped->getMass());
        $this->assertEquals(10.0, $underdamped->getNaturalFrequency());
        $this->assertEquals(0.25, $underdamped->getDampingRatio());
        $this->assertTrue($underdamped->isUnderdamped());
        $this->assertFalse($underdamped->isCriticallyDamped());
        $this->assertFalse($underdamped->isOverdamped());

        // Critically damped regime (zeta = 1): stiffness 100, damping 20, mass 1
        // zeta = 20 / (2 * sqrt(100)) = 20 / 20 = 1.0
        $criticallyDamped = new Spring(100.0, 20.0, 1.0);
        $this->assertEquals(1.0, $criticallyDamped->getDampingRatio());
        $this->assertTrue($criticallyDamped->isCriticallyDamped());
        $this->assertFalse($criticallyDamped->isUnderdamped());
        $this->assertFalse($criticallyDamped->isOverdamped());

        // Overdamped regime (zeta > 1): stiffness 100, damping 40, mass 1
        // zeta = 40 / 20 = 2.0
        $overdamped = new Spring(100.0, 40.0, 1.0);
        $this->assertEquals(2.0, $overdamped->getDampingRatio());
        $this->assertTrue($overdamped->isOverdamped());
        $this->assertFalse($overdamped->isUnderdamped());
        $this->assertFalse($overdamped->isCriticallyDamped());
    }

    public function testSpringPresets(): void
    {
        $bouncy = Spring::bouncy();
        $this->assertTrue($bouncy->isUnderdamped());
        $this->assertTrue($bouncy->getDampingRatio() > 0.0);
        $this->assertTrue($bouncy->getDampingRatio() < 1.0);

        $gentle = Spring::gentle();
        $this->assertTrue($gentle->isUnderdamped());

        $wobbly = Spring::wobbly();
        $this->assertTrue($wobbly->isUnderdamped());

        $stiff = Spring::stiff();
        $this->assertEquals(250.0, $stiff->getStiffness());

        $snappy = Spring::snappy();
        $this->assertEquals(400.0, $snappy->getStiffness());
        $this->assertEquals(0.8, $snappy->getMass());

        $slow = Spring::slow();
        $this->assertEquals(60.0, $slow->getStiffness());
        $this->assertEquals(1.2, $slow->getMass());
    }

    public function testSpringOdeSolverBoundaryAndSettling(): void
    {
        $spring = Spring::bouncy();

        // At t = 0, solve(0, 0, 100) must equal initial position 0
        $this->assertEquals(0.0, $spring->solve(0.0, 0.0, 100.0));

        // At negative t, must equal from
        $this->assertEquals(0.0, $spring->solve(-1.0, 0.0, 100.0));

        // In underdamped spring, there should be overshoot beyond target
        $maxVal = 0.0;
        for ($t = 0.0; $t <= 2.0; $t += 0.02) {
            $val = $spring->solve($t, 0.0, 1.0);
            if ($val > $maxVal) {
                $maxVal = $val;
            }
        }
        $this->assertTrue($maxVal > 1.0);

        // After settling duration, position must be within restDelta of target
        $settling = $spring->settlingDuration(0.0, 1.0);
        $this->assertTrue($settling > 0.2);
        $this->assertTrue($settling < 5.0);

        $finalPos = $spring->solve($settling, 0.0, 1.0);
        $this->assertTrue(abs($finalPos - 1.0) < 0.02);
    }

    public function testCriticallyDampedAndOverdampedOdeSolver(): void
    {
        // Critically damped
        $cd = new Spring(100.0, 20.0, 1.0);
        $this->assertEquals(0.0, $cd->solve(0.0, 0.0, 50.0));
        $settleCd = $cd->settlingDuration(0.0, 50.0);
        $this->assertTrue(abs($cd->solve($settleCd, 0.0, 50.0) - 50.0) < 0.1);

        // Overdamped
        $od = new Spring(100.0, 40.0, 1.0);
        $this->assertEquals(0.0, $od->solve(0.0, 0.0, 50.0));
        $settleOd = $od->settlingDuration(0.0, 50.0);
        $this->assertTrue(abs($od->solve($settleOd, 0.0, 50.0) - 50.0) < 0.1);
    }

    public function testSpringVelocityFunction(): void
    {
        $spring = new Spring(100.0, 10.0, 1.0, 5.0);
        $this->assertEquals(5.0, $spring->getInitialVelocity());

        // At t = 0, velocity must match initial velocity
        $this->assertEquals(5.0, $spring->velocity(0.0, 0.0, 100.0));

        // Eventually velocity must approach zero
        $settling = $spring->settlingDuration(0.0, 100.0);
        $velAtSettle = $spring->velocity($settling, 0.0, 100.0);
        $this->assertTrue(abs($velAtSettle) < 0.1);
    }

    public function testSpringFluentImmutability(): void
    {
        $base = Spring::default();
        $modified = $base->withStiffness(250.0)
            ->withDamping(18.0)
            ->withMass(1.5)
            ->withVelocity(2.0)
            ->withDelay(0.25);

        $this->assertEquals(100.0, $base->getStiffness());
        $this->assertEquals(250.0, $modified->getStiffness());
        $this->assertEquals(18.0, $modified->getDamping());
        $this->assertEquals(1.5, $modified->getMass());
        $this->assertEquals(2.0, $modified->getInitialVelocity());
        $this->assertEquals(0.25, $modified->getDelay());
    }

    public function testSpringSamplingAndCssLinear(): void
    {
        $spring = Spring::snappy();
        $samples = $spring->sample(15, 0.0, 1.0);

        $this->assertEquals(15, count($samples));
        $this->assertEquals(0.0, $samples[0]['progress']);
        $this->assertEquals(1.0, $samples[14]['progress']);
        $this->assertEquals(0.0, $samples[0]['value']);

        $cssLinear = $spring->toCssLinear(10);
        $this->assertStringContainsString('linear(', $cssLinear);
        $this->assertStringContainsString('%', $cssLinear);
    }

    public function testSpringToCssKeyframes(): void
    {
        $spring = Spring::bouncy();
        $css = $spring->toCssKeyframes('bounceY', 'transform', 100.0, 0.0, 'px', 10);

        $this->assertStringContainsString('@keyframes bounceY', $css);
        $this->assertStringContainsString('0% { transform: translateY(100.00px); }', $css);
        $this->assertStringContainsString('100% { transform: translateY(0.00px); }', $css);
    }

    // ==========================================
    // 2. Keyframes Generator Tests
    // ==========================================

    public function testKeyframesManualStepsAndCompilation(): void
    {
        $kf = Keyframes::make('fadeSlideUp')
            ->duration(0.8)
            ->delay(0.1)
            ->iterations(2)
            ->direction('alternate')
            ->fillMode('both')
            ->easing('cubic-bezier(0.25, 1, 0.5, 1)')
            ->addStep(0, ['opacity' => 0, 'transform' => 'translateY(30px)'])
            ->addStep(50, ['opacity' => 0.7, 'transform' => 'translateY(5px)'])
            ->addStep(100, ['opacity' => 1, 'transform' => 'translateY(0px)']);

        $this->assertEquals('fadeSlideUp', $kf->getName());
        $this->assertEquals(0.8, $kf->getDuration());
        $this->assertEquals(0.1, $kf->getDelay());
        $this->assertEquals(2, $kf->getIterations());
        $this->assertEquals('alternate', $kf->getDirection());
        $this->assertEquals('both', $kf->getFillMode());

        $css = $kf->toCss();
        $this->assertStringContainsString('@keyframes fadeSlideUp {', $css);
        $this->assertStringContainsString('0% { opacity: 0; transform: translateY(30px); }', $css);
        $this->assertStringContainsString('50% { opacity: 0.7; transform: translateY(5px); }', $css);
        $this->assertStringContainsString('100% { opacity: 1; transform: translateY(0px); }', $css);

        $animShorthand = $kf->toAnimationCss();
        $this->assertEquals('fadeSlideUp 0.800s cubic-bezier(0.25, 1, 0.5, 1) 0.100s 2 alternate both', $animShorthand);
    }

    public function testKeyframesFromSpringSynthesis(): void
    {
        $spring = Spring::bouncy();
        $kf = Keyframes::fromSpring(
            'springEntry',
            ['y' => 60, 'opacity' => 0, 'scale' => 0.8],
            ['y' => 0, 'opacity' => 1, 'scale' => 1.0],
            $spring,
            12
        );

        $this->assertEquals('springEntry', $kf->getName());
        $this->assertTrue($kf->getDuration() > 0.5);
        $this->assertEquals(12, count($kf->getSteps()));

        $css = $kf->toCss();
        $this->assertStringContainsString('@keyframes springEntry {', $css);
        $this->assertStringContainsString('0% {', $css);
        $this->assertStringContainsString('100% {', $css);
        $this->assertStringContainsString('opacity:', $css);
        $this->assertStringContainsString('translateY', $css);
        $this->assertStringContainsString('scale', $css);
    }

    // ==========================================
    // 3. ScrollTrigger Tests
    // ==========================================

    public function testScrollTriggerParametersAndPresets(): void
    {
        $st = ScrollTrigger::make(0.25, '0px 0px -50px 0px', true)
            ->start('top 80%')
            ->end('bottom 20%')
            ->enterAction('restart')
            ->exitAction('reverse')
            ->withMarkers(true);

        $this->assertEquals(0.25, $st->getThreshold());
        $this->assertEquals('0px 0px -50px 0px', $st->getRootMargin());
        $this->assertTrue($st->isOnce());
        $this->assertEquals('top 80%', $st->getStart());
        $this->assertEquals('bottom 20%', $st->getEnd());
        $this->assertEquals('restart', $st->getEnterAction());
        $this->assertEquals('reverse', $st->getExitAction());
        $this->assertTrue($st->hasMarkers());

        $attrs = $st->toDataAttributes();
        $this->assertEquals('true', $attrs['data-scroll']);
        $this->assertEquals('0.25', $attrs['data-scroll-threshold']);
        $this->assertEquals('0px 0px -50px 0px', $attrs['data-scroll-margin']);
        $this->assertEquals('true', $attrs['data-scroll-once']);
        $this->assertEquals('restart', $attrs['data-scroll-enter']);
        $this->assertEquals('reverse', $attrs['data-scroll-exit']);
        $this->assertEquals('true', $attrs['data-scroll-markers']);

        $html = $st->toHtmlAttributes();
        $this->assertStringContainsString('data-scroll="true"', $html);
        $this->assertStringContainsString('data-scroll-threshold="0.25"', $html);
    }

    public function testScrollTriggerScrubAndParallax(): void
    {
        $scrubTrigger = ScrollTrigger::scrub('top 90%', 'bottom 10%', 0.5);
        $this->assertEquals(0.5, $scrubTrigger->getScrub());
        $this->assertEquals('top 90%', $scrubTrigger->getStart());
        $this->assertEquals('bottom 10%', $scrubTrigger->getEnd());
        $this->assertFalse($scrubTrigger->isOnce());

        $parallaxTrigger = ScrollTrigger::parallax(-0.3, 'vertical');
        $this->assertEquals(-0.3, $parallaxTrigger->getParallax());
        $this->assertEquals('vertical', $parallaxTrigger->getDirection());

        $attrs = $parallaxTrigger->toDataAttributes();
        $this->assertEquals('-0.3', $attrs['data-scroll-parallax']);
        $this->assertEquals('vertical', $attrs['data-scroll-direction']);
    }

    // ==========================================
    // 4. Stagger Timing Tests
    // ==========================================

    public function testStaggerForwardAndReverseCalculations(): void
    {
        $forward = Stagger::forward(0.1, 0.05);
        $this->assertEquals(0.05, $forward->calculateDelay(0, 4));
        $this->assertEquals(0.15, $forward->calculateDelay(1, 4));
        $this->assertEquals(0.25, $forward->calculateDelay(2, 4));
        $this->assertEquals(0.35, $forward->calculateDelay(3, 4));

        $reverse = Stagger::reverse(0.1, 0.05);
        $this->assertEquals(0.35, $reverse->calculateDelay(0, 4));
        $this->assertEquals(0.25, $reverse->calculateDelay(1, 4));
        $this->assertEquals(0.15, $reverse->calculateDelay(2, 4));
        $this->assertEquals(0.05, $reverse->calculateDelay(3, 4));
    }

    public function testStaggerCenterAndMaxDelay(): void
    {
        $center = Stagger::center(0.05, 0.0);
        // For 5 items (indices 0, 1, 2, 3, 4), index 2 is center
        $this->assertEquals(0.1, $center->calculateDelay(0, 5));
        $this->assertEquals(0.05, $center->calculateDelay(1, 5));
        $this->assertEquals(0.0, $center->calculateDelay(2, 5));
        $this->assertEquals(0.05, $center->calculateDelay(3, 5));
        $this->assertEquals(0.1, $center->calculateDelay(4, 5));

        // Max delay clamping
        $clamped = Stagger::forward(0.5, 0.0)->maxDelay(0.6);
        $this->assertEquals(0.0, $clamped->calculateDelay(0, 5));
        $this->assertEquals(0.5, $clamped->calculateDelay(1, 5));
        $this->assertEquals(0.6, $clamped->calculateDelay(2, 5));
        $this->assertEquals(0.6, $clamped->calculateDelay(3, 5));
    }

    public function testStaggerApplyToCollection(): void
    {
        $stagger = Stagger::forward(0.05, 0.1);
        $items = ['alpha', 'beta', 'gamma'];

        $results = $stagger->apply($items, function ($item, $delay, $i) {
            return "{$item}:{$delay}";
        });

        $this->assertEquals(['alpha:0.1', 'beta:0.15', 'gamma:0.2'], $results);
    }

    // ==========================================
    // 5. AnimationVariant Tests
    // ==========================================

    public function testAnimationVariantPropertiesAndStyleString(): void
    {
        $spring = Spring::bouncy();
        $variant = AnimationVariant::make('enter')
            ->opacity(0.9)
            ->x(25)
            ->y(-10)
            ->scale(1.05)
            ->rotate(45)
            ->filter('blur(2px)')
            ->background('#1e293b')
            ->color('#38bdf8')
            ->spring($spring);

        $this->assertEquals('enter', $variant->getName());
        $this->assertEquals(0.9, $variant->getProperty('opacity'));
        $this->assertEquals(25.0, $variant->getProperty('x'));
        $this->assertEquals(-10.0, $variant->getProperty('y'));
        $this->assertEquals(1.05, $variant->getProperty('scale'));
        $this->assertEquals(45.0, $variant->getProperty('rotate'));
        $this->assertSame($spring, $variant->getSpring());

        $transform = $variant->toTransformString();
        $this->assertStringContainsString('translateX(25px)', $transform);
        $this->assertStringContainsString('translateY(-10px)', $transform);
        $this->assertStringContainsString('scale(1.05)', $transform);
        $this->assertStringContainsString('rotate(45deg)', $transform);

        $styleStr = $variant->toStyleString();
        $this->assertStringContainsString('opacity: 0.9;', $styleStr);
        $this->assertStringContainsString('background: #1e293b;', $styleStr);
        $this->assertStringContainsString('color: #38bdf8;', $styleStr);
        $this->assertStringContainsString('transform:', $styleStr);
    }

    // ==========================================
    // 6. Motion & MotionElement Component Tests
    // ==========================================

    public function testMotionElementDeclarativeRender(): void
    {
        $button = Motion::button('test_btn')
            ->class('btn-primary', 'font-bold')
            ->initial(['opacity' => 0, 'y' => 20, 'scale' => 0.9])
            ->animate(['opacity' => 1, 'y' => 0, 'scale' => 1.0])
            ->whileHover(['scale' => 1.08, 'y' => -2])
            ->whileTap(['scale' => 0.96])
            ->spring(Spring::bouncy())
            ->scrollTrigger(ScrollTrigger::onVisible(0.2))
            ->text('Launch Missile');

        $html = $button->render();

        $this->assertStringContainsString('<style>@keyframes oshim_kf_test_btn {', $html);
        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('id="test_btn"', $html);
        $this->assertStringContainsString('class="btn-primary font-bold"', $html);
        $this->assertStringContainsString('data-motion="true"', $html);
        $this->assertStringContainsString('data-motion-id="test_btn"', $html);
        $this->assertStringContainsString('data-while-hover=', $html);
        $this->assertStringContainsString('data-while-tap=', $html);
        $this->assertStringContainsString('data-spring=', $html);
        $this->assertStringContainsString('data-scroll="true"', $html);
        $this->assertStringContainsString('data-scroll-threshold="0.2"', $html);
        $this->assertStringContainsString('animation: oshim_kf_test_btn', $html);
        $this->assertStringContainsString('Launch Missile', $html);
    }

    public function testMotionNestedChildrenWithStagger(): void
    {
        $container = Motion::div('container')
            ->stagger(Stagger::forward(0.15))
            ->spring(Spring::gentle());

        $item1 = Motion::card('card_1')->initial(['opacity' => 0, 'y' => 20])->animate(['opacity' => 1, 'y' => 0])->text('Card 1');
        $item2 = Motion::card('card_2')->initial(['opacity' => 0, 'y' => 20])->animate(['opacity' => 1, 'y' => 0])->text('Card 2');
        $item3 = Motion::card('card_3')->initial(['opacity' => 0, 'y' => 20])->animate(['opacity' => 1, 'y' => 0])->text('Card 3');

        $container->children([$item1, $item2, $item3]);

        $html = $container->render();

        $this->assertStringContainsString('id="container"', $html);
        $this->assertStringContainsString('data-stagger="true"', $html);
        $this->assertStringContainsString('id="card_1"', $html);
        $this->assertStringContainsString('id="card_2"', $html);
        $this->assertStringContainsString('id="card_3"', $html);
        $this->assertStringContainsString('Card 1', $html);
        $this->assertStringContainsString('Card 2', $html);
        $this->assertStringContainsString('Card 3', $html);
    }

    public function testMotionInteroperabilityWithDslElement(): void
    {
        $motion = Motion::div('dsl_target')
            ->class('border', 'p-4')
            ->initial(['opacity' => 0])
            ->animate(['opacity' => 1])
            ->text('DSL Wrapped');

        $dslElement = $motion->toElement();
        $this->assertInstanceOf(Element::class, $dslElement);

        $dslHtml = $dslElement->render();
        $this->assertStringContainsString('id="dsl_target"', $dslHtml);
        $this->assertStringContainsString('class="border p-4"', $dslHtml);
        $this->assertStringContainsString('DSL Wrapped', $dslHtml);
    }

    public function testMotionVariantsDefinition(): void
    {
        $motion = Motion::section('hero')
            ->variants([
                'hidden' => ['opacity' => 0, 'y' => 50],
                'visible' => ['opacity' => 1, 'y' => 0],
            ]);

        $variants = $motion->getVariants();
        $this->assertEquals(2, count($variants));
        $this->assertInstanceOf(AnimationVariant::class, $variants['hidden']);
        $this->assertInstanceOf(AnimationVariant::class, $variants['visible']);

        $html = $motion->render();
        $this->assertStringContainsString('data-variants=', $html);
        $this->assertStringContainsString('hidden', $html);
        $this->assertStringContainsString('visible', $html);
    }

    // ==========================================
    // 7. MotionTimeline Choreography Tests
    // ==========================================

    public function testMotionTimelineSequenceCalculation(): void
    {
        $timeline = MotionTimeline::make();

        $spring1 = Spring::snappy(); // settling duration approx 0.8s
        $spring2 = Spring::gentle(); // settling duration approx 1.5s

        $timeline->add('navbar', ['opacity' => 1, 'y' => 0], 0.0, $spring1);
        $timeline->add('hero', ['opacity' => 1, 'scale' => 1], -0.2, $spring2);

        $tracks = $timeline->getTracks();
        $this->assertEquals(2, count($tracks));

        $this->assertEquals('navbar', $tracks[0]['id']);
        $this->assertEquals(0.0, $tracks[0]['startTime']);
        $this->assertEquals($spring1->settlingDuration(), $tracks[0]['duration']);

        $this->assertEquals('hero', $tracks[1]['id']);
        $expectedHeroStart = max(0.0, $tracks[0]['endTime'] - 0.2);
        $this->assertEquals($expectedHeroStart, $tracks[1]['startTime']);

        $totalDuration = $timeline->getTotalDuration();
        $this->assertTrue($totalDuration > 1.0);
    }

    // ==========================================
    // 8. MotionRuntime Client Script Tests
    // ==========================================

    public function testMotionRuntimeGeneratesZeroDependencyScript(): void
    {
        $scriptTag = MotionRuntime::script(true);
        $this->assertStringContainsString('<script id="oshim-motion-runtime">', $scriptTag);
        $this->assertStringContainsString('IntersectionObserver', $scriptTag);
        $this->assertStringContainsString('requestAnimationFrame', $scriptTag);
        $this->assertStringContainsString('data-motion="true"', $scriptTag);
        $this->assertStringContainsString('dataset.scroll', $scriptTag);
        $this->assertStringContainsString('dataset.whileHover', $scriptTag);
        $this->assertStringContainsString('</script>', $scriptTag);

        $rawCode = MotionRuntime::code(false);
        $this->assertStringContainsString('__OSHIM_MOTION_INITIALIZED__', $rawCode);
    }
}
