<?php
declare(strict_types=1);

namespace Oshim\Ui\Router;

use Closure;

/**
 * Base Layout Component with nesting and slot preservation.
 */
class Layout
{
    private string $id;
    private Closure $renderFn;
    private ?self $parentLayout = null;

    public function __construct(string $id, callable $renderFn, ?self $parentLayout = null)
    {
        $this->id = $id;
        $this->renderFn = $renderFn(...);
        $this->parentLayout = $parentLayout;
    }

    public static function make(string $id, callable $renderFn, ?self $parentLayout = null): self
    {
        return new self($id, $renderFn, $parentLayout);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getParent(): ?self
    {
        return $this->parentLayout;
    }

    /**
     * Render the layout wrapping child slot content.
     */
    public function render(string $childContent): string
    {
        $rendered = (string)($this->renderFn)($childContent);
        if ($this->parentLayout !== null) {
            return $this->parentLayout->render($rendered);
        }
        return $rendered;
    }
}
