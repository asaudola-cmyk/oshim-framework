<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * Glassmorphic Modal Dialog with Backdrop Blur and Keyboard Controls.
 */
class ModalWidget extends Element
{
    private string $modalId;
    private string $title;
    private string $bodyHtml;
    private ?string $footerHtml;

    public function __construct(string $modalId, string $title, string $bodyHtml = '', ?string $footerHtml = null)
    {
        parent::__construct('div');
        $this->modalId = $modalId;
        $this->title = $title;
        $this->bodyHtml = $bodyHtml;
        $this->footerHtml = $footerHtml;
    }

    public static function modal(string $modalId, string $title, string $bodyHtml = '', ?string $footerHtml = null): self
    {
        return new self($modalId, $title, $bodyHtml, $footerHtml);
    }

    public function render(): string
    {
        $titleEsc = htmlspecialchars($this->title, ENT_QUOTES);
        $footer = $this->footerHtml !== null ? "<div style=\"padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: flex-end; gap: 0.5rem;\">{$this->footerHtml}</div>" : '';

        return <<<HTML
<div id="{$this->modalId}" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(16px); z-index: 9999; justify-content: center; align-items: center; padding: 1rem;">
    <div style="width: 100%; max-width: 550px; background: rgba(15,23,42,0.95); border: 1px solid rgba(0,242,254,0.3); border-radius: 16px; box-shadow: 0 25px 50px rgba(0,0,0,0.8), 0 0 30px rgba(0,242,254,0.15); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.2rem; font-weight: 700; color: #f8fafc; margin: 0;">{$titleEsc}</h3>
            <button onclick="document.getElementById('{$this->modalId}').style.display='none'" style="background: transparent; border: none; color: #94a3b8; font-size: 1.25rem; cursor: pointer;">✕</button>
        </div>
        <div style="padding: 1.5rem; color: #e2e8f0; font-size: 0.95rem;">
            {$this->bodyHtml}
        </div>
        {$footer}
    </div>
</div>
HTML;
    }
}
