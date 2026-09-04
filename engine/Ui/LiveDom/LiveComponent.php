<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

use JsonSerializable;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Oshim\Ui\LiveDom\Exceptions\ActionNotAllowedException;
use Oshim\Ui\LiveDom\Exceptions\InvalidSignatureException;
use Oshim\Ui\Form\FormValidator;

/**
 * Sovereign Base Class for LiveDOM Reactive Components.
 * Pure PHP 8.3+ with zero external dependencies.
 */
abstract class LiveComponent implements JsonSerializable
{
    protected string $id;
    protected array $errors = [];
    protected array $dispatchedEvents = [];
    protected ?string $redirectUrl = null;
    protected static ?string $signingSecret = null;

    /**
     * Internal methods prohibited from being invoked via client actions.
     */
    protected const RESTRICTED_ACTIONS = [
        'mount', 'boot', 'render', 'rendering', 'rendered', 'updating', 'updated',
        'hydrate', 'dehydrate', 'createsnapshot', 'fromsnapshot', 'callaction',
        'renderwithlivedom', 'wrapinlivedomroot', 'dispatch', 'redirect',
        'validate', 'adderror', 'haserror', 'geterror', 'geterrors', 'reseterrorbag',
        'set', 'sync', 'getid', 'getstate', 'getdispatchedevents', 'getredirecturl',
        'getcomponentalias', 'getsigningsecret', 'setsigningsecret',
        '__construct', '__destruct', '__get', '__set', '__call', '__callstatic',
        '__isset', '__unset', '__sleep', '__wakeup', '__serialize', '__unserialize',
        '__tostring', '__invoke', '__set_state', '__clone', '__debuginfo', 'jsonserialize',
    ];

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? ('livedom_' . bin2hex(random_bytes(6)));
        $this->boot();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public static function setSigningSecret(string $secret): void
    {
        self::$signingSecret = $secret;
    }

    public static function getSigningSecret(): string
    {
        return self::$signingSecret ?? LiveDomPayload::getDefaultSecret();
    }

    /**
     * Component Lifecycle: Hook executed whenever component is instantiated (mounted or hydrated).
     */
    public function boot(): void
    {
    }


    /**
     * Component Lifecycle: Hook called before a property is updated.
     */
    public function updating(string $name, mixed $value): void
    {
    }

    /**
     * Component Lifecycle: Hook called after a property is updated.
     */
    public function updated(string $name, mixed $value): void
    {
    }

    /**
     * Component Lifecycle: Hook called right before render().
     */
    public function rendering(): void
    {
    }

    /**
     * Component Lifecycle: Hook called right after render().
     */
    public function rendered(string $html): string
    {
        return $html;
    }

    /**
     * Render the component HTML output.
     */
    abstract public function render(): string;

    /**
     * Set a reactive state property, firing updating and updated hooks.
     */
    public function set(string $name, mixed $value): static
    {
        $reflection = new ReflectionClass($this);
        if ($reflection->hasProperty($name)) {
            $prop = $reflection->getProperty($name);
            if ($prop->isPublic() && !$prop->isStatic()) {
                $this->updating($name, $value);
                $prop->setValue($this, $value);
                $this->updated($name, $value);
            }
        }
        return $this;
    }

    /**
     * Alias for set().
     */
    public function sync(string $name, mixed $value): static
    {
        return $this->set($name, $value);
    }

    /**
     * Extract all public non-static properties as component state.
     */
    public function getState(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $state = [];

        foreach ($properties as $property) {
            if (!$property->isStatic() && $property->isInitialized($this)) {
                $state[$property->getName()] = $property->getValue($this);
            }
        }

        return $state;
    }

    /**
     * Hydrate public state properties from array, firing lifecycle hooks.
     */
    public function hydrate(array $state): void
    {
        $reflection = new ReflectionClass($this);

        foreach ($state as $key => $value) {
            if ($reflection->hasProperty($key)) {
                $prop = $reflection->getProperty($key);
                if ($prop->isPublic() && !$prop->isStatic()) {
                    $this->updating($key, $value);
                    $prop->setValue($this, $value);
                    $this->updated($key, $value);
                }
            }
        }
    }

    /**
     * Invoke an action method on the component with strict security verification.
     */
    public function callAction(string $method, array $params = []): mixed
    {
        $normalized = strtolower($method);

        // Handle internal $set special action from live:model
        if ($method === '$set' || $method === 'set') {
            $propName = (string)($params[0] ?? ($params['property'] ?? ''));
            $propVal = $params[1] ?? ($params['value'] ?? null);
            $this->set($propName, $propVal);
            return null;
        }

        if (in_array($normalized, self::RESTRICTED_ACTIONS, true)) {
            throw new ActionNotAllowedException("Action [{$method}] is restricted on component [" . static::class . "].");
        }

        if (!method_exists($this, $method)) {
            throw new ActionNotAllowedException("Action [{$method}] does not exist on component [" . static::class . "].");
        }

        try {
            $refMethod = new ReflectionMethod($this, $method);
            if (!$refMethod->isPublic() || $refMethod->isStatic() || $refMethod->getDeclaringClass()->getName() === self::class) {
                throw new ActionNotAllowedException("Action [{$method}] is not callable on component [" . static::class . "].");
            }
        } catch (\ReflectionException $e) {
            throw new ActionNotAllowedException("Cannot inspect method [{$method}]: " . $e->getMessage());
        }

        // Support associative param mapping if parameters match method arguments
        $args = $this->resolveActionArguments($refMethod, $params);

        return $this->$method(...$args);
    }

    /**
     * Match incoming params to method signature arguments.
     */
    protected function resolveActionArguments(ReflectionMethod $method, array $params): array
    {
        $parameters = $method->getParameters();
        if (empty($parameters)) {
            return [];
        }

        // If params is a sequential list matching or exceeding count
        if (array_is_list($params)) {
            return $params;
        }

        $resolved = [];
        foreach ($parameters as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $params)) {
                $resolved[] = $params[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $resolved[] = $param->getDefaultValue();
            } else {
                $resolved[] = null;
            }
        }

        return $resolved;
    }

    /**
     * Create signed LiveDOM snapshot payload.
     */
    public function createSnapshot(?string $secret = null): LiveDomPayload
    {
        return LiveDomPayload::create(
            id: $this->id,
            component: static::class,
            state: $this->getState(),
            memo: [
                'alias' => static::getComponentAlias(),
            ],
            secret: $secret ?? static::getSigningSecret()
        );
    }

    /**
     * Restore and hydrate component instance from signed snapshot.
     */
    public static function fromSnapshot(array|LiveDomPayload $snapshot, ?string $secret = null): static
    {
        $payload = $snapshot instanceof LiveDomPayload
            ? $snapshot
            : LiveDomPayload::fromArray($snapshot, $secret ?? static::getSigningSecret(), true);

        $payload->verify($secret ?? static::getSigningSecret());

        $instance = new static($payload->getId());
        $instance->hydrate($payload->getState());

        return $instance;
    }

    /**
     * Dispatches a browser custom event to be fired on client `window`.
     */
    public function dispatch(string $event, array $detail = []): static
    {
        $this->dispatchedEvents[] = [
            'name'   => $event,
            'detail' => $detail,
        ];
        return $this;
    }

    public function getDispatchedEvents(): array
    {
        return $this->dispatchedEvents;
    }

    /**
     * Direct the client browser to redirect to a URL.
     */
    public function redirect(string $url): static
    {
        $this->redirectUrl = $url;
        return $this;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    /**
     * Form Validation Helper.
     */
    public function validate(array $rules, array $messages = []): bool
    {
        $state = $this->getState();
        $this->errors = [];

        if (class_exists(FormValidator::class)) {
            $validator = FormValidator::make($state, $rules);
            if ($validator->fails()) {
                $this->errors = $validator->getErrors();
                return false;
            }
            return true;
        }

        // Built-in rule validator fallback
        foreach ($rules as $field => $ruleString) {
            $val = $state[$field] ?? null;
            $ruleList = is_array($ruleString) ? $ruleString : explode('|', (string)$ruleString);

            foreach ($ruleList as $rule) {
                $parts = explode(':', $rule, 2);
                $ruleName = strtolower($parts[0]);
                $param = $parts[1] ?? null;

                if ($ruleName === 'required' && ($val === null || $val === '' || (is_array($val) && empty($val)))) {
                    $this->addError($field, $messages["{$field}.required"] ?? "The {$field} field is required.");
                    break;
                }

                if ($ruleName === 'min' && $param !== null) {
                    $min = (int)$param;
                    if (is_string($val) && mb_strlen($val) < $min) {
                        $this->addError($field, $messages["{$field}.min"] ?? "The {$field} must be at least {$min} characters.");
                        break;
                    }
                    if (is_numeric($val) && $val < $min) {
                        $this->addError($field, $messages["{$field}.min"] ?? "The {$field} must be at least {$min}.");
                        break;
                    }
                }

                if ($ruleName === 'max' && $param !== null) {
                    $max = (int)$param;
                    if (is_string($val) && mb_strlen($val) > $max) {
                        $this->addError($field, $messages["{$field}.max"] ?? "The {$field} must not exceed {$max} characters.");
                        break;
                    }
                }

                if ($ruleName === 'email' && $val !== null && $val !== '') {
                    if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                        $this->addError($field, $messages["{$field}.email"] ?? "The {$field} must be a valid email address.");
                        break;
                    }
                }

                if ($ruleName === 'numeric' && $val !== null && $val !== '') {
                    if (!is_numeric($val)) {
                        $this->addError($field, $messages["{$field}.numeric"] ?? "The {$field} must be a number.");
                        break;
                    }
                }
            }
        }

        return empty($this->errors);
    }

    public function addError(string $key, string $message): static
    {
        $this->errors[$key] = $message;
        return $this;
    }

    public function hasError(string $key): bool
    {
        return isset($this->errors[$key]);
    }

    public function getError(string $key): ?string
    {
        $val = $this->errors[$key] ?? null;
        if (is_array($val)) {
            return !empty($val) ? (string)reset($val) : null;
        }
        return $val;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function resetErrorBag(?string $key = null): static
    {
        if ($key === null) {
            $this->errors = [];
        } else {
            unset($this->errors[$key]);
        }
        return $this;
    }

    /**
     * Render the component wrapped with all LiveDOM hydration & morph attributes.
     */
    public function renderWithLiveDom(): string
    {
        $this->rendering();
        $html = $this->render();
        $wrapped = $this->wrapInLiveDomRoot($html);
        return $this->rendered($wrapped);
    }

    /**
     * Injects data-live-* attributes onto the root HTML element.
     */
    protected function wrapInLiveDomRoot(string $html): string
    {
        $html = trim($html);
        $snapshot = $this->createSnapshot();
        $encodedSnapshot = $snapshot->encode();
        $checksum = $snapshot->getChecksum();
        $alias = static::getComponentAlias();

        $liveAttrs = sprintf(
            'data-live-id="%s" data-live-component="%s" data-live-snapshot="%s" data-live-checksum="%s"',
            htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($encodedSnapshot, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($checksum, ENT_QUOTES, 'UTF-8')
        );

        // Check if root element tag exists
        if (preg_match('/^<([a-zA-Z0-9\-]+)([^>]*)>(.*)<\/\1>\s*$/s', $html, $matches)) {
            $tag = $matches[1];
            $existingAttrs = $matches[2];
            $inner = $matches[3];

            // Remove existing data-live-* attributes to prevent duplication
            $cleanedAttrs = preg_replace('/\s*data-live-(id|component|snapshot|checksum)="[^"]*"/i', '', $existingAttrs);

            return "<{$tag} {$liveAttrs}{$cleanedAttrs}>{$inner}</{$tag}>";
        }

        // Otherwise wrap in div
        return "<div {$liveAttrs}>{$html}</div>";
    }

    /**
     * Convert class name into kebab-case component alias.
     */
    public static function getComponentAlias(): string
    {
        $short = (new ReflectionClass(static::class))->getShortName();
        $short = preg_replace('/Component$/', '', $short) ?? $short;
        return strtolower((string)preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
    }

    public function jsonSerialize(): array
    {
        return [
            'id'        => $this->id,
            'component' => static::getComponentAlias(),
            'state'     => $this->getState(),
            'html'      => $this->renderWithLiveDom(),
            'snapshot'  => $this->createSnapshot()->toArray(),
        ];
    }
}
