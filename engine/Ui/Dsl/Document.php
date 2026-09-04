<?php
declare(strict_types=1);

namespace Oshim\Ui\Dsl;

use Oshim\Ui\Theme\OshimTheme;

class Document
{
    private string $title;
    private ?Element $navbar = null;
    private array $bodyElements = [];
    private ?Element $footer = null;
    private string $lang = 'bn';
    private ?string $customCss = null;

    public function __construct(string $title = 'OSHIM Cloud')
    {
        $this->title = $title;
    }

    public static function make(string $title = 'OSHIM Cloud'): self
    {
        return new self($title);
    }

    public function lang(string $lang): self
    {
        $this->lang = $lang;
        return $this;
    }

    public function navbar(Element $navbar): self
    {
        $this->navbar = $navbar;
        return $this;
    }

    public function footer(Element $footer): self
    {
        $this->footer = $footer;
        return $this;
    }

    public function body(array|Element $elements): self
    {
        if (is_array($elements)) {
            $this->bodyElements = array_merge($this->bodyElements, $elements);
        } else {
            $this->bodyElements[] = $elements;
        }
        return $this;
    }

    public function customCss(string $css): self
    {
        $this->customCss = $css;
        return $this;
    }

    private bool $enableClientRuntime = true;
    private bool $enableTailwind = true;

    public function withClientRuntime(bool $enable = true): self
    {
        $this->enableClientRuntime = $enable;
        return $this;
    }

    public function withTailwind(bool $enable = true): self
    {
        $this->enableTailwind = $enable;
        return $this;
    }

    public function render(): string
    {
        $themeCss = OshimTheme::getEmbeddedCss();
        if ($this->customCss) {
            $themeCss .= "\n" . $this->customCss;
        }

        $navbarHtml = $this->navbar ? $this->navbar->render() : '';
        $footerHtml = $this->footer ? $this->footer->render() : '';

        $bodyContent = '';
        foreach ($this->bodyElements as $el) {
            if ($el instanceof Element) {
                $bodyContent .= $el->render();
            } elseif (is_string($el)) {
                $bodyContent .= $el;
            }
        }

        $fullHtmlToScan = $navbarHtml . $bodyContent . $footerHtml;

        if ($this->enableTailwind) {
            $tailwindCss = \Oshim\Ui\Css\TailwindJitCompiler::compile($fullHtmlToScan);
            if ($tailwindCss !== '') {
                $themeCss .= "\n/* --- OSHIM Pure PHP Tailwind JIT --- */\n" . $tailwindCss;
            }
        }

        $runtimeTag = $this->enableClientRuntime ? \Oshim\Ui\Runtime\OshimClientRuntime::renderTag() : '';
        $escapedTitle = htmlspecialchars($this->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="{$this->lang}" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$escapedTitle}</title>
    <link rel="manifest" href="/manifest.json">
    <style>
{$themeCss}
    </style>
</head>
<body>
    {$navbarHtml}
    <main style="flex: 1;">
        {$bodyContent}
    </main>
    {$footerHtml}
    <script src="/oshim-livedom.js"></script>
    {$runtimeTag}
</body>
</html>
HTML;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}

