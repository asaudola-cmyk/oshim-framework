<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

use Oshim\Http\Request;
use Oshim\Http\Response;

/**
 * Sovereign Static Facade for Oshim LiveDOM.
 */
class LiveDom
{
    protected static ?LiveDomManager $instance = null;

    public static function getManager(): LiveDomManager
    {
        if (self::$instance === null) {
            self::$instance = new LiveDomManager();
        }
        return self::$instance;
    }

    public static function setManager(LiveDomManager $manager): void
    {
        self::$instance = $manager;
    }

    public static function register(string $alias, string $class): void
    {
        self::getManager()->register($alias, $class);
    }

    public static function registerMany(array $map): void
    {
        self::getManager()->registerMany($map);
    }

    public static function render(string|LiveComponent $component, array $props = []): string
    {
        return self::getManager()->render($component, $props);
    }

    public static function handle(array $payload): LiveDomResponse
    {
        return self::getManager()->handleRequest($payload);
    }

    public static function handleHttp(Request $request): Response
    {
        return self::getManager()->handleHttpRequest($request);
    }

    public static function script(): string
    {
        return self::getManager()->script();
    }

    public static function styles(): string
    {
        return self::getManager()->styles();
    }

    public static function assets(): string
    {
        return self::getManager()->assets();
    }

    public static function setSecret(string $secret): void
    {
        LiveDomPayload::setDefaultSecret($secret);
        LiveComponent::setSigningSecret($secret);
    }

    public static function getSecret(): string
    {
        return LiveDomPayload::getDefaultSecret();
    }
}
