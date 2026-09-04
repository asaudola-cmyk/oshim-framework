<?php
declare(strict_types=1);

namespace Oshim\Ui\Animation;

/**
 * Motion Factory Facade.
 *
 * Provides a clean Framer Motion equivalent syntax for creating declarative
 * server-driven animated elements in pure PHP.
 *
 * Example:
 * Motion::div()
 *     ->initial(['opacity' => 0, 'y' => 20])
 *     ->animate(['opacity' => 1, 'y' => 0])
 *     ->spring(Spring::bouncy())
 *     ->render();
 */
class Motion
{
    public static function make(string $tag = 'div', ?string $id = null): MotionElement
    {
        return new MotionElement($tag, $id);
    }

    public static function element(string $tag, ?string $id = null): MotionElement
    {
        return new MotionElement($tag, $id);
    }

    public static function div(?string $id = null): MotionElement
    {
        return new MotionElement('div', $id);
    }

    public static function button(?string $id = null): MotionElement
    {
        return new MotionElement('button', $id);
    }

    public static function span(?string $id = null): MotionElement
    {
        return new MotionElement('span', $id);
    }

    public static function p(?string $id = null): MotionElement
    {
        return new MotionElement('p', $id);
    }

    public static function section(?string $id = null): MotionElement
    {
        return new MotionElement('section', $id);
    }

    public static function article(?string $id = null): MotionElement
    {
        return new MotionElement('article', $id);
    }

    public static function header(?string $id = null): MotionElement
    {
        return new MotionElement('header', $id);
    }

    public static function footer(?string $id = null): MotionElement
    {
        return new MotionElement('footer', $id);
    }

    public static function nav(?string $id = null): MotionElement
    {
        return new MotionElement('nav', $id);
    }

    public static function aside(?string $id = null): MotionElement
    {
        return new MotionElement('aside', $id);
    }

    public static function main(?string $id = null): MotionElement
    {
        return new MotionElement('main', $id);
    }

    public static function h1(?string $id = null): MotionElement
    {
        return new MotionElement('h1', $id);
    }

    public static function h2(?string $id = null): MotionElement
    {
        return new MotionElement('h2', $id);
    }

    public static function h3(?string $id = null): MotionElement
    {
        return new MotionElement('h3', $id);
    }

    public static function card(?string $id = null): MotionElement
    {
        return (new MotionElement('div', $id))->class('oshim-motion-card');
    }

    /**
     * Fluent Spring helper.
     */
    public static function spring(float $stiffness = 100.0, float $damping = 10.0, float $mass = 1.0): Spring
    {
        return new Spring($stiffness, $damping, $mass);
    }

    /**
     * Fluent Stagger helper.
     */
    public static function stagger(float $interval = 0.08, float $delay = 0.0): Stagger
    {
        return new Stagger($interval, $delay);
    }

    /**
     * Fluent ScrollTrigger helper.
     */
    public static function scroll(float $threshold = 0.1, string $rootMargin = '0px', bool $once = true): ScrollTrigger
    {
        return new ScrollTrigger($threshold, $rootMargin, $once);
    }

    /**
     * Fluent Variant helper.
     */
    public static function variant(string $name, array $properties = []): AnimationVariant
    {
        return new AnimationVariant($name, $properties);
    }
}
