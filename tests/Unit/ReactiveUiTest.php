<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Reactive\ReactiveComponent;
use Oshim\Ui\Reactive\DomMorphDiff;
use Oshim\Ui\Form\FormValidator;
use RuntimeException;

class CounterComponent extends ReactiveComponent
{
    public int $count = 0;
    public string $message = 'Hello';

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): string
    {
        return "<div class=\"counter\"><span>Count: {$this->count}</span><button wire:click=\"increment\">+1</button></div>";
    }
}

final class ReactiveUiTest extends TestCase
{
    public function testReactiveComponentStateAndHydration(): void
    {
        $component = new CounterComponent('comp-1');
        $this->assertSame(0, $component->count);

        $component->callAction('increment');
        $this->assertSame(1, $component->count);

        $payload = $component->createSignedPayload();
        $this->assertSame('comp-1', $payload['id']);
        $this->assertSame(1, $payload['state']['count']);
        $this->assertNotEmpty($payload['checksum']);

        // Restore from signed payload
        $restored = CounterComponent::restoreFromSignedPayload($payload);
        $this->assertSame(1, $restored->count);

        // Tamper state -> checksum fails
        $payload['state']['count'] = 999;
        $this->assertThrows(function () use ($payload) {
            CounterComponent::restoreFromSignedPayload($payload);
        }, RuntimeException::class);
    }

    public function testDomMorphDiffEngine(): void
    {
        $oldHtml = '<div class="card"><p>Count: 0</p></div>';
        $newHtml = '<div class="card"><p>Count: 1</p></div>';

        $diff = DomMorphDiff::diff($oldHtml, $newHtml);
        $this->assertTrue($diff['has_changes']);
        $this->assertCount(1, $diff['patches']);
        $this->assertSame('MORPH_INNER_HTML', $diff['patches'][0]['type']);

        $sameDiff = DomMorphDiff::diff($oldHtml, $oldHtml);
        $this->assertFalse($sameDiff['has_changes']);
    }

    public function testFormValidatorRules(): void
    {
        $rules = [
            'name' => 'required|min:3',
            'email' => 'required|email',
            'age' => 'required|numeric|min:18',
        ];

        // Valid data
        $validator = FormValidator::make([
            'name' => 'Shafiullah',
            'email' => 'shafi@example.com',
            'age' => 25,
        ], $rules);

        $this->assertTrue($validator->passes());
        $this->assertEmpty($validator->getErrors());

        // Invalid data
        $invalid = FormValidator::make([
            'name' => 'Al',
            'email' => 'not-an-email',
            'age' => 15,
        ], $rules);

        $this->assertTrue($invalid->fails());
        $errors = $invalid->getErrors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('age', $errors);
    }
}
