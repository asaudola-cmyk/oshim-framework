<?php
declare(strict_types=1);

namespace Oshim\Auth\Guards;

use Oshim\Http\Session\Session;
use Oshim\Auth\Password\PasswordHasher;

class SessionGuard implements GuardInterface
{
    private ?object $user = null;
    private ?Session $session;
    /** @var callable|null */
    private $userProvider = null;

    public function __construct(?Session $session = null, ?callable $userProvider = null)
    {
        $this->session = $session;
        $this->userProvider = $userProvider;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?object
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $id = $this->session?->get('auth_user_id');
        if ($id !== null && $this->userProvider !== null) {
            $this->user = ($this->userProvider)($id);
        }

        return $this->user;
    }

    public function id(): int|string|null
    {
        return $this->user()?->id ?? $this->session?->get('auth_user_id');
    }

    public function login(object $user, bool $remember = false): void
    {
        $this->user = $user;
        $id = $user->id ?? null;
        if ($id !== null) {
            $this->session?->set('auth_user_id', $id);
        }
    }

    public function logout(): void
    {
        $this->user = null;
        $this->session?->remove('auth_user_id');
    }

    public function validate(array $credentials = []): bool
    {
        $password = $credentials['password'] ?? '';
        $user = $credentials['user'] ?? null;

        if ($user && isset($user->password)) {
            return PasswordHasher::check($password, (string)$user->password);
        }

        return false;
    }
}
