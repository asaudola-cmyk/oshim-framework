<?php
declare(strict_types=1);

namespace Oshim\Http\Session;

class Session
{
    protected string $id = '';
    protected string $name = 'oshim_session';
    protected array $attributes = [];
    /** @var array<string, mixed> */
    protected array $flashOld = [];
    /** @var array<string, mixed> */
    protected array $flashNew = [];
    protected bool $started = false;

    public function __construct(
        protected SessionStoreInterface $store,
        protected string $appKey,
        protected int $lifetime = 7200
    ) {}

    public function start(?string $id = null): bool
    {
        if ($this->started) {
            return true;
        }

        $this->id = $this->isValidId($id) ? (string)$id : $this->generateSessionId();
        $data = $this->store->read($this->id);

        $this->attributes = $data['attributes'] ?? $data;
        $this->flashOld = $data['_flash_new'] ?? [];
        $this->flashNew = [];

        // Ensure CSRF token exists
        if (!isset($this->attributes['_token'])) {
            $this->regenerateToken();
        }

        $this->started = true;
        return true;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        if ($this->isValidId($id)) {
            $this->id = $id;
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function regenerate(bool $destroyOld = true): string
    {
        if ($destroyOld && $this->id !== '') {
            $this->store->destroy($this->id);
        }

        $this->id = $this->generateSessionId();
        return $this->id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
        if (array_key_exists($key, $this->flashOld)) {
            return $this->flashOld[$key];
        }
        if (array_key_exists($key, $this->flashNew)) {
            return $this->flashNew[$key];
        }

        return $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function put(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes)
            || array_key_exists($key, $this->flashOld)
            || array_key_exists($key, $this->flashNew);
    }

    public function remove(string $key): mixed
    {
        $val = $this->get($key);
        unset($this->attributes[$key], $this->flashOld[$key], $this->flashNew[$key]);
        return $val;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $val = $this->get($key, $default);
        $this->remove($key);
        return $val;
    }

    public function flash(string $key, mixed $value): void
    {
        $this->flashNew[$key] = $value;
    }

    public function now(string $key, mixed $value): void
    {
        $this->flashOld[$key] = $value;
    }

    public function reflash(): void
    {
        $this->flashNew = array_merge($this->flashNew, $this->flashOld);
        $this->flashOld = [];
    }

    public function keep(array|string $keys): void
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        foreach ($keys as $key) {
            if (isset($this->flashOld[$key])) {
                $this->flashNew[$key] = $this->flashOld[$key];
                unset($this->flashOld[$key]);
            }
        }
    }

    public function all(): array
    {
        return array_merge($this->attributes, $this->flashOld, $this->flashNew);
    }

    public function clear(): void
    {
        $token = $this->attributes['_token'] ?? null;
        $this->attributes = [];
        $this->flashOld = [];
        $this->flashNew = [];
        if ($token !== null) {
            $this->attributes['_token'] = $token;
        }
    }

    public function token(): string
    {
        if (!isset($this->attributes['_token'])) {
            $this->regenerateToken();
        }
        return (string)$this->attributes['_token'];
    }

    public function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->attributes['_token'] = $token;
        return $token;
    }

    public function save(): void
    {
        if (!$this->started) {
            return;
        }

        $payload = [
            'attributes' => $this->attributes,
            '_flash_new' => $this->flashNew,
        ];

        $this->store->write($this->id, $payload, $this->lifetime);
    }

    public function destroy(): bool
    {
        $this->clear();
        $this->started = false;
        return $this->store->destroy($this->id);
    }

    protected function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    protected function isValidId(?string $id): bool
    {
        return is_string($id) && ctype_alnum($id) && strlen($id) === 64;
    }
}
