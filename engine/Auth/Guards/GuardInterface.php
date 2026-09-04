<?php
declare(strict_types=1);

namespace Oshim\Auth\Guards;

interface GuardInterface
{
    public function check(): bool;
    public function guest(): bool;
    public function user(): ?object;
    public function id(): int|string|null;
    public function login(object $user, bool $remember = false): void;
    public function logout(): void;
    public function validate(array $credentials = []): bool;
}
