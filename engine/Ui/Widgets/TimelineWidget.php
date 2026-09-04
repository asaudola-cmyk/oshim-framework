<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * Activity & Audit Trail Timeline Widget.
 */
class TimelineWidget extends Element
{
    private array $events = [];

    public static function timeline(): self
    {
        return new self();
    }

    public function addEvent(string $title, string $time, string $description = '', string $icon = '⚡', string $statusColor = '#00f2fe'): self
    {
        $this->events[] = [
            'title' => $title,
            'time' => $time,
            'desc' => $description,
            'icon' => $icon,
            'color' => $statusColor,
        ];
        return $this;
    }

    public function render(): string
    {
        $itemsHtml = '';
        foreach ($this->events as $ev) {
            $itemsHtml .= sprintf(
                '<div style="position: relative; padding-left: 2.25rem; margin-bottom: 1.5rem;">
                    <div style="position: absolute; left: 0; top: 0; width: 24px; height: 24px; border-radius: 50%%; background: rgba(15,23,42,0.9); border: 2px solid %s; display: flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                        %s
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.2rem;">
                        <h4 style="font-size: 0.95rem; font-weight: 600; color: #f8fafc; margin: 0;">%s</h4>
                        <span style="font-size: 0.75rem; color: #94a3b8;">%s</span>
                    </div>
                    <p style="font-size: 0.85rem; color: #cbd5e1; margin: 0;">%s</p>
                </div>',
                htmlspecialchars($ev['color']),
                htmlspecialchars($ev['icon']),
                htmlspecialchars($ev['title']),
                htmlspecialchars($ev['time']),
                htmlspecialchars($ev['desc'])
            );
        }

        return <<<HTML
<div class="oshim-glass-card" style="padding: 1.5rem; position: relative;">
    <div style="position: absolute; left: 2.25rem; top: 2rem; bottom: 2rem; width: 2px; background: rgba(255,255,255,0.08);"></div>
    {$itemsHtml}
</div>
HTML;
    }
}
