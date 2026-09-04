<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Widgets\AiChatWidget;
use Oshim\Ui\Widgets\AgentStudioWidget;

final class AiChatWidgetTest extends TestCase
{
    public function testAiChatWidgetRendering(): void
    {
        $widget = AiChatWidget::chat('/api/ai/stream', 'Ask anything...');
        $html = $widget->render();

        $this->assertStringContainsString('OSHIM Sovereign AI Studio', $html);
        $this->assertStringContainsString('Ask anything...', $html);
        $this->assertStringContainsString('Live Stream', $html);
    }

    public function testAgentStudioWidgetRendering(): void
    {
        $studio = AgentStudioWidget::studio('Cloud Operations Squad')
            ->addAgent('Architect', 'ACTIVE', 'Designing VM cluster', '📐')
            ->addAgent('Deployer', 'IDLE', '', '🚀');

        $html = $studio->render();
        $this->assertStringContainsString('Cloud Operations Squad', $html);
        $this->assertStringContainsString('Architect', $html);
        $this->assertStringContainsString('Designing VM cluster', $html);
        $this->assertStringContainsString('ACTIVE', $html);
        $this->assertStringContainsString('IDLE', $html);
    }
}
