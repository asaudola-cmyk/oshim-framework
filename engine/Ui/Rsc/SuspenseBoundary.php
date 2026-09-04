<?php
declare(strict_types=1);

namespace Oshim\Ui\Rsc;

use Closure;

/**
 * Suspense Boundary for Streaming SSR.
 * Renders fallback skeleton immediately, then resolves async child content.
 */
class SuspenseBoundary
{
    private string $id;
    private string $fallbackHtml;
    private Closure $asyncResolver;

    public function __construct(string $fallbackHtml, callable $asyncResolver, ?string $id = null)
    {
        $this->id = $id ?? ('suspense-' . substr(md5(uniqid('', true)), 0, 8));
        $this->fallbackHtml = $fallbackHtml;
        $this->asyncResolver = $asyncResolver(...);
    }

    public static function make(string $fallbackHtml, callable $asyncResolver, ?string $id = null): self
    {
        return new self($fallbackHtml, $asyncResolver, $id);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFallbackHtml(): string
    {
        return $this->fallbackHtml;
    }

    /**
     * Render the initial HTML placeholder.
     */
    public function renderInitial(): string
    {
        return sprintf(
            '<div id="%s" data-oshim-suspense="pending">%s</div>',
            htmlspecialchars($this->id, ENT_QUOTES),
            $this->fallbackHtml
        );
    }

    /**
     * Resolve the async content and produce the replacement stream chunk.
     */
    public function resolveChunk(): array
    {
        $resolvedHtml = (string)($this->asyncResolver)();

        $chunkHtml = sprintf(
            '<template id="chunk-%s">%s</template><script>(function(){var t=document.getElementById("chunk-%s");var target=document.getElementById("%s");if(t&&target){target.replaceWith(t.content.cloneNode(true));}})();</script>',
            htmlspecialchars($this->id, ENT_QUOTES),
            $resolvedHtml,
            htmlspecialchars($this->id, ENT_QUOTES),
            htmlspecialchars($this->id, ENT_QUOTES)
        );

        return [
            'id' => $this->id,
            'resolved_html' => $resolvedHtml,
            'stream_chunk' => $chunkHtml,
        ];
    }
}
