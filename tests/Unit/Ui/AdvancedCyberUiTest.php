<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Testing\TestCase;
use Oshim\Ui\Widgets\CodeStudioWidget;
use Oshim\Ui\Widgets\HologramCardWidget;
use Oshim\Ui\Widgets\KanbanPipelineWidget;
use Oshim\Ui\Widgets\ParticleBackgroundWidget;
use Oshim\Ui\Widgets\SovereignMasterDashboard;
use Oshim\Ui\Widgets\TelemetryHudWidget;

class AdvancedCyberUiTest extends TestCase
{
    public function testHologramCardWidgetRendersCorrectly(): void
    {
        $widget = HologramCardWidget::create(
            'Quantum Node',
            'Cluster Subsystem',
            '<p>Ultra fast matrix calculation</p>',
            '#10b981',
            '⚡'
        );

        $html = $widget->render();

        $this->assertStringContainsString('Quantum Node', $html);
        $this->assertStringContainsString('Cluster Subsystem', $html);
        $this->assertStringContainsString('Ultra fast matrix calculation', $html);
        $this->assertStringContainsString('#10b981', $html);
        $this->assertStringContainsString('conic-gradient', $html);
    }

    public function testTelemetryHudWidgetRendersGaugesAndTickers(): void
    {
        $widget = TelemetryHudWidget::create([
            'Core CPU' => ['value' => 25, 'max' => 100, 'unit' => '%', 'color' => '#00f2fe', 'icon' => '⚡'],
        ], [
            'System Driver' => 'KVM Driver Ready',
        ]);

        $html = $widget->render();

        $this->assertStringContainsString('Core CPU', $html);
        $this->assertStringContainsString('25', $html);
        $this->assertStringContainsString('stroke-dasharray', $html);
        $this->assertStringContainsString('System Driver', $html);
        $this->assertStringContainsString('KVM Driver Ready', $html);
    }

    public function testCodeStudioWidgetRendersSyntaxTokensAndRunAction(): void
    {
        $code = "<?php\nclass Engine {\n    public function run() {\n        return 42;\n    }\n}";
        $widget = CodeStudioWidget::create('Engine.php', $code, 'php', 'Execution success: 42');

        $html = $widget->render();

        $this->assertStringContainsString('Engine.php', $html);
        $this->assertStringContainsString('class', $html);
        $this->assertStringContainsString('Execution success: 42', $html);
        $this->assertStringContainsString('Run', $html);
        $this->assertStringContainsString('Copy', $html);
    }

    public function testKanbanPipelineWidgetRendersColumnsAndDraggableCards(): void
    {
        $widget = KanbanPipelineWidget::create([
            'Sprint 1' => [
                ['id' => 'card_101', 'title' => 'WebRTC Media Engine', 'priority' => 'critical', 'tag' => 'Core', 'assignee' => 'Alpha'],
            ],
        ]);

        $html = $widget->render();

        $this->assertStringContainsString('Sprint 1', $html);
        $this->assertStringContainsString('card_101', $html);
        $this->assertStringContainsString('WebRTC Media Engine', $html);
        $this->assertStringContainsString('draggable="true"', $html);
        $this->assertStringContainsString('ondragstart', $html);
    }

    public function testParticleBackgroundWidgetGeneratesCanvasScript(): void
    {
        $widget = ParticleBackgroundWidget::create('#00f2fe', 30);
        $html = $widget->render();

        $this->assertStringContainsString('canvas id="oshim-particle-canvas"', $html);
        $this->assertStringContainsString('requestAnimationFrame', $html);
        $this->assertStringContainsString('#00f2fe', $html);
    }

    public function testSovereignMasterDashboardGeneratesCompleteHtmlAndCompiledTailwind(): void
    {
        $dashboard = SovereignMasterDashboard::create('OSHIM Sovereign Test Workstation');

        $body = $dashboard->render();
        $this->assertStringContainsString('OSHIM SOVEREIGN WORKSTATION', $body);
        $this->assertStringContainsString('oshim-telemetry-hud', $body);
        $this->assertStringContainsString('oshim-kanban-pipeline', $body);
        $this->assertStringContainsString('oshim-code-studio', $body);

        $fullHtml = $dashboard->renderFullPage();
        $this->assertStringContainsString('<!DOCTYPE html>', $fullHtml);
        $this->assertStringContainsString('<title>OSHIM Sovereign Test Workstation</title>', $fullHtml);
        $this->assertStringContainsString('<style>', $fullHtml);
    }
}
