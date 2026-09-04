<?php
declare(strict_types=1);

namespace Oshim\Auth;

use Oshim\Auth\Guards\GuardInterface;

class Auth
{
    private static ?AuthManager $manager = null;

    public static function getManager(): AuthManager
    {
        if (self::$manager === null) {
            self::$manager = new AuthManager();
        }
        return self::$manager;
    }

    public static function setManager(AuthManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function guard(?string $name = null): GuardInterface
    {
        return self::getManager()->guard($name);
    }

    public static function check(): bool
    {
        return self::getManager()->check();
    }

    public static function guest(): bool
    {
        return self::getManager()->guest();
    }

    public static function user(): ?object
    {
        return self::getManager()->user();
    }

    public static function id(): int|string|null
    {
        return self::getManager()->id();
    }

    public static function login(object $user, bool $remember = false): void
    {
        self::getManager()->login($user, $remember);
    }

    public static function logout(): void
    {
        self::getManager()->logout();
    }
}
