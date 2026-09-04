<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Widgets\ChartWidget;
use Oshim\Ui\Widgets\CommandPaletteWidget;
use Oshim\Ui\Widgets\DataTableWidget;

final class AdvancedWidgetsTest extends TestCase
{
    public function testChartWidgetSvgGeneration(): void
    {
        $chart = ChartWidget::area('CPU Utilization (%)', [12.5, 24.0, 18.2, 45.0, 32.1, 78.4], '#00f2fe');
        $html = $chart->render();

        $this->assertStringContainsString('CPU Utilization (%)', $html);
        $this->assertStringContainsString('<svg viewBox="0 0 400 120"', $html);
        $this->assertStringContainsString('<path d="M', $html);
        $this->assertStringContainsString('78.4', $html);

        $barChart = ChartWidget::bar('Network Inbound (MB/s)', [10, 25, 40, 30, 80], '#7F00FF');
        $barHtml = $barChart->render();
        $this->assertStringContainsString('<rect', $barHtml);
    }

    public function testCommandPaletteWidgetRender(): void
    {
        $palette = CommandPaletteWidget::palette()
            ->addCommand('Create Cloud VPS', 'app.vps.create', 'Ctrl+N', '⚡')
            ->addCommand('Search Invoices', 'app.billing.search', 'Ctrl+I', '📄');

        $html = $palette->render();

        $this->assertStringContainsString('id="oshim-command-palette"', $html);
        $this->assertStringContainsString('Create Cloud VPS', $html);
        $this->assertStringContainsString('Search Invoices', $html);
        $this->assertStringContainsString('Ctrl+N', $html);
    }

    public function testDataTableWidgetRender(): void
    {
        $table = DataTableWidget::table(
            'Active Virtual Machines',
            [
                ['label' => 'VM ID', 'field' => 'id'],
                ['label' => 'Hostname', 'field' => 'hostname'],
                ['label' => 'Status', 'field' => 'status'],
            ],
            [
                ['id' => 'vm-101', 'hostname' => 'vps-dhaka-01', 'status' => 'RUNNING'],
                ['id' => 'vm-102', 'hostname' => 'vps-sylhet-02', 'status' => 'STOPPED'],
            ]
        );

        $html = $table->render();

        $this->assertStringContainsString('Active Virtual Machines', $html);
        $this->assertStringContainsString('vm-101', $html);
        $this->assertStringContainsString('vps-dhaka-01', $html);
        $this->assertStringContainsString('RUNNING', $html);
        $this->assertStringContainsString('STOPPED', $html);
    }
}
