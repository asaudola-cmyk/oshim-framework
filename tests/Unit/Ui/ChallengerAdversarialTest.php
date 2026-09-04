<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Tests\Harness\TestCase;
use Oshim\Ui\Component;
use Oshim\Ui\ComponentRegistry;
use Oshim\Ui\DiffEngine;
use Oshim\Ui\UiManager;
use Oshim\Ui\Components\Button;
use Oshim\Ui\Components\Card;
use Oshim\Ui\Components\Table;
use Oshim\Ui\Components\Chart;
use Oshim\Ui\Components\Modal;
use Oshim\Ui\Components\Form;
use Oshim\Ui\Components\Sidebar;
use Oshim\Ui\Components\Navbar;
use Oshim\Ui\Components\Terminal;
use Oshim\Ui\Components\DataGrid;
use Oshim\Ui\Components\StatusBadge;
use Oshim\Ui\Exceptions\ComponentActionException;
use Oshim\Ui\Exceptions\ComponentNotFoundException;
use Oshim\Ui\Exceptions\InvalidSignatureException;
use Oshim\Ui\Exceptions\UiException;
use Throwable;

// Concrete test component fixture for adversarial testing
class AdversarialFixtureComponent extends Component
{
    public bool $actionExecuted = false;
    public mixed $lastPayload = null;

    public function render(): string
    {
        $role = $this->escape($this->state['role'] ?? 'guest');
        $counter = (int)($this->state['counter'] ?? 0);
        return $this->renderRoot('div', "<span class=\"role-badge\">{$role}</span><span class=\"count\">{$counter}</span>");
    }

    public function increment(array $payload = []): int
    {
        $this->actionExecuted = true;
        $this->lastPayload = $payload;
        $step = (int)($payload['step'] ?? 1);
        $this->state['counter'] = ($this->state['counter'] ?? 0) + $step;
        return $this->state['counter'];
    }

    public function changeRole(array $payload = []): void
    {
        $this->actionExecuted = true;
        $this->lastPayload = $payload;
        $this->state['role'] = (string)($payload['role'] ?? 'guest');
    }

    public function dangerousInternalHelper(): string
    {
        return 'danger_internal_executed';
    }
}

/**
 * Empirical Adversarial Challenger Test Suite for Milestone 2 (UI Engine & Design System).
 */
class ChallengerAdversarialTest extends TestCase
{
    protected ComponentRegistry $registry;
    protected DiffEngine $diffEngine;
    protected UiManager $uiManager;

    protected function setUp(): void
    {
        $this->registry = new ComponentRegistry();
        $this->registry->register('adversarial-fixture', AdversarialFixtureComponent::class);
        $this->diffEngine = new DiffEngine();
        $this->uiManager = new UiManager($this->registry, $this->diffEngine);
    }

    // =========================================================================
    // SECTION 1: STATE HYDRATION & DEHYDRATION SECURITY
    // =========================================================================

    public function testHmacSignatureTamperingWithModifiedState(): void
    {
        $cmp = new AdversarialFixtureComponent([], 'cmp_sec_01');
        $initialState = ['role' => 'guest', 'counter' => 0];
        $sig = $cmp->generateSignature($initialState);

        // Attacker attempts privilege escalation to 'superadmin' using original HMAC
        $forgedState = ['role' => 'superadmin', 'counter' => 0];
        $this->assertFalse($cmp->verifySignature($forgedState, $sig), "Forged state must be rejected by verifySignature.");

        // Verify UiManager throws InvalidSignatureException on forged state
        $this->assertThrows(InvalidSignatureException::class, function () use ($cmp, $forgedState, $sig) {
            $this->uiManager->processAction('adversarial-fixture', $cmp->getId(), 'increment', [], $forgedState, $sig);
        });
    }

    public function testHmacSignatureTamperingWithModifiedComponentId(): void
    {
        $cmpA = new AdversarialFixtureComponent([], 'cmp_alice');
        $state = ['role' => 'operator', 'counter' => 10];
        $sigA = $cmpA->generateSignature($state);

        // Attacker attempts to replay signature generated for Alice onto Bob
        $cmpB = new AdversarialFixtureComponent([], 'cmp_bob');
        $this->assertFalse($cmpB->verifySignature($state, $sigA), "Signature bound to cmp_alice must not be valid for cmp_bob.");

        $this->assertThrows(InvalidSignatureException::class, function () use ($cmpB, $state, $sigA) {
            $this->uiManager->processAction('adversarial-fixture', $cmpB->getId(), 'increment', [], $state, $sigA);
        });
    }

    public function testHmacSignatureWithAlteredSecretKey(): void
    {
        $cmp = new AdversarialFixtureComponent([], 'cmp_key_test');
        $state = ['role' => 'viewer'];

        Component::setSecretKey('secret_key_alpha_1234567890123456');
        $sigAlpha = $cmp->generateSignature($state);

        // Secret key rotated / altered
        Component::setSecretKey('secret_key_beta_9876543210987654');
        $this->assertFalse($cmp->verifySignature($state, $sigAlpha), "Signature under Key Alpha must be invalid under Key Beta.");

        // Reset to default
        Component::setSecretKey('oshim_default_ui_secret_change_in_prod');
    }

    public function testHmacSignatureWithTruncatedOrCorruptedSignatures(): void
    {
        $cmp = new AdversarialFixtureComponent([], 'cmp_sig_corrupt');
        $state = ['role' => 'admin'];
        $validSig = $cmp->generateSignature($state);

        // Truncated signature
        $truncatedSig = substr($validSig, 0, 32);
        $this->assertFalse($cmp->verifySignature($state, $truncatedSig));

        // 1-bit flipped signature
        $flippedSig = $validSig;
        $flippedSig[0] = ($flippedSig[0] === 'a') ? 'b' : 'a';
        $this->assertFalse($cmp->verifySignature($state, $flippedSig));

        // Injected characters & null bytes
        $injectedSig = $validSig . "\0";
        $this->assertFalse($cmp->verifySignature($state, $injectedSig));

        // Empty signature
        $this->assertFalse($cmp->verifySignature($state, ''));
    }

    public function testReplayAttackWithInjectedStateProps(): void
    {
        $cmp = new AdversarialFixtureComponent(['immutableProp' => 'CANNOT_TOUCH'], 'cmp_props_immut');
        $this->assertSame('CANNOT_TOUCH', $cmp->getProps()['immutableProp']);

        // Attacker sends state attempting to override immutable props
        $tamperedState = ['immutableProp' => 'OVERWRITTEN', 'counter' => 1];
        $sig = $cmp->generateSignature($tamperedState);

        $result = $this->uiManager->processAction('adversarial-fixture', $cmp->getId(), 'increment', [], $tamperedState, $sig);
        $this->assertTrue($result['success']);

        // Props on newly resolved instance remain immutable
        $resolved = $this->registry->resolve('adversarial-fixture', ['immutableProp' => 'CANNOT_TOUCH'], $cmp->getId());
        $resolved->hydrate($result['state']);
        $this->assertSame('CANNOT_TOUCH', $resolved->getProps()['immutableProp']);
    }

    public function testMalformedOrCorruptedStatePayloadDirectProcessing(): void
    {
        $cmp = new AdversarialFixtureComponent([], 'cmp_malformed_direct');
        $invalidSig = 'invalid_tampered_sig';

        // 1. Invalid signature
        $this->assertThrows(InvalidSignatureException::class, function () use ($cmp, $invalidSig) {
            $this->uiManager->processAction('adversarial-fixture', $cmp->getId(), 'increment', [], ['counter' => 0], $invalidSig);
        });

        // 2. Empty signature
        $this->assertThrows(InvalidSignatureException::class, function () use ($cmp) {
            $this->uiManager->processAction('adversarial-fixture', $cmp->getId(), 'increment', [], ['counter' => 0], '');
        });

        // 3. Component not found
        $this->assertThrows(ComponentNotFoundException::class, function () {
            $this->uiManager->processAction('non_existent_component', 'id_1', 'increment', [], [], 'sig');
        });
    }

    public function testTypeStrictnessInStateSerialization(): void
    {
        $cmp = new AdversarialFixtureComponent([], 'cmp_type_strict');

        // Integer 1 vs String "1"
        $stateInt = ['val' => 1];
        $stateStr = ['val' => "1"];
        $sigInt = $cmp->generateSignature($stateInt);
        $sigStr = $cmp->generateSignature($stateStr);

        $this->assertNotSame($sigInt, $sigStr, "Integer 1 and string '1' must produce distinct HMAC signatures.");
        $this->assertFalse($cmp->verifySignature($stateStr, $sigInt));
        $this->assertFalse($cmp->verifySignature($stateInt, $sigStr));

        // Boolean true vs Integer 1
        $stateBool = ['val' => true];
        $sigBool = $cmp->generateSignature($stateBool);
        $this->assertNotSame($sigBool, $sigInt);
    }

    // =========================================================================
    // SECTION 2: COMPONENT LIFECYCLE & EVENT EMISSION
    // =========================================================================

    public function testRestrictedLifecycleMethodsBlockedFromDispatch(): void
    {
        $cmp = new AdversarialFixtureComponent([], 'cmp_restricted_01');
        $restrictedList = [
            'mount', 'render', 'hydrate', 'dehydrate', 'dispatch', 'emit',
            'generateSignature', 'verifySignature', 'verifyChecksum', 'renderRoot',
            'getComponentAlias', 'escape', '__construct', '__destruct',
        ];

        foreach ($restrictedList as $action) {
            $this->assertThrows(ComponentActionException::class, function () use ($cmp, $action) {
                $cmp->dispatch($action, []);
            }, null, "Restricted method [{$action}] must throw ComponentActionException.");
        }
    }

    public function testNonExistentOrDangerousGlobalMethodsBlocked(): void
    {
        $cmp = new AdversarialFixtureComponent([], 'cmp_dangerous_01');
        $forbidden = ['system', 'exec', 'passthru', 'eval', 'shell_exec', 'unlink', 'nonExistentAction'];

        foreach ($forbidden as $action) {
            $this->assertThrows(ComponentActionException::class, function () use ($cmp, $action) {
                $cmp->dispatch($action, []);
            }, null, "Arbitrary function [{$action}] must throw ComponentActionException.");
        }
    }

    public function testMultipleEventEmissionsAndFifoOrder(): void
    {
        $cmp = new AdversarialFixtureComponent([], 'cmp_events_01');
        $this->assertEmpty($cmp->getEmittedEvents());

        // Emit multiple events with varied payloads
        $cmp->emit('node:provisioned', ['ip' => '10.0.0.1', 'vcpus' => 4]);
        $cmp->emit('billing:charged', ['amount' => 29.99, 'currency' => 'USD']);
        $cmp->emit('notification:sent', ['user_id' => 'usr_99', 'type' => 'email']);

        $emitted = $cmp->getEmittedEvents();
        $this->assertCount(3, $emitted);

        $this->assertSame('node:provisioned', $emitted[0]['event']);
        $this->assertSame('10.0.0.1', $emitted[0]['payload']['ip']);
        $this->assertSame('cmp_events_01', $emitted[0]['source']);

        $this->assertSame('billing:charged', $emitted[1]['event']);
        $this->assertSame(29.99, $emitted[1]['payload']['amount']);

        $this->assertSame('notification:sent', $emitted[2]['event']);
        $this->assertSame('usr_99', $emitted[2]['payload']['user_id']);
    }

    // =========================================================================
    // SECTION 3: DIFF ENGINE & MORPH PATCHING
    // =========================================================================

    public function testDiffEngineIdenticalHtmlProducesEmptyPatches(): void
    {
        $html = '<div data-oshim-id="cmp_01" class="oshim-card"><p>No Change</p></div>';
        $patches = $this->diffEngine->diff($html, $html, 'cmp_01');
        $this->assertSame([], $patches, "Identical HTML must result in zero diff patches.");
    }

    public function testDiffEngineChangedHtmlProducesReplacePatch(): void
    {
        $oldHtml = '<div data-oshim-id="cmp_01" class="oshim-card"><p>State A</p></div>';
        $newHtml = '<div data-oshim-id="cmp_01" class="oshim-card"><p>State B</p></div>';
        $patches = $this->diffEngine->diff($oldHtml, $newHtml, 'cmp_01');

        $this->assertCount(1, $patches);
        $this->assertSame('REPLACE_HTML', $patches[0]['op']);
        $this->assertSame('cmp_01', $patches[0]['id']);
        $this->assertSame($newHtml, $patches[0]['html']);
    }

    public function testDiffEngineEmptyStringsAndNullSafety(): void
    {
        $patchesEmpty = $this->diffEngine->diff('', '', 'cmp_empty');
        $this->assertSame([], $patchesEmpty);

        $patchesFromEmpty = $this->diffEngine->diff('', '<span>Created</span>', 'cmp_empty');
        $this->assertCount(1, $patchesFromEmpty);
        $this->assertSame('REPLACE_HTML', $patchesFromEmpty[0]['op']);
    }

    public function testDiffEngineCustomPatchHelper(): void
    {
        $patchSetAttr = $this->diffEngine->createPatch('SET_ATTR', 'el_1', ['key' => 'class', 'val' => 'active']);
        $this->assertSame('SET_ATTR', $patchSetAttr['op']);
        $this->assertSame('el_1', $patchSetAttr['id']);
        $this->assertSame('class', $patchSetAttr['key']);
        $this->assertSame('active', $patchSetAttr['val']);

        $patchEvent = $this->diffEngine->createPatch('DISPATCH_EVENT', 'el_1', ['eventName' => 'toast', 'detail' => ['msg' => 'Success']]);
        $this->assertSame('DISPATCH_EVENT', $patchEvent['op']);
        $this->assertSame('toast', $patchEvent['eventName']);
    }

    // =========================================================================
    // SECTION 4: ADVERSARIAL XSS PAYLOAD SANITIZATION ACROSS ALL 11 COMPONENTS
    // =========================================================================

    public function testXssSanitizationInButton(): void
    {
        $xss = '<script>alert("xss_button")</script>';
        $xssAttr = '"><img src=x onerror=alert(1)>';

        $btn = new Button([
            'label'   => $xss,
            'action'  => $xssAttr,
            'class'   => $xssAttr,
            'payload' => ['k' => $xss],
        ]);
        $html = $btn->render();

        $this->assertStringNotContains('<script>alert("xss_button")</script>', $html);
        $this->assertStringContains('&lt;script&gt;alert(&quot;xss_button&quot;)&lt;/script&gt;', $html);
        $this->assertStringNotContains('"><img src=x onerror=alert(1)>', $html);
    }

    public function testXssSanitizationInCard(): void
    {
        $xssTitle = '"><script>alert("card_title")</script>';
        $xssSub = '<svg/onload=alert("card_sub")>';
        $xssClass = '" onmouseover="alert(1)" class="evil';

        $card = new Card([
            'title'    => $xssTitle,
            'subtitle' => $xssSub,
            'class'    => $xssClass,
        ]);
        $html = $card->render();

        $this->assertStringNotContains('<script>alert("card_title")</script>', $html);
        $this->assertStringNotContains('<svg/onload=alert("card_sub")>', $html);
        $this->assertStringNotContains('" onmouseover="alert(1)"', $html);
    }

    public function testXssSanitizationInTable(): void
    {
        $xss = '<script>alert("table_xss")</script>';
        $table = new Table([
            'columns'      => [['key' => 'col1', 'label' => $xss]],
            'data'         => [['col1' => $xss]],
            'caption'      => $xss,
            'emptyMessage' => $xss,
        ]);
        $html = $table->render();

        $this->assertStringNotContains('<script>alert("table_xss")</script>', $html);
        $this->assertStringContains('&lt;script&gt;alert(&quot;table_xss&quot;)&lt;/script&gt;', $html);
    }

    public function testXssSanitizationInChart(): void
    {
        $xss = '<script>alert("chart_xss")</script>';
        $chart = new Chart([
            'type'  => 'gauge',
            'title' => $xss,
            'unit'  => $xss,
            'data'  => ['value' => 50.0],
        ]);
        $html = $chart->render();

        $this->assertStringNotContains('<script>alert("chart_xss")</script>', $html);
        $this->assertStringContains('&lt;script&gt;alert(&quot;chart_xss&quot;)&lt;/script&gt;', $html);
    }

    public function testXssSanitizationInModal(): void
    {
        $xss = '<script>alert("modal_xss")</script>';
        $modal = new Modal([
            'name'     => 'modal_" onfocus="alert(1)',
            'title'    => $xss,
            'subtitle' => $xss,
        ]);
        $html = $modal->render();

        $this->assertStringNotContains('<script>alert("modal_xss")</script>', $html);
        $this->assertStringNotContains('" onfocus="alert(1)', $html);
    }

    public function testXssSanitizationInForm(): void
    {
        $xss = '<script>alert("form_xss")</script>';
        $form = new Form([
            'action' => '"><script>alert(1)</script>',
            'fields' => [
                'field_evil' => [
                    'label'       => $xss,
                    'placeholder' => $xss,
                    'help'        => $xss,
                    'type'        => 'text',
                ],
            ],
            'values' => [
                'field_evil' => $xss,
            ],
            'errors' => [
                'field_evil' => $xss,
            ],
        ]);
        $html = $form->render();

        $this->assertStringNotContains('<script>alert("form_xss")</script>', $html);
        $this->assertStringNotContains('"><script>alert(1)</script>', $html);
        $this->assertStringContains('&lt;script&gt;alert(&quot;form_xss&quot;)&lt;/script&gt;', $html);
    }

    public function testXssSanitizationInSidebar(): void
    {
        $xss = '<script>alert("sidebar_xss")</script>';
        $sidebar = new Sidebar([
            'brand'       => ['name' => $xss, 'url' => 'javascript:alert(1)'],
            'items'       => [
                ['label' => $xss, 'url' => 'javascript:alert(2)', 'badge' => $xss],
            ],
            'userProfile' => ['name' => $xss, 'role' => $xss],
        ]);
        $html = $sidebar->render();

        $this->assertStringNotContains('<script>alert("sidebar_xss")</script>', $html);
    }

    public function testXssSanitizationInNavbar(): void
    {
        $xss = '<script>alert("navbar_xss")</script>';
        $navbar = new Navbar([
            'breadcrumbs'       => [['label' => $xss, 'url' => 'javascript:alert(1)']],
            'searchPlaceholder' => $xss,
            'user'              => ['name' => $xss, 'avatar' => '"><img src=x onerror=alert(1)>'],
        ]);
        $html = $navbar->render();

        $this->assertStringNotContains('<script>alert("navbar_xss")</script>', $html);
        $this->assertStringNotContains('"><img src=x onerror=alert(1)>', $html);
    }

    public function testXssSanitizationInTerminal(): void
    {
        $xss = '"><script>alert("terminal_xss")</script>';
        $term = new Terminal([
            'instanceId' => $xss,
            'title'      => $xss,
            'wsEndpoint' => $xss,
        ]);
        $html = $term->render();

        $this->assertStringNotContains('<script>alert("terminal_xss")</script>', $html);
    }

    public function testXssSanitizationInDataGrid(): void
    {
        $xss = '<script>alert("datagrid_xss")</script>';
        $grid = new DataGrid([
            'columns'      => [['key' => 'col1', 'label' => $xss]],
            'items'        => [['id' => 'row_1', 'col1' => $xss]],
            'emptyMessage' => $xss,
            'bulkActions'  => [['action' => 'delete', 'label' => $xss]],
        ]);
        $html = $grid->render();

        $this->assertStringNotContains('<script>alert("datagrid_xss")</script>', $html);
        $this->assertStringContains('&lt;script&gt;alert(&quot;datagrid_xss&quot;)&lt;/script&gt;', $html);
    }

    public function testXssSanitizationInStatusBadge(): void
    {
        $xss = '<script>alert("badge_xss")</script>';
        $badge = new StatusBadge([
            'status' => 'running',
            'label'  => $xss,
            'class'  => '"><svg/onload=alert(1)>',
        ]);
        $html = $badge->render();

        $this->assertStringNotContains('<script>alert("badge_xss")</script>', $html);
        $this->assertStringNotContains('"><svg/onload=alert(1)>', $html);
    }

    // =========================================================================
    // SECTION 5: NESTED COMPONENTS & SLOT MUTATIONS
    // =========================================================================

    public function testNestedComponentSlotRendering(): void
    {
        $btn = new Button(['label' => 'Reboot Node', 'variant' => 'danger']);
        $card = new Card([
            'title' => 'Server Actions',
            'body'  => $btn->render(),
        ]);
        $html = $card->render();

        $this->assertStringContains('Server Actions', $html);
        $this->assertStringContains('oshim-card', $html);
        $this->assertStringContains('oshim-btn--danger', $html);
        $this->assertStringContains('Reboot Node', $html);
    }

    public function testModalWithEmbeddedFormRendering(): void
    {
        $form = new Form([
            'action' => '/api/vps/create',
            'fields' => [
                'name' => ['label' => 'Server Name', 'type' => 'text'],
            ],
        ]);
        $modal = new Modal([
            'title' => 'Create Server Modal',
            'body'  => $form->render(),
            'isOpen' => true,
        ]);
        $html = $modal->render();

        $this->assertStringContains('Create Server Modal', $html);
        $this->assertStringContains('oshim-form', $html);
        $this->assertStringContains('Server Name', $html);
    }

    // =========================================================================
    // SECTION 6: CONCURRENCY & STREAM SIMULATION
    // =========================================================================

    public function testSseStreamEndpointHeadersAndFormat(): void
    {
        $req = new Request(
            method: 'GET',
            uri: '/oshim/ui/sse?channel=metrics&component_id=chart_cpu_01'
        );

        $sseResponse = $this->uiManager->handleSse($req);
        $this->assertStringContains('text/event-stream', (string)$sseResponse->getHeaders()->get('Content-Type'));
        $this->assertStringContains('no-cache', (string)$sseResponse->getHeaders()->get('Cache-Control'));
    }

    public function testConcurrentActionProcessingStateIsolation(): void
    {
        // Simulate 20 rapid sequential requests across distinct component instances
        for ($i = 1; $i <= 20; $i++) {
            $cmpId = "cmp_concurrent_{$i}";
            $cmp = new AdversarialFixtureComponent([], $cmpId);

            $state = ['counter' => $i * 10, 'role' => "role_{$i}"];
            $sig = $cmp->generateSignature($state);

            $result = $this->uiManager->processAction('adversarial-fixture', $cmpId, 'increment', ['step' => 5], $state, $sig);

            $this->assertTrue($result['success']);
            $this->assertSame($cmpId, $result['id']);
            $this->assertSame(($i * 10) + 5, $result['state']['counter']);
            $this->assertSame("role_{$i}", $result['state']['role']);

            // Verify fresh valid signature for the mutated state
            $resolved = $this->registry->resolve('adversarial-fixture', [], $cmpId);
            $this->assertTrue($resolved->verifySignature($result['state'], $result['sig']));
        }
    }
}
