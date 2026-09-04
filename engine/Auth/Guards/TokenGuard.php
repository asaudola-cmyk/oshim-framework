<?php
declare(strict_types=1);

namespace Oshim\Auth\Guards;

use Oshim\Http\Request;

class TokenGuard implements GuardInterface
{
    private ?object $user = null;
    private ?Request $request;
    /** @var callable|null */
    private $userProvider = null;

    public function __construct(?Request $request = null, ?callable $userProvider = null)
    {
        $this->request = $request;
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

        $token = $this->extractToken();
        if ($token !== null && $this->userProvider !== null) {
            $this->user = ($this->userProvider)($token);
        }

        return $this->user;
    }

    public function id(): int|string|null
    {
        return $this->user()?->id ?? null;
    }

    public function login(object $user, bool $remember = false): void
    {
        $this->user = $user;
    }

    public function logout(): void
    {
        $this->user = null;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    private function extractToken(): ?string
    {
        if ($this->request === null) {
            return null;
        }

        $bearer = $this->request->bearerToken();
        if ($bearer !== null) {
            return $bearer;
        }

        $authHeader = $this->request->header('authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return $this->request->query('api_token');
    }
}
