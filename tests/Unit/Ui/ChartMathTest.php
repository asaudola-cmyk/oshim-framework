<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Tests\Harness\TestCase;
use Oshim\Ui\Components\Chart;

class ChartMathTest extends TestCase
{
    public function testCubicBezierSplineControlPointsCalculation(): void
    {
        $chart = new Chart();

        // 1. Zero points
        $p0 = $chart->calculateCubicBezierSpline([]);
        $this->assertSame('M 0 0', $p0);

        // 2. Single point
        $p1 = $chart->calculateCubicBezierSpline([['x' => 50.0, 'y' => 100.0]]);
        $this->assertSame('M 50.0 100.0', $p1);

        // 3. Two points (straight line)
        $p2 = $chart->calculateCubicBezierSpline([
            ['x' => 10.0, 'y' => 20.0],
            ['x' => 80.0, 'y' => 90.0],
        ]);
        $this->assertSame('M 10.0 20.0 L 80.0 90.0', $p2);

        // 4. Multiple points (smooth cubic Bézier spline)
        $pts = [
            ['x' => 10.0, 'y' => 100.0],
            ['x' => 50.0, 'y' => 40.0],
            ['x' => 90.0, 'y' => 80.0],
            ['x' => 130.0, 'y' => 20.0],
        ];
        $spline = $chart->calculateCubicBezierSpline($pts);

        $this->assertStringStartsWith('M 10.0 100.0', $spline);
        $this->assertStringContains(' C ', $spline);

        // Verify it contains 3 cubic Bézier segments
        $cCount = substr_count($spline, ' C ');
        $this->assertSame(3, $cCount);
    }

    public function testBoundsCalculationWithAllZeroDataset(): void
    {
        $chart = new Chart([
            'type' => 'area',
            'data' => [
                'labels' => ['A', 'B', 'C', 'D'],
                'datasets' => [
                    ['values' => [0, 0, 0, 0]],
                ],
            ],
        ]);
        $html = $chart->render();

        $this->assertStringContains('<svg', $html);
        $this->assertStringContains('class="oshim-chart', $html);
        $this->assertStringContains('d="M', $html);
    }

    public function testBoundsCalculationWithSingleValueDataset(): void
    {
        $chart = new Chart([
            'type' => 'line',
            'data' => [
                'labels' => ['Point 1'],
                'datasets' => [
                    ['values' => [42.0]],
                ],
            ],
        ]);
        $html = $chart->render();

        $this->assertStringContains('<svg', $html);
        $this->assertStringContains('data-tooltip="42.0%"', $html);
    }

    public function testBoundsCalculationWithNegativeValues(): void
    {
        $chart = new Chart([
            'type' => 'line',
            'data' => [
                'labels' => ['T1', 'T2', 'T3', 'T4'],
                'datasets' => [
                    ['values' => [-15.0, 5.0, -8.0, 22.0], 'color' => '#ff5252'],
                ],
            ],
        ]);
        $html = $chart->render();

        $this->assertStringContains('stroke="#ff5252"', $html);
        $this->assertStringContains('-15.0%', $html);
        $this->assertStringContains('22.0%', $html);
    }

    public function testClosedAreaPathGenerationWithBaseline(): void
    {
        $chart = new Chart([
            'type' => 'area',
            'width' => 500,
            'height' => 200,
            'data' => [
                'labels' => ['1', '2', '3'],
                'datasets' => [
                    ['label' => 'RAM', 'values' => [10, 30, 20], 'color' => '#00f2fe', 'fill' => true],
                ],
            ],
        ]);
        $html = $chart->render();

        $this->assertStringContains('class="oshim-chart__area"', $html);
        $this->assertStringContains('<linearGradient', $html);
        $this->assertStringContains('stop-opacity="0.35"', $html);
        $this->assertStringContains(' Z"', $html); // Closes path
    }

    public function testBarChartGroupedAndStackedGeometry(): void
    {
        // Grouped Bar
        $groupedChart = new Chart([
            'type' => 'bar',
            'stacked' => false,
            'data' => [
                'labels' => ['Q1', 'Q2'],
                'datasets' => [
                    ['label' => 'In', 'values' => [100, 200], 'color' => '#00f2fe'],
                    ['label' => 'Out', 'values' => [150, 250], 'color' => '#ffd600'],
                ],
            ],
        ]);
        $groupedHtml = $groupedChart->render();
        $this->assertStringContains('fill="#00f2fe"', $groupedHtml);
        $this->assertStringContains('fill="#ffd600"', $groupedHtml);

        // Stacked Bar
        $stackedChart = new Chart([
            'type' => 'bar',
            'stacked' => true,
            'data' => [
                'labels' => ['Q1', 'Q2'],
                'datasets' => [
                    ['label' => 'System', 'values' => [50, 60], 'color' => '#00e676'],
                    ['label' => 'User', 'values' => [80, 90], 'color' => '#7F00FF'],
                ],
            ],
        ]);
        $stackedHtml = $stackedChart->render();
        $this->assertStringContains('fill="#00e676"', $stackedHtml);
        $this->assertStringContains('fill="#7F00FF"', $stackedHtml);
    }

    public function testRadialGaugeArcMathAndClamping(): void
    {
        // 1. Normal 50% gauge
        $gauge50 = new Chart([
            'type' => 'gauge',
            'title' => 'Storage Used',
            'data' => ['value' => 50.0],
            'min' => 0,
            'max' => 100,
        ]);
        $html50 = $gauge50->render();
        $this->assertStringContains('50.0%', $html50);
        $this->assertStringContains('oshim-chart__gauge-arc', $html50);

        // 2. Clamped overflow gauge (>100%)
        $gaugeOver = new Chart([
            'type' => 'gauge',
            'data' => ['value' => 135.0],
            'min' => 0,
            'max' => 100,
        ]);
        $htmlOver = $gaugeOver->render();
        $this->assertStringContains('135.0%', $htmlOver);
        $this->assertStringContains('#ff5252', $htmlOver); // Warning/critical red color for >90%
    }
}
