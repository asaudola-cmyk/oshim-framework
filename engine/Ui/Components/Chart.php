<?php
declare(strict_types=1);

namespace Oshim\Ui\Components;

use Oshim\Ui\Component;

class Chart extends Component
{
    protected string $type = 'line'; // line, area, bar, gauge, sparkline
    protected array $data = [];      // ['labels' => [], 'datasets' => [['label' => '', 'values' => [], 'color' => '', 'fill' => bool]]]
    protected int $width = 600;
    protected int $height = 240;
    protected ?string $title = null;
    protected string $unit = '%';
    protected ?float $min = null;
    protected ?float $max = null;
    protected bool $stacked = false;
    protected bool $showGrid = true;
    protected bool $showDots = true;
    protected bool $showLegend = true;
    protected bool $animated = true;
    protected string $class = '';

    public function mount(array $props): void
    {
        $this->type = in_array($props['type'] ?? '', ['line', 'area', 'bar', 'gauge', 'sparkline'], true) ? $props['type'] : 'line';
        $this->data = (array)($props['data'] ?? []);
        $this->width = max(50, (int)($props['width'] ?? 600));
        $this->height = max(30, (int)($props['height'] ?? 240));
        $this->title = $props['title'] ?? null;
        $this->unit = (string)($props['unit'] ?? '%');
        $this->min = isset($props['min']) ? (float)$props['min'] : null;
        $this->max = isset($props['max']) ? (float)$props['max'] : null;
        $this->stacked = (bool)($props['stacked'] ?? false);
        $this->showGrid = (bool)($props['showGrid'] ?? true);
        $this->showDots = (bool)($props['showDots'] ?? true);
        $this->showLegend = (bool)($props['showLegend'] ?? true);
        $this->animated = (bool)($props['animated'] ?? true);
        $this->class = (string)($props['class'] ?? '');
    }

    public function render(): string
    {
        return match ($this->type) {
            'line', 'area' => $this->renderSplineChart($this->type === 'area'),
            'bar' => $this->renderBarChart(),
            'gauge' => $this->renderGaugeChart(),
            'sparkline' => $this->renderSparkline(),
            default => $this->renderSplineChart(false),
        };
    }

    /**
     * Pure PHP Bézier Spline Line & Area Chart Renderer
     */
    private function renderSplineChart(bool $isArea): string
    {
        $labels = $this->data['labels'] ?? [];
        $datasets = $this->data['datasets'] ?? [];

        // Support simple data format: ['values' => [...]]
        if (empty($datasets) && isset($this->data['values'])) {
            $datasets = [['label' => 'Series', 'values' => $this->data['values'], 'color' => $this->data['color'] ?? '#00f2fe']];
        }

        $numPoints = count($labels);
        if ($numPoints === 0 && !empty($datasets[0]['values'])) {
            $numPoints = count($datasets[0]['values']);
            for ($i = 0; $i < $numPoints; $i++) {
                $labels[] = (string)($i + 1);
            }
        }

        $paddingLeft = $this->showGrid ? 45 : 10;
        $paddingRight = 15;
        $paddingTop = 20;
        $paddingBottom = $this->showGrid ? 30 : 10;

        $plotW = max(10, $this->width - $paddingLeft - $paddingRight);
        $plotH = max(10, $this->height - $paddingTop - $paddingBottom);
        $baseY = $this->height - $paddingBottom;

        // Compute Min and Max
        $allValues = [];
        foreach ($datasets as $ds) {
            foreach ($ds['values'] ?? [] as $v) {
                if (is_numeric($v)) {
                    $allValues[] = (float)$v;
                }
            }
        }

        $minVal = $this->min ?? (!empty($allValues) ? min($allValues) : 0.0);
        $maxVal = $this->max ?? (!empty($allValues) ? max($allValues) : 100.0);
        if ($maxVal <= $minVal) {
            $maxVal = $minVal + 10.0; // Avoid division by zero
        }
        $range = $maxVal - $minVal;

        $defs = [];
        $gridLines = [];
        $paths = [];
        $dots = [];

        // Generate Grid Lines (4 horizontal divisions)
        if ($this->showGrid) {
            for ($i = 0; $i <= 4; $i++) {
                $y = $baseY - ($i / 4.0) * $plotH;
                $val = $minVal + ($i / 4.0) * $range;
                $gridLines[] = sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" class="oshim-chart__grid" stroke="rgba(255,255,255,0.06)" stroke-dasharray="3,3"/>', $paddingLeft, $y, $this->width - $paddingRight, $y);
                $gridLines[] = sprintf('<text x="%d" y="%.1f" class="oshim-chart__label" text-anchor="end" dominant-baseline="middle" fill="#64748b" font-size="10">%.0f%s</text>', $paddingLeft - 8, $y, $val, $this->escape($this->unit));
            }
            // X-axis labels
            if ($numPoints > 1) {
                $step = $plotW / ($numPoints - 1);
                foreach ($labels as $idx => $lbl) {
                    $x = $paddingLeft + $idx * $step;
                    $gridLines[] = sprintf('<text x="%.1f" y="%d" class="oshim-chart__label" text-anchor="middle" fill="#64748b" font-size="10">%s</text>', $x, $this->height - 8, $this->escape((string)$lbl));
                }
            }
        }

        // Render each dataset
        foreach ($datasets as $dsIdx => $ds) {
            $color = $ds['color'] ?? '#00f2fe';
            $values = $ds['values'] ?? [];
            $fill = $isArea || !empty($ds['fill']);
            $gradId = 'grad_oshim_' . $this->getId() . '_' . $dsIdx;

            if ($fill) {
                $defs[] = sprintf(
                    '<linearGradient id="%s" x1="0" y1="0" x2="0" y2="1"><stop offset="0%%" stop-color="%s" stop-opacity="0.35"/><stop offset="100%%" stop-color="%s" stop-opacity="0.0"/></linearGradient>',
                    $gradId,
                    $color,
                    $color
                );
            }

            if (empty($values)) {
                continue;
            }

            $pts = [];
            $n = count($values);
            for ($i = 0; $i < $n; $i++) {
                $x = $numPoints > 1 ? $paddingLeft + ($i / ($numPoints - 1)) * $plotW : $paddingLeft + $plotW / 2;
                $v = (float)($values[$i] ?? 0.0);
                $y = $baseY - (($v - $minVal) / $range) * $plotH;
                $pts[] = ['x' => $x, 'y' => $y, 'val' => $v];
            }

            // Calculate Bézier Path
            $splinePath = $this->calculateCubicBezierSpline($pts);

            // Area path
            if ($fill && count($pts) > 1) {
                $areaPath = sprintf("M %.1f %.1f L %s L %.1f %.1f Z", $pts[0]['x'], $baseY, substr($splinePath, 2), $pts[count($pts) - 1]['x'], $baseY);
                $paths[] = sprintf('<path d="%s" fill="url(#%s)" class="oshim-chart__area"/>', $areaPath, $gradId);
            }

            // Line path
            $paths[] = sprintf('<path d="%s" fill="none" stroke="%s" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="oshim-chart__line%s"/>', $splinePath, $color, $this->animated ? ' oshim-chart__line--anim' : '');

            // Dots
            if ($this->showDots) {
                foreach ($pts as $pt) {
                    $dots[] = sprintf(
                        '<circle cx="%.1f" cy="%.1f" r="4" fill="%s" stroke="#0b101d" stroke-width="2" class="oshim-chart__dot" data-tooltip="%.1f%s"/>',
                        $pt['x'],
                        $pt['y'],
                        $color,
                        $pt['val'],
                        $this->escape($this->unit)
                    );
                }
            }
        }

        $svg = sprintf('<svg viewBox="0 0 %d %d" class="oshim-chart oshim-chart--spline %s" preserveAspectRatio="xMidYMid meet">', $this->width, $this->height, $this->escape($this->class));
        if (!empty($defs)) {
            $svg .= '<defs>' . implode('', $defs) . '</defs>';
        }
        $svg .= implode('', $gridLines);
        $svg .= implode('', $paths);
        $svg .= implode('', $dots);
        $svg .= '</svg>';

        return '<div class="oshim-chart-container" data-oshim-id="' . $this->getId() . '">' . $svg . '</div>';
    }

    /**
     * Pure PHP Catmull-Rom to Cubic Bézier Spline Math
     */
    public function calculateCubicBezierSpline(array $pts): string
    {
        $n = count($pts);
        if ($n === 0) {
            return 'M 0 0';
        }
        if ($n === 1) {
            return sprintf('M %.1f %.1f', $pts[0]['x'], $pts[0]['y']);
        }
        if ($n === 2) {
            return sprintf('M %.1f %.1f L %.1f %.1f', $pts[0]['x'], $pts[0]['y'], $pts[1]['x'], $pts[1]['y']);
        }

        $path = sprintf('M %.1f %.1f', $pts[0]['x'], $pts[0]['y']);
        $alpha = 0.2; // smoothing factor

        for ($i = 0; $i < $n - 1; $i++) {
            $p0 = $pts[max(0, $i - 1)];
            $p1 = $pts[$i];
            $p2 = $pts[$i + 1];
            $p3 = $pts[min($n - 1, $i + 2)];

            $cp1x = $p1['x'] + ($p2['x'] - $p0['x']) * $alpha;
            $cp1y = $p1['y'] + ($p2['y'] - $p0['y']) * $alpha;

            $cp2x = $p2['x'] - ($p3['x'] - $p1['x']) * $alpha;
            $cp2y = $p2['y'] - ($p3['y'] - $p1['y']) * $alpha;

            $path .= sprintf(' C %.1f %.1f, %.1f %.1f, %.1f %.1f', $cp1x, $cp1y, $cp2x, $cp2y, $p2['x'], $p2['y']);
        }

        return $path;
    }

    /**
     * Pure PHP Multi-Series Bar Chart Renderer (Stacked & Grouped)
     */
    private function renderBarChart(): string
    {
        $labels = $this->data['labels'] ?? [];
        $datasets = $this->data['datasets'] ?? [];

        if (empty($datasets) && isset($this->data['values'])) {
            $datasets = [['label' => 'Series', 'values' => $this->data['values'], 'color' => $this->data['color'] ?? '#00f2fe']];
        }

        $numCategories = max(1, count($labels));
        $numDatasets = max(1, count($datasets));

        $paddingLeft = $this->showGrid ? 45 : 10;
        $paddingRight = 15;
        $paddingTop = 20;
        $paddingBottom = $this->showGrid ? 30 : 10;
        $plotW = max(10, $this->width - $paddingLeft - $paddingRight);
        $plotH = max(10, $this->height - $paddingTop - $paddingBottom);
        $baseY = $this->height - $paddingBottom;

        $catStep = $plotW / $numCategories;
        $groupW = $catStep * 0.7;

        $maxVal = $this->max ?? 100.0;
        if ($this->max === null) {
            if ($this->stacked) {
                $stackSums = array_fill(0, $numCategories, 0.0);
                foreach ($datasets as $ds) {
                    foreach ($ds['values'] ?? [] as $i => $v) {
                        $stackSums[$i] = ($stackSums[$i] ?? 0.0) + (float)$v;
                    }
                }
                $maxVal = max(!empty($stackSums) ? $stackSums : [100.0]) * 1.1;
            } else {
                $vals = [];
                foreach ($datasets as $ds) {
                    foreach ($ds['values'] ?? [] as $v) {
                        $vals[] = (float)$v;
                    }
                }
                $maxVal = max(!empty($vals) ? $vals : [100.0]) * 1.1;
            }
        }
        if ($maxVal <= 0) {
            $maxVal = 100.0;
        }

        $svg = sprintf('<svg viewBox="0 0 %d %d" class="oshim-chart oshim-chart--bar %s">', $this->width, $this->height, $this->escape($this->class));

        // Grid lines
        if ($this->showGrid) {
            for ($i = 0; $i <= 4; $i++) {
                $y = $baseY - ($i / 4.0) * $plotH;
                $val = ($i / 4.0) * $maxVal;
                $svg .= sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" class="oshim-chart__grid" stroke="rgba(255,255,255,0.06)" stroke-dasharray="3,3"/>', $paddingLeft, $y, $this->width - $paddingRight, $y);
                $svg .= sprintf('<text x="%d" y="%.1f" class="oshim-chart__label" text-anchor="end" dominant-baseline="middle" fill="#64748b" font-size="10">%.0f%s</text>', $paddingLeft - 8, $y, $val, $this->escape($this->unit));
            }
        }

        // Category bars
        if ($this->stacked) {
            for ($c = 0; $c < $numCategories; $c++) {
                $barX = $paddingLeft + $c * $catStep + ($catStep - $groupW) / 2;
                $accumY = $baseY;
                foreach ($datasets as $ds) {
                    $v = (float)($ds['values'][$c] ?? 0.0);
                    $h = ($v / $maxVal) * $plotH;
                    $barY = $accumY - $h;
                    $color = $ds['color'] ?? '#00f2fe';
                    $svg .= sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3" fill="%s" class="oshim-chart__bar"/>', $barX, $barY, $groupW, $h, $color);
                    $accumY = $barY;
                }
                if ($this->showGrid && isset($labels[$c])) {
                    $svg .= sprintf('<text x="%.1f" y="%d" class="oshim-chart__label" text-anchor="middle" fill="#64748b" font-size="10">%s</text>', $barX + $groupW / 2, $this->height - 8, $this->escape((string)$labels[$c]));
                }
            }
        } else {
            $barW = $groupW / $numDatasets;
            for ($c = 0; $c < $numCategories; $c++) {
                $catX = $paddingLeft + $c * $catStep + ($catStep - $groupW) / 2;
                foreach ($datasets as $dsIdx => $ds) {
                    $v = (float)($ds['values'][$c] ?? 0.0);
                    $h = max(1.0, ($v / $maxVal) * $plotH);
                    $barX = $catX + $dsIdx * $barW;
                    $barY = $baseY - $h;
                    $color = $ds['color'] ?? '#00f2fe';
                    $svg .= sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="2" fill="%s" class="oshim-chart__bar" data-val="%.1f"/>', $barX + 1, $barY, max(2.0, $barW - 2), $h, $color, $v);
                }
                if ($this->showGrid && isset($labels[$c])) {
                    $svg .= sprintf('<text x="%.1f" y="%d" class="oshim-chart__label" text-anchor="middle" fill="#64748b" font-size="10">%s</text>', $catX + $groupW / 2, $this->height - 8, $this->escape((string)$labels[$c]));
                }
            }
        }

        $svg .= '</svg>';
        return '<div class="oshim-chart-container" data-oshim-id="' . $this->getId() . '">' . $svg . '</div>';
    }

    /**
     * Pure PHP Semi-Circle / Radial Gauge Chart Renderer
     */
    private function renderGaugeChart(): string
    {
        $val = (float)($this->data['value'] ?? ($this->data['datasets'][0]['values'][0] ?? 0.0));
        $min = $this->min ?? 0.0;
        $max = $this->max ?? 100.0;
        $clamped = max($min, min($max, $val));
        $pct = ($max > $min) ? ($clamped - $min) / ($max - $min) : 0.0;

        $cx = $this->width / 2.0;
        $cy = $this->height * 0.75;
        $r = min($this->width * 0.4, $this->height * 0.6);

        // Semi-circle arc track (from 180deg to 0deg)
        $startAngle = M_PI;
        $valAngle = M_PI - ($pct * M_PI);

        $trackStartX = $cx - $r;
        $trackStartY = $cy;
        $trackEndX = $cx + $r;
        $trackEndY = $cy;

        $valX = $cx + $r * cos($valAngle);
        $valY = $cy - $r * sin($valAngle);

        $color = match (true) {
            $pct > 0.9 => '#ff5252',
            $pct > 0.7 => '#ffd600',
            default => '#00f2fe',
        };

        $svg = sprintf('<svg viewBox="0 0 %d %d" class="oshim-chart oshim-chart--gauge %s">', $this->width, $this->height, $this->escape($this->class));

        // Background Arc
        $svg .= sprintf('<path d="M %.1f %.1f A %d %d 0 0 1 %.1f %.1f" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="14" stroke-linecap="round"/>', $trackStartX, $trackStartY, (int)$r, (int)$r, $trackEndX, $trackEndY);

        // Value Arc
        if ($pct > 0.001) {
            $svg .= sprintf('<path d="M %.1f %.1f A %d %d 0 0 1 %.1f %.1f" fill="none" stroke="%s" stroke-width="14" stroke-linecap="round" class="oshim-chart__gauge-arc"/>', $trackStartX, $trackStartY, (int)$r, (int)$r, $valX, $valY, $color);
        }

        // Center Percentage Value & Label
        $svg .= sprintf('<text x="%.1f" y="%.1f" class="oshim-chart__gauge-val" text-anchor="middle" dominant-baseline="middle" fill="#f8fafc" font-size="20" font-weight="bold">%.1f%s</text>', $cx, $cy - 10, $val, $this->escape($this->unit));
        if ($this->title !== null) {
            $svg .= sprintf('<text x="%.1f" y="%.1f" class="oshim-chart__gauge-title" text-anchor="middle" fill="#94a3b8" font-size="12">%s</text>', $cx, $cy + 22, $this->escape($this->title));
        }

        $svg .= '</svg>';
        return '<div class="oshim-chart-container" data-oshim-id="' . $this->getId() . '">' . $svg . '</div>';
    }

    /**
     * Pure PHP Sparkline Renderer
     */
    private function renderSparkline(): string
    {
        $values = $this->data['values'] ?? ($this->data['datasets'][0]['values'] ?? []);
        if (empty($values)) {
            return '<div class="oshim-sparkline" data-oshim-id="' . $this->getId() . '"></div>';
        }

        $w = $this->width;
        $h = $this->height;
        $minVal = min($values);
        $maxVal = max($values);
        $range = ($maxVal > $minVal) ? $maxVal - $minVal : 1.0;
        $n = count($values);

        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $x = ($n > 1) ? ($i / ($n - 1)) * ($w - 4) + 2 : $w / 2;
            $y = $h - 2 - (((float)$values[$i] - $minVal) / $range) * ($h - 6);
            $pts[] = ['x' => $x, 'y' => $y];
        }

        $path = $this->calculateCubicBezierSpline($pts);
        $color = $this->data['color'] ?? '#00f2fe';

        return sprintf(
            '<div class="oshim-sparkline" data-oshim-id="%s"><svg viewBox="0 0 %d %d" class="oshim-sparkline__svg"><path d="%s" fill="none" stroke="%s" stroke-width="1.8"/></svg></div>',
            $this->getId(),
            $w,
            $h,
            $path,
            $color
        );
    }
}
