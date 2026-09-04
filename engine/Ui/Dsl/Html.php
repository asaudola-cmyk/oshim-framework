<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

class Html
{
    public static function div(array $attributes = [], mixed ...$children): Div
    {
        $el = new Div();
        foreach ($attributes as $k => $v) {
            $el->attr($k, $v);
        }
        foreach ($children as $child) {
            if ($child instanceof Element) {
                $el->child($child);
            } else {
                $el->text((string)$child);
            }
        }
        return $el;
    }

    public static function h1(array $attributes = [], string $text = ''): Heading
    {
        $el = new Heading(1, $text);
        foreach ($attributes as $k => $v) {
            $el->attr($k, $v);
        }
        return $el;
    }

    public static function h2(array $attributes = [], string $text = ''): Heading
    {
        $el = new Heading(2, $text);
        foreach ($attributes as $k => $v) {
            $el->attr($k, $v);
        }
        return $el;
    }

    public static function p(array $attributes = [], string $text = ''): Paragraph
    {
        $el = new Paragraph($text);
        foreach ($attributes as $k => $v) {
            $el->attr($k, $v);
        }
        return $el;
    }

    public static function button(array $attributes = [], string $text = ''): Button
    {
        $el = new Button($text);
        foreach ($attributes as $k => $v) {
            $el->attr($k, $v);
        }
        return $el;
    }
}
