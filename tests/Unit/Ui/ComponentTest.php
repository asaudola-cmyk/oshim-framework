<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Tests\Harness\TestCase;
use Oshim\Ui\Component;
use Oshim\Ui\Exceptions\ComponentActionException;

// Concrete test component fixture
class SampleTestComponent extends Component
{
    public bool $customActionInvoked = false;
    public ?array $receivedPayload = null;

    public function render(): string
    {
        $content = '<div class="sample-content">Hello ' . $this->escape($this->props['name'] ?? 'World') . '</div>';
        return $this->renderRoot('div', $content, ['class' => 'sample-test-component']);
    }

    public function doSomething(array $payload = []): string
    {
        $this->customActionInvoked = true;
        $this->receivedPayload = $payload;
        $this->state['counter'] = ($this->state['counter'] ?? 0) + 1;
        return 'done';
    }
}

class MethodVisibilityFixtureComponent extends Component
{
    public function render(): string
    {
        return '<div>Visibility Fixture</div>';
    }

    public function allowedPublicMethod(array $payload = []): string
    {
        return 'public_allowed';
    }

    protected function internalProtectedMethod(): string
    {
        return 'protected_secret';
    }

    private function internalPrivateMethod(): string
    {
        return 'private_secret';
    }

    public static function staticMethod(): string
    {
        return 'static_method';
    }
}

class ComponentTest extends TestCase
{
    public function testComponentInstantiationAndUniqueId(): void
    {
        $cmp1 = new SampleTestComponent(['name' => 'Alice']);
        $cmp2 = new SampleTestComponent(['name' => 'Bob'], 'custom_id_123');

        $this->assertNotEmpty($cmp1->getId());
        $this->assertStringStartsWith('oshim_', $cmp1->getId());
        $this->assertSame('custom_id_123', $cmp2->getId());
        $this->assertSame('Alice', $cmp1->getProps()['name']);
    }

    public function testComponentStateHydrationAndDehydration(): void
    {
        $cmp = new SampleTestComponent(['name' => 'Alice']);
        $this->assertSame([], $cmp->getState());

        $cmp->hydrate(['counter' => 5, 'active' => true]);
        $this->assertSame(5, $cmp->getState()['counter']);
        $this->assertTrue($cmp->getState()['active']);

        $dehydrated = $cmp->dehydrate();
        $this->assertArrayHasKey('id', $dehydrated);
        $this->assertArrayHasKey('props', $dehydrated);
        $this->assertArrayHasKey('state', $dehydrated);
        $this->assertArrayHasKey('checksum', $dehydrated);
        $this->assertSame(5, $dehydrated['state']['counter']);
    }

    public function testHmacStateSignatureGenerationAndVerification(): void
    {
        $cmp = new SampleTestComponent();
        $state = ['user_id' => 42, 'role' => 'operator'];

        $sig = $cmp->generateSignature($state);
        $this->assertNotEmpty($sig);
        $this->assertTrue($cmp->verifySignature($state, $sig));
        $this->assertTrue($cmp->verifyChecksum($cmp->generateSignature($cmp->getState())));
    }

    public function testTamperedStateChecksumRejection(): void
    {
        $cmp = new SampleTestComponent();
        $originalState = ['user_id' => 42, 'role' => 'operator'];
        $sig = $cmp->generateSignature($originalState);

        $tamperedState = ['user_id' => 42, 'role' => 'superadmin'];
        $this->assertFalse($cmp->verifySignature($tamperedState, $sig));

        $tamperedState2 = ['user_id' => 99, 'role' => 'operator'];
        $this->assertFalse($cmp->verifySignature($tamperedState2, $sig));
    }

    public function testComponentEventEmission(): void
    {
        $cmp = new SampleTestComponent();
        $this->assertSame([], $cmp->getEmittedEvents());

        $cmp->emit('node:restarted', ['nodeId' => 'fra-01']);
        $cmp->emit('alert:created', ['level' => 'critical']);

        $events = $cmp->getEmittedEvents();
        $this->assertCount(2, $events);
        $this->assertSame('node:restarted', $events[0]['event']);
        $this->assertSame('fra-01', $events[0]['payload']['nodeId']);
        $this->assertSame($cmp->getId(), $events[0]['source']);
    }

    public function testComponentActionDispatchingAndHandleEvent(): void
    {
        $cmp = new SampleTestComponent();
        $result = $cmp->dispatch('doSomething', ['key' => 'val123']);

        $this->assertSame('done', $result);
        $this->assertTrue($cmp->customActionInvoked);
        $this->assertSame('val123', $cmp->receivedPayload['key']);
        $this->assertSame(1, $cmp->getState()['counter']);

        // Test handleEvent wrapper
        $dehydrated = $cmp->handleEvent('doSomething', ['key' => 'val456']);
        $this->assertSame(2, $dehydrated['state']['counter']);
        $this->assertArrayHasKey('checksum', $dehydrated);
    }

    public function testRestrictedLifecycleActionsThrowException(): void
    {
        $cmp = new SampleTestComponent();

        $this->assertThrows(ComponentActionException::class, function () use ($cmp) {
            $cmp->dispatch('mount', []);
        });

        $this->assertThrows(ComponentActionException::class, function () use ($cmp) {
            $cmp->dispatch('render', []);
        });

        $this->assertThrows(ComponentActionException::class, function () use ($cmp) {
            $cmp->dispatch('generateSignature', []);
        });

        $this->assertThrows(ComponentActionException::class, function () use ($cmp) {
            $cmp->dispatch('nonExistentAction', []);
        });
    }

    public function testProtectedAndPrivateMethodDispatchRejection(): void
    {
        $cmp = new MethodVisibilityFixtureComponent();

        // Public method works
        $this->assertSame('public_allowed', $cmp->dispatch('allowedPublicMethod'));

        // Protected method rejected
        $this->assertThrows(ComponentActionException::class, function () use ($cmp) {
            $cmp->dispatch('internalProtectedMethod');
        });

        // Private method rejected
        $this->assertThrows(ComponentActionException::class, function () use ($cmp) {
            $cmp->dispatch('internalPrivateMethod');
        });

        // Static method rejected
        $this->assertThrows(ComponentActionException::class, function () use ($cmp) {
            $cmp->dispatch('staticMethod');
        });
    }

    public function testAllFrameworkAndLifecycleMethodsBlockedFromDispatch(): void
    {
        $cmp = new MethodVisibilityFixtureComponent();
        $blockedList = [
            'mount', 'render', 'hydrate', 'dehydrate', 'emit', 'dispatch',
            'renderRoot', 'generateSignature', 'verifySignature', 'escape',
            'slot', 'hasSlot', 'setSlot', 'withSlots', 'getId', 'setId',
            'getState', 'setState', '__construct', '__destruct', '__get',
            '__set', '__call',
        ];

        foreach ($blockedList as $method) {
            $this->assertThrows(ComponentActionException::class, function () use ($cmp, $method) {
                $cmp->dispatch($method, []);
            }, null, "Framework method [{$method}] must be blocked from dispatch.");
        }
    }

    public function testSlotManagement(): void
    {
        $cmp = new SampleTestComponent();
        $this->assertFalse($cmp->hasSlot('header'));
        $this->assertSame('fallback', $cmp->slot('header', 'fallback'));

        $cmp->setSlot('header', '<h2>My Header</h2>');
        $this->assertTrue($cmp->hasSlot('header'));
        $this->assertSame('<h2>My Header</h2>', $cmp->slot('header'));

        $cmp->withSlots([
            'footer' => '<p>Footer Note</p>',
            'sidebar' => '<aside>Tools</aside>'
        ]);
        $this->assertTrue($cmp->hasSlot('footer'));
        $this->assertTrue($cmp->hasSlot('sidebar'));
        $this->assertSame('<p>Footer Note</p>', $cmp->slot('footer'));
    }

    public function testRenderRootAndComponentAlias(): void
    {
        $cmp = new SampleTestComponent(['name' => 'Charlie'], 'test_cmp_root');
        $cmp->hydrate(['status' => 'active']);
        $html = $cmp->render();

        $this->assertStringContains('data-oshim-id="test_cmp_root"', $html);
        $this->assertStringContains('data-oshim-component="sample-test-component"', $html);
        $this->assertStringContains('data-oshim-state="', $html);
        $this->assertStringContains('data-oshim-sig="', $html);
        $this->assertStringContains('class="sample-test-component"', $html);
        $this->assertStringContains('Hello Charlie', $html);
    }
}
