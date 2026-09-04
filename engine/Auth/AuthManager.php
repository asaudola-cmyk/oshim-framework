<?php
declare(strict_types=1);

namespace Oshim\Auth;

use Oshim\Auth\Guards\GuardInterface;
use Oshim\Auth\Guards\SessionGuard;
use Oshim\Auth\Guards\TokenGuard;
use Oshim\Http\Session\Session;
use Oshim\Http\Request;
use InvalidArgumentException;

class AuthManager
{
    /** @var array<string, GuardInterface> */
    private array $guards = [];
    private string $defaultGuard = 'web';
    private ?Session $session = null;
    private ?Request $request = null;
    /** @var callable|null */
    private $userProvider = null;

    public function __construct(?Session $session = null, ?Request $request = null)
    {
        $this->session = $session;
        $this->request = $request;
    }

    public function setUserProvider(callable $provider): void
    {
        $this->userProvider = $provider;
    }

    public function guard(?string $name = null): GuardInterface
    {
        $name = $name ?? $this->defaultGuard;

        if (isset($this->guards[$name])) {
            return $this->guards[$name];
        }

        return $this->guards[$name] = match ($name) {
            'web', 'session' => new SessionGuard($this->session, $this->userProvider),
            'api', 'token' => new TokenGuard($this->request, $this->userProvider),
            default => throw new InvalidArgumentException("Unsupported auth guard: {$name}"),
        };
    }

    public function check(): bool
    {
        return $this->guard()->check();
    }

    public function guest(): bool
    {
        return !$this->guard()->guest();
    }

    public function user(): ?object
    {
        return $this->guard()->user();
    }

    public function id(): int|string|null
    {
        return $this->guard()->id();
    }

    public function login(object $user, bool $remember = false): void
    {
        $this->guard()->login($user, $remember);
    }

    public function logout(): void
    {
        $this->guard()->logout();
    }
}
