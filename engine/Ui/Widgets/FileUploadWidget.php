<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * Drag and Drop File Upload Widget.
 */
class FileUploadWidget extends Element
{
    private string $uploadUrl;
    private string $fieldName;

    public function __construct(string $uploadUrl = '/api/upload', string $fieldName = 'file')
    {
        parent::__construct('div');
        $this->uploadUrl = $uploadUrl;
        $this->fieldName = $fieldName;
    }

    public static function uploader(string $uploadUrl = '/api/upload', string $fieldName = 'file'): self
    {
        return new self($uploadUrl, $fieldName);
    }

    public function render(): string
    {
        $id = 'oshim-uploader-' . uniqid();

        return <<<HTML
<div id="{$id}" style="border: 2px dashed rgba(0,242,254,0.4); border-radius: 16px; padding: 2.5rem 1.5rem; text-align: center; background: rgba(15,23,42,0.6); backdrop-filter: blur(12px); cursor: pointer; transition: all 0.2s ease;" ondragover="event.preventDefault(); this.style.borderColor='#00f2fe'; this.style.background='rgba(0,242,254,0.05)';" ondragleave="this.style.borderColor='rgba(0,242,254,0.4)'; this.style.background='rgba(15,23,42,0.6)';" onclick="document.getElementById('{$id}-input').click()">
    <input type="file" id="{$id}-input" name="{$this->fieldName}" style="display: none;" onchange="alert('File selected: ' + this.files[0].name)" />
    <span style="font-size: 2.5rem; display: block; margin-bottom: 0.5rem;">☁️</span>
    <h4 style="font-size: 1rem; font-weight: 600; color: #f8fafc; margin-bottom: 0.25rem;">Drop your file here or browse</h4>
    <p style="font-size: 0.8rem; color: #94a3b8; margin: 0;">Supports PNG, JPG, PDF, ZIP up to 50MB</p>
</div>
HTML;
    }
}
