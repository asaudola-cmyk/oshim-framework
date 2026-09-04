<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * Interactive Server-Driven Data Table Widget.
 */
class DataTableWidget extends Element
{
    private array $columns;
    private array $rows;
    private string $title;

    public function __construct(string $title = '', array $columns = [], array $rows = [])
    {
        parent::__construct('div');
        $this->title = $title;
        $this->columns = $columns;
        $this->rows = $rows;
        $this->class('oshim-glass-card oshim-datatable-widget');
    }

    public static function table(string $title, array $columns, array $rows): self
    {
        return new self($title, $columns, $rows);
    }

    public function render(): string
    {
        $headerHtml = '';
        foreach ($this->columns as $col) {
            $label = htmlspecialchars($col['label'] ?? (string)$col);
            $headerHtml .= "<th style=\"padding: 0.75rem 1rem; text-align: left; font-size: 0.8rem; font-weight: 600; color: #94a3b8; border-bottom: 1px solid rgba(255,255,255,0.08); text-transform: uppercase;\">{$label}</th>";
        }

        $rowsHtml = '';
        foreach ($this->rows as $row) {
            $rowsHtml .= '<tr style="border-bottom: 1px solid rgba(255,255,255,0.04); transition: background 0.15s ease;" onmouseover="this.style.background=\'rgba(255,255,255,0.03)\'" onmouseout="this.style.background=\'transparent\'">';
            foreach ($this->columns as $key => $col) {
                $field = is_array($col) ? ($col['field'] ?? $key) : $key;
                $val = $row[$field] ?? '';
                $cellHtml = htmlspecialchars((string)$val);

                // Auto status badge formatting
                if (strtolower((string)$val) === 'running' || strtolower((string)$val) === 'active' || strtolower((string)$val) === 'paid') {
                    $cellHtml = '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 9999px; background: rgba(0,230,118,0.1); border: 1px solid rgba(0,230,118,0.3); color: #00e676; font-size: 0.75rem; font-weight: 600;"><span style="width: 6px; height: 6px; border-radius: 50%; background: #00e676;"></span>' . $cellHtml . '</span>';
                } elseif (strtolower((string)$val) === 'stopped' || strtolower((string)$val) === 'failed') {
                    $cellHtml = '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 9999px; background: rgba(255,82,82,0.1); border: 1px solid rgba(255,82,82,0.3); color: #ff5252; font-size: 0.75rem; font-weight: 600;"><span style="width: 6px; height: 6px; border-radius: 50%; background: #ff5252;"></span>' . $cellHtml . '</span>';
                }

                $rowsHtml .= "<td style=\"padding: 0.85rem 1rem; font-size: 0.9rem; color: #f8fafc;\">{$cellHtml}</td>";
            }
            $rowsHtml .= '</tr>';
        }

        $titleEsc = htmlspecialchars($this->title, ENT_QUOTES);
        $totalRows = count($this->rows);

        return <<<HTML
<div class="oshim-glass-card" style="padding: 1.5rem; overflow: hidden;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="font-size: 1.1rem; font-weight: 600; color: #f8fafc;">{$titleEsc}</h3>
        <span style="font-size: 0.8rem; color: #94a3b8; background: rgba(255,255,255,0.06); padding: 3px 10px; border-radius: 6px;">Total: {$totalRows}</span>
    </div>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    {$headerHtml}
                </tr>
            </thead>
            <tbody>
                {$rowsHtml}
            </tbody>
        </table>
    </div>
</div>
HTML;
    }
}
