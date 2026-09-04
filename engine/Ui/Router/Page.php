<?php
declare(strict_types=1);

namespace Oshim\Ui\Router;

use Closure;

/**
 * Page Definition for Next.js-style App Router.
 */
class Page
{
    private string $path;
    private Closure $renderFn;
    private ?Layout $layout;
    private string $title;

    public function __construct(string $path, callable $renderFn, ?Layout $layout = null, string $title = 'OSHIM App')
    {
        $this->path = $path;
        $this->renderFn = $renderFn(...);
        $this->layout = $layout;
        $this->title = $title;
    }

    public static function make(string $path, callable $renderFn, ?Layout $layout = null, string $title = 'OSHIM App'): self
    {
        return new self($path, $renderFn, $layout, $title);
    }

    public function getPath(): string { return $this->path; }
    public function getLayout(): ?Layout { return $this->layout; }
    public function getTitle(): string { return $this->title; }

    public function renderInner(array $params = []): string
    {
        return (string)($this->renderFn)($params);
    }

    public function renderFull(array $params = []): string
    {
        $inner = $this->renderInner($params);
        if ($this->layout !== null) {
            return $this->layout->render($inner);
        }
        return $inner;
    }
}
