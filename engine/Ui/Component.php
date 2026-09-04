<?php
declare(strict_types=1);

namespace Oshim\Ui;

use Oshim\Security\Sanitizer;
use Oshim\Ui\Exceptions\ComponentActionException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Abstract Base UI Component for OSHIM Reactive UI Engine.
 */
abstract class Component
{
    protected string $id;
    protected array $props = [];
    protected array $state = [];
    protected array $slots = [];
    protected array $emittedEvents = [];
    protected static ?string $secretKey = null;

    public function __construct(array $props = [], ?string $id = null)
    {
        $this->id = $id ?? 'oshim_' . bin2hex(random_bytes(6));
        $this->props = $props;
        $this->mount($props);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProps(): array
    {
        return $this->props;
    }

    public function getState(): array
    {
        return $this->state;
    }

    /**
     * Initial component mounting lifecycle hook.
     */
    public function mount(array $props): void
    {
    }

    /**
     * Abstract render method returning HTML/SVG.
     */
    abstract public function render(): string;

    /**
     * Hydrates reactive state from incoming verified client state.
     */
    public function hydrate(array $state): void
    {
        $this->state = array_merge($this->state, $state);
    }

    /**
     * Dehydrates state for serializing into client DOM payload or checksum testing.
     */
    public function dehydrate(): array
    {
        return [
            'id'       => $this->id,
            'props'    => $this->props,
            'state'    => $this->state,
            'checksum' => $this->generateSignature($this->state),
        ];
    }

    /**
     * Dispatches an action method on the component.
     */
    public function dispatch(string $action, array $payload = []): mixed
    {
        $restricted = [
            'mount', 'render', 'hydrate', 'dehydrate', 'emit', 'dispatch',
            'renderroot', 'generatesignature', 'verifysignature', 'verifychecksum',
            'escape', 'slot', 'hasslot', 'setslot', 'withslots', 'getid', 'setid',
            'getprops', 'getstate', 'setstate', 'getemittedevents', 'handleevent',
            'getsecretkey', 'setsecretkey', 'getcomponentalias',
            '__construct', '__destruct', '__get', '__set', '__call', '__callstatic',
            '__isset', '__unset', '__sleep', '__wakeup', '__serialize', '__unserialize',
            '__tostring', '__invoke', '__set_state', '__clone', '__debuginfo',
        ];

        if (in_array(strtolower($action), $restricted, true)) {
            throw new ComponentActionException("Action [{$action}] is not allowed on component [" . static::class . "].");
        }

        try {
            $ref = new ReflectionMethod($this, $action);
            if (
                !$ref->isPublic() ||
                $ref->isStatic() ||
                $ref->isConstructor() ||
                $ref->isDestructor() ||
                $ref->getDeclaringClass()->getName() === self::class
            ) {
                throw new ComponentActionException("Action [{$action}] is not allowed on component [" . static::class . "].");
            }
        } catch (\ReflectionException $e) {
            throw new ComponentActionException("Action [{$action}] does not exist on component [" . static::class . "].");
        }

        $numParams = $ref->getNumberOfParameters();
        if ($numParams === 0) {
            return $this->$action();
        }

        return $this->$action($payload);
    }

    /**
     * Handle event and return dehydrated state (lifecycle compatibility helper).
     */
    public function handleEvent(string $action, array $payload = []): array
    {
        $this->dispatch($action, $payload);
        return $this->dehydrate();
    }

    /**
     * Emits a custom reactive event to client or parent components.
     */
    public function emit(string $event, mixed $payload = null): static
    {
        $this->emittedEvents[] = [
            'event'   => $event,
            'payload' => $payload,
            'source'  => $this->id,
        ];
        return $this;
    }

    public function getEmittedEvents(): array
    {
        return $this->emittedEvents;
    }

    // --- Slot Management ---

    public function slot(string $name = 'default', string $default = ''): string
    {
        return $this->slots[$name] ?? $default;
    }

    public function setSlot(string $name, string $content): static
    {
        $this->slots[$name] = $content;
        return $this;
    }

    public function hasSlot(string $name): bool
    {
        return isset($this->slots[$name]) && trim($this->slots[$name]) !== '';
    }

    public function withSlots(array $slots): static
    {
        foreach ($slots as $name => $content) {
            $this->setSlot($name, (string)$content);
        }
        return $this;
    }

    // --- State Security & HMAC-SHA256 Signing ---

    public static function setSecretKey(string $key): void
    {
        self::$secretKey = $key;
    }

    public static function getSecretKey(): string
    {
        if (self::$secretKey !== null) {
            return self::$secretKey;
        }

        $key = $_ENV['APP_KEY'] ?? $_ENV['OSHIM_KEY'] ?? 'oshim_default_ui_secret_change_in_prod';
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }

    public function generateSignature(array $state): string
    {
        $payload = $this->id . ':' . json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash_hmac('sha256', $payload, self::getSecretKey());
    }

    public function verifySignature(array $state, string $sig): bool
    {
        return hash_equals($this->generateSignature($state), $sig);
    }

    public function verifyChecksum(string $sig): bool
    {
        return $this->verifySignature($this->state, $sig);
    }

    /**
     * Decorates the root container HTML element with required OSHIM reactive attributes.
     */
    protected function renderRoot(string $tag, string $innerHtml, array $attributes = []): string
    {
        $state = $this->state;
        $encodedState = base64_encode(json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $sig = $this->generateSignature($state);

        $attrs = [
            'data-oshim-id'        => $this->id,
            'data-oshim-component' => static::getComponentAlias(),
            'data-oshim-state'     => $encodedState,
            'data-oshim-sig'       => $sig,
        ];

        // Merge custom attributes
        foreach ($attributes as $k => $v) {
            if ($k === 'class' && isset($attrs['class'])) {
                $attrs['class'] .= ' ' . $v;
            } else {
                $attrs[$k] = $v;
            }
        }

        $attrString = '';
        foreach ($attrs as $k => $v) {
            $attrString .= ' ' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '"';
        }

        return "<{$tag}{$attrString}>{$innerHtml}</{$tag}>";
    }

    public static function getComponentAlias(): string
    {
        $shortName = (new ReflectionClass(static::class))->getShortName();
        return strtolower((string)preg_replace('/(?<!^)[A-Z]/', '-$0', $shortName));
    }

    protected function escape(mixed $value): string
    {
        if (class_exists(Sanitizer::class)) {
            return Sanitizer::escapeHtml((string)$value);
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
