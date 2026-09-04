<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use Oshim\Testing\TestCase;
use Oshim\Container\Container;
use Oshim\Container\Exceptions\NotFoundException;
use Oshim\Container\Exceptions\BindingResolutionException;

// Helper classes for testing DI
class SimpleDependency
{
    public string $name = 'simple';
}

class NestedDependency
{
    public function __construct(public SimpleDependency $simple)
    {
    }
}

class DefaultParamDependency
{
    public function __construct(public string $config = 'default_val', public ?SimpleDependency $dep = null)
    {
    }
}

class ContextualClientA
{
    public function __construct(public string $apiKey)
    {
    }
}

class ContextualClientB
{
    public function __construct(public string $apiKey)
    {
    }
}

class CircularA
{
    public function __construct(public CircularB $b)
    {
    }
}

class CircularB
{
    public function __construct(public CircularA $a)
    {
    }
}

class ContainerTest extends TestCase
{
    protected Container $container;

    public function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function testContainerBindsAndResolves(): void
    {
        $this->container->bind('foo', fn() => 'bar');
        $this->assertTrue($this->container->has('foo'));
        $this->assertEquals('bar', $this->container->get('foo'));
    }

    public function testContainerSingletonPersistence(): void
    {
        $this->container->singleton('stdClass', fn() => new \stdClass());

        $obj1 = $this->container->make('stdClass');
        $obj2 = $this->container->make('stdClass');

        $this->assertSame($obj1, $obj2);
    }

    public function testContainerInstanceBinding(): void
    {
        $instance = new \stdClass();
        $instance->flag = true;

        $this->container->instance('my_instance', $instance);
        $this->assertSame($instance, $this->container->get('my_instance'));
    }

    public function testContainerAutoWiresConstructor(): void
    {
        /** @var NestedDependency $nested */
        $nested = $this->container->make(NestedDependency::class);

        $this->assertInstanceOf(NestedDependency::class, $nested);
        $this->assertInstanceOf(SimpleDependency::class, $nested->simple);
        $this->assertEquals('simple', $nested->simple->name);
    }

    public function testContainerResolvesDefaultAndNullableParameters(): void
    {
        /** @var DefaultParamDependency $resolved */
        $resolved = $this->container->make(DefaultParamDependency::class);

        $this->assertInstanceOf(DefaultParamDependency::class, $resolved);
        $this->assertEquals('default_val', $resolved->config);
        $this->assertNotNull($resolved->dep);
    }

    public function testContainerContextualBinding(): void
    {
        $this->container->when(ContextualClientA::class)
            ->needs('$apiKey')
            ->give('api_key_A_123');

        $this->container->when(ContextualClientB::class)
            ->needs('$apiKey')
            ->give('api_key_B_456');

        /** @var ContextualClientA $clientA */
        $clientA = $this->container->make(ContextualClientA::class);
        /** @var ContextualClientB $clientB */
        $clientB = $this->container->make(ContextualClientB::class);

        $this->assertEquals('api_key_A_123', $clientA->apiKey);
        $this->assertEquals('api_key_B_456', $clientB->apiKey);
    }

    public function testContainerThrowsNotFoundExceptionForMissingEntry(): void
    {
        $this->assertThrows(function () {
            $this->container->get('non_existent_key_999');
        }, NotFoundException::class);
    }

    public function testContainerCircularDependencyDetection(): void
    {
        $this->assertThrows(function () {
            $this->container->make(CircularA::class);
        }, BindingResolutionException::class, 'Circular dependency');
    }

    public function testContainerMethodInjectionViaCall(): void
    {
        $result = $this->container->call(function (SimpleDependency $dep, int $number = 42) {
            return $dep->name . ':' . $number;
        }, ['number' => 100]);

        $this->assertEquals('simple:100', $result);
    }

    public function testContainerResolvesVariadicConstructorParameters(): void
    {
        $variadic = $this->container->make(VariadicTestClass::class);
        $this->assertEquals([], $variadic->items);

        $withParams = $this->container->make(VariadicTestClass::class, ['items' => ['a', 'b', 'c']]);
        $this->assertEquals(['a', 'b', 'c'], $withParams->items);
    }
}

class VariadicTestClass
{
    public array $items;
    public function __construct(string ...$items)
    {
        $this->items = $items;
    }
}
