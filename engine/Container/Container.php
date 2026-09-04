<?php
declare(strict_types=1);

namespace Oshim\Container;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionNamedType;
use ReflectionUnionType;
use Oshim\Container\Exceptions\ContainerException;
use Oshim\Container\Exceptions\NotFoundException;
use Oshim\Container\Exceptions\BindingResolutionException;

/**
 * PSR-11 compliant Dependency Injection Container with Reflection Auto-wiring.
 */
class Container implements ContainerInterface
{
    /**
     * Globally accessible container instance.
     */
    protected static ?Container $instance = null;

    /**
     * Registered type bindings.
     * @var array<string, array{concrete: Closure|string|object, shared: bool}>
     */
    protected array $bindings = [];

    /**
     * Resolved singleton instances.
     * @var array<string, object>
     */
    protected array $instances = [];
    protected bool $reflectionCacheLoaded = false;
    protected array $reflectionCache = [];

    /**
     * Registered aliases mapping alias => target abstract.
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * Contextual bindings mapping [ConcreteClass][AbstractTarget] => Implementation.
     * @var array<string, array<string, mixed>>
     */
    protected array $contextual = [];

    /**
     * Registered Service Providers.
     * @var list<ServiceProviderInterface>
     */
    protected array $providers = [];

    /**
     * List of booted service provider class names.
     * @var array<string, bool>
     */
    protected array $bootedProviders = [];

    /**
     * Stack of targets currently being built (for circular dependency detection).
     * @var array<string, bool>
     */
    protected array $buildStack = [];

    /**
     * Set or get the global container instance.
     */
    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        /** @var static */
        return static::$instance;
    }

    public static function setInstance(?Container $container): ?static
    {
        $old = static::$instance;
        static::$instance = $container;
        /** @var static|null */
        return $old;
    }

    /**
     * Register a binding with the container.
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): static
    {
        // Drop stale singleton instance if rebinding
        unset($this->instances[$abstract], $this->aliases[$abstract]);

        if ($concrete === null) {
            $concrete = $abstract;
        }

        if (!$concrete instanceof Closure) {
            if (!is_string($concrete) && !is_object($concrete)) {
                throw new ContainerException("Concrete binding for [{$abstract}] must be a string class name, closure, or object.");
            }
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared'   => $shared,
        ];

        return $this;
    }

    /**
     * Register a shared binding (singleton) in the container.
     */
    public function singleton(string $abstract, mixed $concrete = null): static
    {
        return $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as a shared singleton.
     */
    public function instance(string $abstract, object $instance): static
    {
        unset($this->aliases[$abstract]);
        $this->instances[$abstract] = $instance;
        return $this;
    }

    /**
     * Alias a type to a different name.
     */
    public function alias(string $abstract, string $alias): static
    {
        if ($alias === $abstract) {
            throw new ContainerException("[{$abstract}] cannot be aliased to itself.");
        }
        $this->aliases[$alias] = $abstract;
        return $this;
    }

    /**
     * Get the underlying abstract for an alias.
     */
    public function getAlias(string $abstract): string
    {
        return isset($this->aliases[$abstract]) ? $this->getAlias($this->aliases[$abstract]) : $abstract;
    }

    /**
     * Begin a contextual binding definition.
     */
    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $this->getAlias($concrete));
    }

    /**
     * Add a contextual binding to the container.
     */
    public function addContextualBinding(string $concrete, string $abstract, mixed $implementation): void
    {
        $this->contextual[$concrete][$abstract] = $implementation;
    }

    /**
     * Resolve the given type from the container.
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * PSR-11 get implementation.
     */
    public function get(string $id): mixed
    {
        try {
            return $this->resolve($id);
        } catch (BindingResolutionException $e) {
            if ($this->has($id)) {
                throw $e;
            }
            throw new NotFoundException("No entry was found for identifier [{$id}].", 0, $e);
        }
    }

    /**
     * PSR-11 has implementation.
     */
    public function has(string $id): bool
    {
        $abstract = $this->getAlias($id);
        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract])
            || class_exists($abstract);
    }

    /**
     * Resolve an abstract type to its concrete value.
     */
    protected function resolve(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->getAlias($abstract);

        // If an instance already exists (and no custom parameters are supplied), return it
        if (isset($this->instances[$abstract]) && empty($parameters)) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        // If concrete is the same as abstract or is a class name string
        if ($concrete === $abstract || is_string($concrete)) {
            $object = $this->build($concrete, $parameters);
        } elseif ($concrete instanceof Closure) {
            $object = $concrete($this, $parameters);
        } elseif (is_object($concrete)) {
            $object = $concrete;
        } else {
            throw new BindingResolutionException("Target [{$abstract}] cannot be resolved.");
        }

        // Cache as singleton if configured
        if ($this->isShared($abstract) && empty($parameters)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Get the concrete type for a given abstract.
     */
    protected function getConcrete(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }
        return $abstract;
    }

    /**
     * Determine if a given type is configured as shared.
     */
    public function isShared(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);
        return isset($this->instances[$abstract])
            || (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared'] === true);
    }

    /**
     * Instantiate a concrete instance of the given class via Reflection.
     */
        public function build(string $concrete, array $parameters = []): object
    {
        if (!class_exists($concrete)) {
            throw new BindingResolutionException("Target class [{$concrete}] does not exist.");
        }

        if (isset($this->buildStack[$concrete])) {
            $cycle = implode(' -> ', array_keys($this->buildStack)) . ' -> ' . $concrete;
            throw new BindingResolutionException("Circular dependency detected while resolving [{$concrete}]: {$cycle}");
        }

        $this->buildStack[$concrete] = true;

        try {
            // ADVANCED OPTIMIZATION: Try to use compiled reflection cache first
            if (!$this->reflectionCacheLoaded) {
                $cacheFile = dirname(__DIR__, 2) . '/storage/framework/di_cache.php';
                if (is_file($cacheFile)) {
                    $this->reflectionCache = require $cacheFile;
                }
                $this->reflectionCacheLoaded = true;
            }

            if (isset($this->reflectionCache[$concrete]) && empty($parameters)) {
                $deps = $this->reflectionCache[$concrete];
                $instances = [];
                foreach ($deps as $dep) {
                    // Primitive dependencies are not supported in basic DI cache, fallback
                    if (is_string($dep) && class_exists($dep) || interface_exists($dep)) {
                        $instances[] = $this->make($dep);
                    } else {
                        // Cache miss/invalid for this class, fallback to reflection
                        $instances = null;
                        break;
                    }
                }
                if ($instances !== null) {
                    return new $concrete(...$instances);
                }
            }

            $reflector = new ReflectionClass($concrete);
            if (!$reflector->isInstantiable()) {
                throw new BindingResolutionException("Target [{$concrete}] is not instantiable.");
            }

            $constructor = $reflector->getConstructor();
            if ($constructor === null) {
                return new $concrete();
            }

            $dependencies = $constructor->getParameters();
            $instances = $this->resolveDependencies($dependencies, $parameters, $concrete);

            return $reflector->newInstanceArgs($instances);
        } finally {
            unset($this->buildStack[$concrete]);
        }
    }

    protected function resolveDependencies(array $dependencies, array $parameters, string $concreteClass): array
    {
        $results = [];

        foreach ($dependencies as $index => $parameter) {
            $name = $parameter->getName();

            // 1. Check override parameters (named or positional index)
            if (array_key_exists($name, $parameters) || array_key_exists($index, $parameters)) {
                $val = array_key_exists($name, $parameters) ? $parameters[$name] : $parameters[$index];
                if ($parameter->isVariadic() && is_array($val)) {
                    foreach ($val as $item) {
                        $results[] = $item;
                    }
                } else {
                    $type = $parameter->getType();
                    if ($val !== null && $type instanceof ReflectionNamedType && $type->isBuiltin()) {
                        $typeName = $type->getName();
                        if ($typeName === 'int' && is_numeric($val)) {
                            $val = (int)$val;
                        } elseif ($typeName === 'float' && is_numeric($val)) {
                            $val = (float)$val;
                        } elseif ($typeName === 'bool') {
                            $val = in_array(strtolower((string)$val), ['1', 'true', 'yes', 'on'], true);
                        } elseif ($typeName === 'string') {
                            $val = (string)$val;
                        }
                    }
                    $results[] = $val;
                }
                continue;
            }

            // 2. Check contextual bindings
            $contextualValue = $this->findContextualBinding($concreteClass, $parameter);
            if ($contextualValue !== null) {
                $results[] = $contextualValue instanceof Closure ? $contextualValue($this) : $contextualValue;
                continue;
            }

            // 3. Variadic parameter (do not auto-wire as single class; resolve as empty array)
            if ($parameter->isVariadic()) {
                continue;
            }

            // 4. Resolve class/interface type-hinted dependency
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                if (array_key_exists($className, $parameters)) {
                    $results[] = $parameters[$className];
                    continue;
                }
                foreach ($parameters as $paramVal) {
                    if (is_object($paramVal) && is_a($paramVal, $className)) {
                        $results[] = $paramVal;
                        continue 2;
                    }
                }
                try {
                    $results[] = $this->make($className);
                    continue;
                } catch (BindingResolutionException $e) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $results[] = $parameter->getDefaultValue();
                        continue;
                    }
                    if ($parameter->allowsNull()) {
                        $results[] = null;
                        continue;
                    }
                    throw $e;
                }
            }

            // 4. Default parameter value
            if ($parameter->isDefaultValueAvailable()) {
                $results[] = $parameter->getDefaultValue();
                continue;
            }

            // 5. Nullable parameter
            if ($parameter->allowsNull()) {
                $results[] = null;
                continue;
            }

            // 6. Unresolvable parameter
            throw new BindingResolutionException(
                "Unresolvable dependency [{$parameter}] in class [{$concreteClass}]."
            );
        }

        return $results;
    }

    /**
     * Find contextual binding for a parameter.
     */
    protected function findContextualBinding(string $concreteClass, ReflectionParameter $parameter): mixed
    {
        if (!isset($this->contextual[$concreteClass])) {
            return null;
        }

        // By parameter name: '$foo' or 'foo'
        if (isset($this->contextual[$concreteClass]['$' . $parameter->getName()])) {
            return $this->contextual[$concreteClass]['$' . $parameter->getName()];
        }
        if (isset($this->contextual[$concreteClass][$parameter->getName()])) {
            return $this->contextual[$concreteClass][$parameter->getName()];
        }

        // By type class name
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();
            if (isset($this->contextual[$concreteClass][$typeName])) {
                $impl = $this->contextual[$concreteClass][$typeName];
                if (is_string($impl) && (class_exists($impl) || interface_exists($impl))) {
                    return $this->make($impl);
                }
                return $impl;
            }
        }

        return null;
    }

    /**
     * Call the given Closure / [Class, Method] and inject its dependencies.
     */
    public function call(callable|array|string $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$classOrObj, $method] = $callback;
            $object = is_object($classOrObj) ? $classOrObj : $this->make($classOrObj);
            $reflector = new ReflectionMethod($object, $method);
            $args = $this->resolveDependencies($reflector->getParameters(), $parameters, get_class($object));
            return $reflector->invokeArgs($object, $args);
        }

        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback, 2);
            return $this->call([$this->make($class), $method], $parameters);
        }

        if ($callback instanceof Closure || is_callable($callback)) {
            $reflector = new ReflectionFunction(Closure::fromCallable($callback));
            $args = $this->resolveDependencies($reflector->getParameters(), $parameters, 'Closure');
            return $callback(...$args);
        }

        throw new ContainerException("Invalid callback supplied to Container::call().");
    }

    /**
     * Register a service provider with the container.
     */
    public function register(ServiceProviderInterface|string $provider): ServiceProviderInterface
    {
        if (is_string($provider)) {
            $provider = $this->make($provider);
        }

        $provider->register($this);
        $this->providers[] = $provider;

        return $provider;
    }

    /**
     * Boot all registered service providers.
     */
    public function boot(): void
    {
        foreach ($this->providers as $provider) {
            $class = get_class($provider);
            if (!isset($this->bootedProviders[$class])) {
                $provider->boot($this);
                $this->bootedProviders[$class] = true;
            }
        }
    }

    /**
     * Flush all bindings, singletons, and contextual configurations.
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->contextual = [];
        $this->providers = [];
        $this->bootedProviders = [];
        $this->buildStack = [];
    }
}
