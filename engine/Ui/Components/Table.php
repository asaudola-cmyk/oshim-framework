<?php
declare(strict_types=1);

namespace Oshim\Ui\Components;

use Oshim\Ui\Component;

class Table extends Component
{
    /** @var array<array{key?: string, label?: string, width?: string, align?: string, render?: callable}|string> */
    protected array $columns = [];
    protected array $data = [];
    protected bool $hoverable = true;
    protected bool $striped = false;
    protected bool $bordered = true;
    protected bool $dense = false;
    protected string $emptyMessage = 'No records found';
    protected ?string $caption = null;
    protected ?string $footer = null;
    protected string $class = '';

    public function mount(array $props): void
    {
        $this->columns = (array)($props['columns'] ?? []);
        $this->data = (array)($props['data'] ?? ($props['rows'] ?? []));
        $this->hoverable = (bool)($props['hoverable'] ?? true);
        $this->striped = (bool)($props['striped'] ?? false);
        $this->bordered = (bool)($props['bordered'] ?? true);
        $this->dense = (bool)($props['dense'] ?? false);
        $this->emptyMessage = (string)($props['emptyMessage'] ?? 'No records found');
        $this->caption = $props['caption'] ?? null;
        $this->footer = $props['footer'] ?? null;
        $this->class = (string)($props['class'] ?? '');
    }

    public function render(): string
    {
        $tableClasses = ['oshim-table', 'oshim-glass-table'];
        if ($this->hoverable) {
            $tableClasses[] = 'oshim-table--hover';
        }
        if ($this->striped) {
            $tableClasses[] = 'oshim-table--striped';
        }
        if ($this->bordered) {
            $tableClasses[] = 'oshim-table--bordered';
        }
        if ($this->dense) {
            $tableClasses[] = 'oshim-table--dense';
        }

        $html = '<div class="oshim-table-wrapper ' . $this->escape($this->class) . '">';
        $html .= '<table data-oshim-id="' . $this->escape($this->id) . '" class="' . $this->escape(implode(' ', $tableClasses)) . '">';

        if ($this->caption !== null) {
            $html .= '<caption>' . $this->escape($this->caption) . '</caption>';
        }

        // Header
        $html .= '<thead class="oshim-table__head"><tr>';
        $normalizedCols = [];
        foreach ($this->columns as $idx => $col) {
            if (is_string($col)) {
                $colMeta = ['key' => $col, 'label' => $col, 'align' => 'left'];
            } else {
                $colMeta = (array)$col;
                if (!isset($colMeta['key'])) {
                    $colMeta['key'] = (string)$idx;
                }
                if (!isset($colMeta['label'])) {
                    $colMeta['label'] = (string)$colMeta['key'];
                }
            }
            $normalizedCols[] = $colMeta;

            $align = in_array($colMeta['align'] ?? '', ['left', 'center', 'right'], true) ? $colMeta['align'] : 'left';
            $widthStyle = !empty($colMeta['width']) ? ' style="width: ' . $this->escape($colMeta['width']) . ';"' : '';
            $html .= '<th class="oshim-table__th text-' . $align . '"' . $widthStyle . '>' . $this->escape($colMeta['label'] ?? '') . '</th>';
        }
        $html .= '</tr></thead>';

        // Body
        $html .= '<tbody class="oshim-table__body">';
        if (empty($this->data)) {
            $colCount = max(1, count($normalizedCols));
            $html .= '<tr class="oshim-table__empty-row"><td colspan="' . $colCount . '" class="oshim-table__empty-cell">';
            if ($this->hasSlot('emptyState')) {
                $html .= $this->slot('emptyState');
            } else {
                $html .= '<div class="oshim-empty-state">';
                $html .= '<svg class="oshim-empty-state__icon" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
                $html .= '<p class="oshim-empty-state__text">' . $this->escape($this->emptyMessage) . '</p>';
                $html .= '</div>';
            }
            $html .= '</td></tr>';
        } else {
            foreach ($this->data as $row) {
                $rowArray = (array)$row;
                $html .= '<tr class="oshim-table__row">';
                foreach ($normalizedCols as $colMeta) {
                    $key = $colMeta['key'];
                    $val = $rowArray[$key] ?? null;
                    $align = in_array($colMeta['align'] ?? '', ['left', 'center', 'right'], true) ? $colMeta['align'] : 'left';

                    if (isset($colMeta['render']) && is_callable($colMeta['render'])) {
                        $cellHtml = (string)$colMeta['render']($val, $rowArray);
                    } elseif ($val === null) {
                        $cellHtml = '<span class="oshim-null">NULL</span>';
                    } else {
                        $cellHtml = $this->escape((string)$val);
                    }

                    $html .= '<td class="oshim-table__td text-' . $align . '">' . $cellHtml . '</td>';
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody>';

        if ($this->footer !== null || $this->hasSlot('footer')) {
            $footerContent = $this->hasSlot('footer') ? $this->slot('footer') : $this->footer;
            $html .= '<tfoot class="oshim-table__foot">' . $footerContent . '</tfoot>';
        }

        $html .= '</table></div>';
        return $html;
    }
}
