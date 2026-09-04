<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * Server-Rendered Responsive Glassmorphic SVG Vector Chart Widget.
 */
class ChartWidget extends Element
{
    private string $title;
    private array $dataPoints;
    private string $type;
    private string $color;

    public function __construct(string $title, array $dataPoints, string $type = 'area', string $color = '#00f2fe')
    {
        parent::__construct('div');
        $this->title = $title;
        $this->dataPoints = array_map('floatval', $dataPoints);
        $this->type = $type;
        $this->color = $color;
        $this->class('oshim-glass-card oshim-chart-widget');
    }

    public static function area(string $title, array $dataPoints, string $color = '#00f2fe'): self
    {
        return new self($title, $dataPoints, 'area', $color);
    }

    public static function sparkline(string $title, array $dataPoints, string $color = '#00e676'): self
    {
        return new self($title, $dataPoints, 'sparkline', $color);
    }

    public static function bar(string $title, array $dataPoints, string $color = '#7F00FF'): self
    {
        return new self($title, $dataPoints, 'bar', $color);
    }

    public function render(): string
    {
        $count = count($this->dataPoints);
        if ($count < 2) {
            return "<div class=\"oshim-glass-card\"><p>Insufficient data points</p></div>";
        }

        $min = min($this->dataPoints);
        $max = max($this->dataPoints);
        $range = max(0.001, $max - $min);

        $width = 400;
        $height = 120;
        $padding = 10;
        $chartWidth = $width - ($padding * 2);
        $chartHeight = $height - ($padding * 2);

        $points = [];
        $svgContent = '';
        $gradientId = 'grad-' . substr(md5($this->title . microtime()), 0, 8);

        for ($i = 0; $i < $count; $i++) {
            $x = $padding + ($i * ($chartWidth / ($count - 1)));
            $normalizedY = ($this->dataPoints[$i] - $min) / $range;
            $y = ($height - $padding) - ($normalizedY * $chartHeight);
            $points[] = "{$x},{$y}";
        }

        $linePath = 'M ' . implode(' L ', $points);
        $firstX = $padding;
        $lastX = $padding + $chartWidth;
        $bottomY = $height - $padding;
        $areaPath = "{$linePath} L {$lastX},{$bottomY} L {$firstX},{$bottomY} Z";

        if ($this->type === 'area') {
            $svgContent .= "<defs><linearGradient id=\"{$gradientId}\" x1=\"0%\" y1=\"0%\" x2=\"0%\" y2=\"100%\"><stop offset=\"0%\" stop-color=\"{$this->color}\" stop-opacity=\"0.4\"/><stop offset=\"100%\" stop-color=\"{$this->color}\" stop-opacity=\"0.0\"/></linearGradient></defs>";
            $svgContent .= "<path d=\"{$areaPath}\" fill=\"url(#{$gradientId})\" />";
            $svgContent .= "<path d=\"{$linePath}\" fill=\"none\" stroke=\"{$this->color}\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\" />";
        } elseif ($this->type === 'sparkline') {
            $svgContent .= "<path d=\"{$linePath}\" fill=\"none\" stroke=\"{$this->color}\" stroke-width=\"2.5\" stroke-linecap=\"round\" />";
        } elseif ($this->type === 'bar') {
            $barWidth = max(4, ($chartWidth / $count) * 0.6);
            for ($i = 0; $i < $count; $i++) {
                $x = $padding + ($i * ($chartWidth / $count)) + 2;
                $normalizedY = ($this->dataPoints[$i] - $min) / $range;
                $barH = max(4, $normalizedY * $chartHeight);
                $y = $bottomY - $barH;
                $svgContent .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$barWidth}\" height=\"{$barH}\" rx=\"3\" fill=\"{$this->color}\" opacity=\"0.85\" />";
            }
        }

        $latestVal = end($this->dataPoints);
        $titleEsc = htmlspecialchars($this->title, ENT_QUOTES);

        return <<<HTML
<div class="oshim-glass-card" style="padding: 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <span style="font-size: 0.85rem; font-weight: 600; color: #94a3b8;">{$titleEsc}</span>
        <span style="font-size: 1.1rem; font-weight: 700; color: {$this->color};">{$latestVal}</span>
    </div>
    <svg viewBox="0 0 {$width} {$height}" style="width: 100%; height: auto; overflow: visible;">
        {$svgContent}
    </svg>
</div>
HTML;
    }
}
