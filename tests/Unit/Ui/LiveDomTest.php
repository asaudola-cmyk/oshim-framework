<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Testing\TestCase;
use Oshim\Ui\LiveDom\LiveComponent;
use Oshim\Ui\LiveDom\LiveDom;
use Oshim\Ui\LiveDom\LiveDomClient;
use Oshim\Ui\LiveDom\LiveDomManager;
use Oshim\Ui\LiveDom\LiveDomPayload;
use Oshim\Ui\LiveDom\LiveDomResponse;
use Oshim\Ui\LiveDom\MorphEngine;
use Oshim\Ui\LiveDom\Directives\DirectiveParser;
use Oshim\Ui\LiveDom\Exceptions\ActionNotAllowedException;
use Oshim\Ui\LiveDom\Exceptions\ComponentNotFoundException;
use Oshim\Ui\LiveDom\Exceptions\InvalidSignatureException;

// --- Test Component Fixtures ---

class TestCounterComponent extends LiveComponent
{
    public int $count = 0;
    public string $title = 'Counter';
    public bool $hookUpdatingFired = false;
    public bool $hookUpdatedFired = false;

    public function mount(int $initialCount = 0, string $initialTitle = 'Counter'): void
    {
        $this->count = $initialCount;
        $this->title = $initialTitle;
    }

    public function updating(string $name, mixed $value): void
    {
        if ($name === 'count') {
            $this->hookUpdatingFired = true;
        }
    }

    public function updated(string $name, mixed $value): void
    {
        if ($name === 'count') {
            $this->hookUpdatedFired = true;
        }
    }

    public function increment(): void
    {
        $this->count++;
    }

    public function add(int $amount): void
    {
        $this->count += $amount;
    }

    public function resetCount(): void
    {
        $this->count = 0;
        $this->dispatch('counter:reset', ['count' => 0]);
    }

    public function goToHome(): void
    {
        $this->redirect('/home');
    }

    protected function internalSecret(): string
    {
        return 'classified';
    }

    public function render(): string
    {
        return "<div class=\"counter-box\"><h1>{$this->title}</h1><span>Count: {$this->count}</span><button live:click=\"increment\">+1</button></div>";
    }
}

class TestTodoComponent extends LiveComponent
{
    public array $todos = [];
    public string $newTodoText = '';

    public function addTodo(): void
    {
        if (!$this->validate(['newTodoText' => 'required|min:3'])) {
            return;
        }

        $this->todos[] = [
            'id'   => count($this->todos) + 1,
            'text' => $this->newTodoText,
            'done' => false,
        ];
        $this->newTodoText = '';
        $this->dispatch('todo:added', ['total' => count($this->todos)]);
    }

    public function toggleTodo(int $id): void
    {
        foreach ($this->todos as &$todo) {
            if ($todo['id'] === $id) {
                $todo['done'] = !$todo['done'];
                break;
            }
        }
    }

    public function render(): string
    {
        $items = '';
        foreach ($this->todos as $t) {
            $status = $t['done'] ? 'done' : 'pending';
            $items .= "<li live:key=\"{$t['id']}\" class=\"{$status}\">{$t['text']}</li>";
        }

        $errorMsg = $this->getError('newTodoText') ?? '';
        return "<div class=\"todo-app\"><ul>{$items}</ul><input live:model=\"newTodoText\" /><button live:click=\"addTodo\">Add</button><span class=\"error\">{$errorMsg}</span></div>";
    }
}

// --- LiveDom Unit Test Suite ---

final class LiveDomTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        LiveDom::setSecret('test_sovereign_secret_key_12345');
    }

    public function testComponentInitializationAndMount(): void
    {
        $counter = new TestCounterComponent('test-comp-1');
        $counter->mount(10, 'My Counter');

        $this->assertSame('test-comp-1', $counter->getId());
        $this->assertSame(10, $counter->count);
        $this->assertSame('My Counter', $counter->title);

        $state = $counter->getState();
        $this->assertSame(10, $state['count']);
        $this->assertSame('My Counter', $state['title']);
    }

    public function testLifecycleHooksUpdatingAndUpdated(): void
    {
        $counter = new TestCounterComponent('test-comp-2');
        $this->assertFalse($counter->hookUpdatingFired);
        $this->assertFalse($counter->hookUpdatedFired);

        $counter->set('count', 5);
        $this->assertSame(5, $counter->count);
        $this->assertTrue($counter->hookUpdatingFired);
        $this->assertTrue($counter->hookUpdatedFired);
    }

    public function testActionInvocationWithArguments(): void
    {
        $counter = new TestCounterComponent('test-comp-3');
        $counter->callAction('increment');
        $this->assertSame(1, $counter->count);

        $counter->callAction('add', [5]);
        $this->assertSame(6, $counter->count);

        $counter->callAction('resetCount');
        $this->assertSame(0, $counter->count);

        $events = $counter->getDispatchedEvents();
        $this->assertCount(1, $events);
        $this->assertSame('counter:reset', $events[0]['name']);
        $this->assertSame(0, $events[0]['detail']['count']);
    }

    public function testRedirectLifecycleHelper(): void
    {
        $counter = new TestCounterComponent('test-comp-redirect');
        $this->assertNull($counter->getRedirectUrl());

        $counter->callAction('goToHome');
        $this->assertSame('/home', $counter->getRedirectUrl());
    }

    public function testSecurityActionProtection(): void
    {
        $counter = new TestCounterComponent('test-comp-4');

        // Cannot call protected method
        $this->assertThrows(function () use ($counter) {
            $counter->callAction('internalSecret');
        }, ActionNotAllowedException::class);

        // Cannot call lifecycle method
        $this->assertThrows(function () use ($counter) {
            $counter->callAction('mount');
        }, ActionNotAllowedException::class);

        // Cannot call internal snapshot creation
        $this->assertThrows(function () use ($counter) {
            $counter->callAction('createSnapshot');
        }, ActionNotAllowedException::class);

        // Cannot call non-existent method
        $this->assertThrows(function () use ($counter) {
            $counter->callAction('doesNotExist');
        }, ActionNotAllowedException::class);
    }

    public function testSnapshotSignedPayloadAndTamperProtection(): void
    {
        $counter = new TestCounterComponent('comp-sec-1');
        $counter->count = 42;
        $counter->title = 'Secure';

        $snapshot = $counter->createSnapshot();
        $this->assertSame('comp-sec-1', $snapshot->getId());
        $this->assertSame(42, $snapshot->getState()['count']);
        $this->assertTrue($snapshot->verify());

        // Restore successfully
        $restored = TestCounterComponent::fromSnapshot($snapshot);
        $this->assertSame(42, $restored->count);
        $this->assertSame('Secure', $restored->title);

        // Tamper state -> verify must throw InvalidSignatureException
        $tamperedArray = $snapshot->toArray();
        $tamperedArray['state']['count'] = 99999;

        $this->assertThrows(function () use ($tamperedArray) {
            TestCounterComponent::fromSnapshot($tamperedArray);
        }, InvalidSignatureException::class);

        // Tamper checksum -> verify must throw
        $tamperedArray2 = $snapshot->toArray();
        $tamperedArray2['checksum'] = 'forged_checksum_hash_value';

        $this->assertThrows(function () use ($tamperedArray2) {
            TestCounterComponent::fromSnapshot($tamperedArray2);
        }, InvalidSignatureException::class);
    }

    public function testValidationAndErrorBag(): void
    {
        $todo = new TestTodoComponent('todo-1');
        $todo->newTodoText = 'ab'; // too short (< 3)

        $todo->callAction('addTodo');
        $this->assertCount(0, $todo->todos);
        $this->assertTrue($todo->hasError('newTodoText'));
        $this->assertNotEmpty($todo->getError('newTodoText'));

        // Valid todo
        $todo->newTodoText = 'Buy groceries';
        $todo->callAction('addTodo');
        $this->assertCount(1, $todo->todos);
        $this->assertSame('Buy groceries', $todo->todos[0]['text']);
        $this->assertFalse($todo->todos[0]['done']);
        $this->assertFalse($todo->hasError('newTodoText'));

        // Toggle todo
        $todo->callAction('toggleTodo', [1]);
        $this->assertTrue($todo->todos[0]['done']);
    }

    public function testRenderWithLiveDomRootAttributes(): void
    {
        $counter = new TestCounterComponent('comp-markup-1');
        $counter->count = 7;
        $html = $counter->renderWithLiveDom();

        $this->assertStringContainsString('data-live-id="comp-markup-1"', $html);
        $this->assertStringContainsString('data-live-component="test-counter"', $html);
        $this->assertStringContainsString('data-live-snapshot="', $html);
        $this->assertStringContainsString('data-live-checksum="', $html);
        $this->assertStringContainsString('<span>Count: 7</span>', $html);
    }

    public function testMorphEngineDiffCalculation(): void
    {
        $morph = new MorphEngine();

        // Identical HTML
        $same = '<div class="card"><p>Hello</p></div>';
        $diffSame = $morph->diff($same, $same);
        $this->assertFalse($diffSame['has_changes']);
        $this->assertCount(0, $diffSame['patches']);

        // Changed text and attribute
        $oldHtml = '<div class="card" id="c1"><p>Count: 0</p></div>';
        $newHtml = '<div class="card active" id="c1"><p>Count: 1</p></div>';

        $diff = $morph->diff($oldHtml, $newHtml);
        $this->assertTrue($diff['has_changes']);
        $this->assertNotEmpty($diff['patches']);

        // Root tag replacement
        $htmlA = '<div class="box">Content</div>';
        $htmlB = '<section class="box">Content</section>';
        $diffRoot = $morph->diff($htmlA, $htmlB);
        $this->assertTrue($diffRoot['has_changes']);
        $this->assertSame('REPLACE_ROOT', $diffRoot['patches'][0]['op']);
    }

    public function testDirectiveParser(): void
    {
        // Attribute with modifiers
        $parsedAttr = DirectiveParser::parseAttribute('live:click.prevent.stop');
        $this->assertNotNull($parsedAttr);
        $this->assertSame('click', $parsedAttr['type']);
        $this->assertContains('prevent', $parsedAttr['modifiers']);
        $this->assertContains('stop', $parsedAttr['modifiers']);

        // Debounce parsing
        $parsedDebounce = DirectiveParser::parseAttribute('live:input.debounce.300ms');
        $this->assertSame(300, $parsedDebounce['debounce']);
        $this->assertContains('debounce', $parsedDebounce['modifiers']);

        // Poll parsing
        $parsedPoll = DirectiveParser::parseAttribute('live:poll.5s');
        $this->assertTrue($parsedPoll['is_poll']);
        $this->assertSame(5000, $parsedPoll['debounce']);

        // Model parsing
        $parsedModel = DirectiveParser::parseAttribute('live:model.lazy');
        $this->assertTrue($parsedModel['is_model']);
        $this->assertContains('lazy', $parsedModel['modifiers']);

        // Expression parsing
        $expr1 = DirectiveParser::parseExpression('increment');
        $this->assertSame('increment', $expr1['action']);
        $this->assertSame([], $expr1['args']);

        $expr2 = DirectiveParser::parseExpression("setFilter('active', 42, true, null)");
        $this->assertSame('setFilter', $expr2['action']);
        $this->assertSame(['active', 42, true, null], $expr2['args']);
    }

    public function testLiveDomManagerRequestRoundtrip(): void
    {
        $manager = new LiveDomManager();
        $manager->register('counter', TestCounterComponent::class);

        $initial = $manager->resolve('counter', [5, 'Initial']);
        $this->assertSame(5, $initial->count);

        $snapshot = $initial->createSnapshot();

        // 1. Send action request: increment
        $res = $manager->handleRequest([
            'id'       => $initial->getId(),
            'action'   => 'increment',
            'params'   => [],
            'snapshot' => $snapshot->toArray(),
        ]);

        $this->assertTrue($res->isSuccess());
        $this->assertStringContainsString('Count: 6', $res->getHtml());
        $this->assertNotEmpty($res->getSnapshot());
        $this->assertNotEmpty($res->getPatches());

        // 2. Send action request with params: add(10)
        $res2 = $manager->handleRequest([
            'id'       => $initial->getId(),
            'action'   => 'add',
            'params'   => [10],
            'snapshot' => $res->getSnapshot(),
        ]);

        $this->assertTrue($res2->isSuccess());
        $this->assertStringContainsString('Count: 16', $res2->getHtml());

        // 3. Send two-way binding $set action
        $res3 = $manager->handleRequest([
            'id'       => $initial->getId(),
            'action'   => '$set',
            'params'   => ['title', 'Updated Counter Title'],
            'snapshot' => $res2->getSnapshot(),
        ]);

        $this->assertTrue($res3->isSuccess());
        $this->assertStringContainsString('Updated Counter Title', $res3->getHtml());
    }

    public function testLiveDomManagerTamperRejection(): void
    {
        $manager = new LiveDomManager();
        $manager->register('counter', TestCounterComponent::class);

        $initial = $manager->resolve('counter');
        $snapshot = $initial->createSnapshot()->toArray();

        // Tamper state
        $snapshot['state']['count'] = 99999;

        $response = $manager->handleRequest([
            'id'       => $initial->getId(),
            'action'   => 'increment',
            'params'   => [],
            'snapshot' => $snapshot,
        ]);

        $this->assertFalse($response->isSuccess());
        $this->assertArrayHasKey('security', $response->getErrors());
    }

    public function testLiveDomFacadeRegistrationAndAssets(): void
    {
        LiveDom::register('test-todo', TestTodoComponent::class);
        $this->assertTrue(LiveDom::getManager()->has('test-todo'));

        $rendered = LiveDom::render('test-todo');
        $this->assertStringContainsString('data-live-component="test-todo"', $rendered);
        $this->assertStringContainsString('todo-app', $rendered);

        $scriptTag = LiveDom::script();
        $this->assertStringContainsString('<script>', $scriptTag);
        $this->assertStringContainsString('window.LiveDom = LiveDom;', $scriptTag);
        $this->assertStringContainsString('morph:', $scriptTag);

        $stylesTag = LiveDom::styles();
        $this->assertStringContainsString('<style>', $stylesTag);
        $this->assertStringContainsString('live-loading-active', $stylesTag);

        $assets = LiveDom::assets();
        $this->assertStringContainsString('<style>', $assets);
        $this->assertStringContainsString('<script>', $assets);
    }

    public function testClientRuntimeScriptIntegrity(): void
    {
        $script = LiveDomClient::getScript();

        $this->assertNotEmpty($script);
        $this->assertStringContainsString('window.LiveDom', $script);
        $this->assertStringContainsString('morphChildren:', $script);
        $this->assertStringContainsString('initModelBindings:', $script);
        $this->assertStringContainsString('initEventDelegation:', $script);
        $this->assertStringContainsString('toggleLoading:', $script);
        $this->assertStringContainsString('call:', $script);
    }
}
