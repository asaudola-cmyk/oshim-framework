<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * Visual Multi-Agent Squad & Graph Execution Console.
 */
class AgentStudioWidget extends Element
{
    private string $teamName;
    private array $agents = [];

    public function __construct(string $teamName = 'Autonomous Squad')
    {
        parent::__construct('div');
        $this->teamName = $teamName;
        $this->class('oshim-glass-card oshim-agent-studio');
    }

    public static function studio(string $teamName = 'Autonomous Squad'): self
    {
        return new self($teamName);
    }

    public function addAgent(string $role, string $status = 'IDLE', string $currentTask = '', string $icon = '🤖'): self
    {
        $this->agents[] = [
            'role' => $role,
            'status' => $status,
            'task' => $currentTask,
            'icon' => $icon,
        ];
        return $this;
    }

    public function render(): string
    {
        $cardsHtml = '';
        foreach ($this->agents as $ag) {
            $isBusy = $ag['status'] === 'RUNNING' || $ag['status'] === 'ACTIVE';
            $statusBadge = $isBusy
                ? '<span style="font-size: 0.75rem; padding: 2px 8px; border-radius: 9999px; background: rgba(0,230,118,0.15); color: #00e676; border: 1px solid rgba(0,230,118,0.3);">● ACTIVE</span>'
                : '<span style="font-size: 0.75rem; padding: 2px 8px; border-radius: 9999px; background: rgba(255,255,255,0.06); color: #94a3b8;">IDLE</span>';

            $taskDesc = !empty($ag['task']) ? "<p style=\"font-size: 0.8rem; color: #cbd5e1; margin: 0.4rem 0 0 0;\">Task: {$ag['task']}</p>" : '';

            $cardsHtml .= <<<HTML
<div style="background: rgba(15,23,42,0.7); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 1.5rem;">{$ag['icon']}</span>
        <div>
            <h4 style="font-size: 0.95rem; font-weight: 600; color: #f8fafc; margin: 0;">{$ag['role']}</h4>
            {$taskDesc}
        </div>
    </div>
    {$statusBadge}
</div>
HTML;
        }

        return <<<HTML
<div class="oshim-glass-card" style="padding: 1.5rem; border-radius: 16px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.25rem;">👥</span>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #f8fafc; margin: 0;">{$this->teamName}</h3>
        </div>
        <button style="padding: 0.4rem 0.9rem; font-size: 0.8rem; font-weight: 600; background: rgba(0,242,254,0.15); color: #00f2fe; border: 1px solid rgba(0,242,254,0.3); border-radius: 8px; cursor: pointer;">⚡ Dispatch Task</button>
    </div>
    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        {$cardsHtml}
    </div>
</div>
HTML;
    }
}
