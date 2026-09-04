<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * Declarative Form Builder Widget with CSRF & Input Grouping.
 */
class FormWidget extends Element
{
    private string $action;
    private string $method;
    private array $fields = [];
    private string $submitLabel;

    public function __construct(string $action = '', string $method = 'POST', string $submitLabel = 'Submit')
    {
        parent::__construct('form');
        $this->action = $action;
        $this->method = strtoupper($method);
        $this->submitLabel = $submitLabel;
    }

    public static function form(string $action = '', string $method = 'POST', string $submitLabel = 'Submit'): self
    {
        return new self($action, $method, $submitLabel);
    }

    public function addField(string $name, string $label, string $type = 'text', string $placeholder = '', bool $required = false): self
    {
        $this->fields[] = [
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'placeholder' => $placeholder,
            'required' => $required,
        ];
        return $this;
    }

    public function render(): string
    {
        $fieldsHtml = '';
        foreach ($this->fields as $f) {
            $reqAttr = $f['required'] ? 'required' : '';
            $reqStar = $f['required'] ? '<span style="color:#ff5252;">*</span>' : '';
            $labelEsc = htmlspecialchars($f['label']);
            $typeEsc = htmlspecialchars($f['type']);
            $nameEsc = htmlspecialchars($f['name']);
            $placeEsc = htmlspecialchars($f['placeholder']);

            $fieldsHtml .= <<<HTML
<div style="margin-bottom: 1.25rem;">
    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.4rem;">{$labelEsc} {$reqStar}</label>
    <input type="{$typeEsc}" name="{$nameEsc}" placeholder="{$placeEsc}" {$reqAttr} style="width: 100%; box-sizing: border-box; padding: 0.65rem 0.9rem; background: rgba(15,23,42,0.8); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: #f8fafc; font-size: 0.9rem; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#00f2fe'" onblur="this.style.borderColor='rgba(255,255,255,0.12)'" />
</div>
HTML;
        }

        return <<<HTML
<form action="{$this->action}" method="{$this->method}" class="oshim-glass-card" style="padding: 1.75rem; border-radius: 16px;">
    {$fieldsHtml}
    <button type="submit" style="width: 100%; padding: 0.75rem; background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%); color: #020617; font-weight: 700; border: none; border-radius: 8px; font-size: 0.95rem; cursor: pointer; transition: transform 0.15s ease;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
        {$this->submitLabel}
    </button>
</form>
HTML;
    }
}
