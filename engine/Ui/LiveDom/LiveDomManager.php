<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Ui\LiveDom\Exceptions\ActionNotAllowedException;
use Oshim\Ui\LiveDom\Exceptions\ComponentNotFoundException;
use Oshim\Ui\LiveDom\Exceptions\InvalidSignatureException;
use Oshim\Ui\LiveDom\Exceptions\LiveDomException;
use Throwable;

/**
 * Sovereign LiveDOM Manager.
 * Orchestrates component lifecycle, snapshot verification, action execution, and DOM diffing.
 */
class LiveDomManager
{
    /**
     * Component Registry mapping aliases to class names.
     * @var array<string, class-string<LiveComponent>>
     */
    protected array $registry = [];

    protected MorphEngine $morphEngine;

    public function __construct(?MorphEngine $morphEngine = null)
    {
        $this->morphEngine = $morphEngine ?? new MorphEngine();
    }

    public function getMorphEngine(): MorphEngine
    {
        return $this->morphEngine;
    }

    /**
     * Register a reactive LiveDOM component class under an alias.
     */
    public function register(string $alias, string $class): static
    {
        $this->registry[strtolower($alias)] = $class;
        return $this;
    }

    /**
     * Register multiple components at once.
     * @param array<string, string> $map
     */
    public function registerMany(array $map): static
    {
        foreach ($map as $alias => $class) {
            $this->register($alias, $class);
        }
        return $this;
    }

    public function has(string $alias): bool
    {
        return isset($this->registry[strtolower($alias)]) || class_exists($alias);
    }

    public function getRegistered(): array
    {
        return $this->registry;
    }

    /**
     * Resolve and instantiate a LiveComponent instance.
     */
    public function resolve(string $aliasOrClass, array $props = [], ?string $id = null): LiveComponent
    {
        $normalized = strtolower($aliasOrClass);

        if (isset($this->registry[$normalized])) {
            $class = $this->registry[$normalized];
        } elseif (class_exists($aliasOrClass)) {
            $class = $aliasOrClass;
        } else {
            throw new ComponentNotFoundException("LiveDOM component [{$aliasOrClass}] not registered or class does not exist.");
        }

        if (!is_subclass_of($class, LiveComponent::class)) {
            throw new LiveDomException("Component class [{$class}] must extend " . LiveComponent::class);
        }

        /** @var LiveComponent $instance */
        $instance = new $class($id);

        if (!empty($props) && method_exists($instance, 'mount')) {
            $instance->mount(...$props);
        }


        return $instance;
    }

    /**
     * Render a component by alias or instance with LiveDOM attributes.
     */
    public function render(string|LiveComponent $component, array $props = []): string
    {
        if (is_string($component)) {
            $component = $this->resolve($component, $props);
        }

        return $component->renderWithLiveDom();
    }

    /**
     * Execute a LiveDOM action request and generate diff patches and updated snapshot.
     */
    public function handleRequest(array $data): LiveDomResponse
    {
        try {
            $id = (string)($data['id'] ?? '');
            $action = (string)($data['action'] ?? '');
            $params = (array)($data['params'] ?? []);
            $rawSnapshot = $data['snapshot'] ?? null;
            $componentName = (string)($data['component'] ?? '');

            if ($rawSnapshot === null && $componentName === '') {
                throw new LiveDomException("Missing required snapshot or component identifier in LiveDOM request.");
            }

            // Hydrate component from snapshot
            /** @var LiveComponent $component */
            if (is_string($rawSnapshot) && $rawSnapshot !== '') {
                $payload = LiveDomPayload::fromEncoded($rawSnapshot);
                $componentClass = $payload->getComponent();
                if (!class_exists($componentClass)) {
                    $componentClass = $this->registry[strtolower($componentClass)] ?? $componentClass;
                }
                if (!class_exists($componentClass)) {
                    throw new ComponentNotFoundException("Component class [{$componentClass}] cannot be found.");
                }
                $component = $componentClass::fromSnapshot($payload);
            } elseif (is_array($rawSnapshot) && !empty($rawSnapshot)) {
                $payload = LiveDomPayload::fromArray($rawSnapshot);
                $componentClass = $payload->getComponent();
                if (!class_exists($componentClass)) {
                    $componentClass = $this->registry[strtolower($componentClass)] ?? $componentClass;
                }
                if (!class_exists($componentClass)) {
                    throw new ComponentNotFoundException("Component class [{$componentClass}] cannot be found.");
                }
                $component = $componentClass::fromSnapshot($payload);
            } else {
                // Initial component creation without existing snapshot
                $component = $this->resolve($componentName, [], $id ?: null);
            }

            // Capture pre-action HTML output for diff computation
            $oldHtml = $component->renderWithLiveDom();

            // Execute the requested action
            $actionResult = null;
            if ($action !== '' && $action !== '$refresh') {
                if ($action === '$set' || $action === 'set') {
                    $prop = (string)($params[0] ?? '');
                    $val = $params[1] ?? null;
                    $component->set($prop, $val);
                } else {
                    $actionResult = $component->callAction($action, $params);
                }
            }

            // Generate post-action HTML and new signed snapshot
            $newHtml = $component->renderWithLiveDom();
            $newSnapshot = $component->createSnapshot();

            // Compute DOM patches
            $diff = $this->morphEngine->diff($oldHtml, $newHtml, $component->getId());

            return new LiveDomResponse(
                success: true,
                id: $component->getId(),
                html: $newHtml,
                snapshot: $newSnapshot->toArray(),
                patches: $diff['patches'],
                events: $component->getDispatchedEvents(),
                redirect: $component->getRedirectUrl(),
                errors: $component->getErrors(),
                result: $actionResult
            );
        } catch (InvalidSignatureException $e) {
            return new LiveDomResponse(
                success: false,
                id: $data['id'] ?? '',
                html: '',
                snapshot: [],
                patches: [],
                events: [],
                redirect: null,
                errors: ['security' => $e->getMessage()]
            );
        } catch (ActionNotAllowedException|ComponentNotFoundException|LiveDomException $e) {
            return new LiveDomResponse(
                success: false,
                id: $data['id'] ?? '',
                html: '',
                snapshot: [],
                patches: [],
                events: [],
                redirect: null,
                errors: ['action' => $e->getMessage()]
            );
        } catch (Throwable $e) {
            return new LiveDomResponse(
                success: false,
                id: $data['id'] ?? '',
                html: '',
                snapshot: [],
                patches: [],
                events: [],
                redirect: null,
                errors: ['internal' => $e->getMessage()]
            );
        }
    }

    /**
     * Handle incoming HTTP request from client runtime.
     */
    public function handleHttpRequest(Request $request): Response
    {
        $body = $request->json() ?: $request->post() ?: $request->all() ?: [];
        $response = $this->handleRequest(is_array($body) ? $body : []);

        $statusCode = $response->isSuccess() ? 200 : ($response->getErrors()['security'] ?? null ? 403 : 400);

        return $response->toHttpResponse($statusCode);
    }

    /**
     * Output client runtime script tag.
     */
    public function script(): string
    {
        return LiveDomClient::renderScriptTag();
    }

    /**
     * Output client runtime style tag.
     */
    public function styles(): string
    {
        return LiveDomClient::renderStyleTag();
    }

    /**
     * Output combined script & styles tags.
     */
    public function assets(): string
    {
        return $this->styles() . "\n" . $this->script();
    }
}
