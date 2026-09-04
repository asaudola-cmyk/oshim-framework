<?php
declare(strict_types=1);

namespace Oshim\Ui\Canvas3D\Math;

/**
 * Color representation for WebGL & Canvas3D materials, lights, and shaders.
 */
class Color
{
    public function __construct(
        public float $r = 1.0,
        public float $g = 1.0,
        public float $b = 1.0,
        public float $a = 1.0
    ) {
        $this->clamp();
    }

    public static function create(float $r = 1.0, float $g = 1.0, float $b = 1.0, float $a = 1.0): self
    {
        return new self($r, $g, $b, $a);
    }

    public static function fromHex(string $hex): self
    {
        $clean = ltrim($hex, '#');
        $len = strlen($clean);

        if ($len === 3) {
            $r = hexdec(str_repeat($clean[0], 2)) / 255.0;
            $g = hexdec(str_repeat($clean[1], 2)) / 255.0;
            $b = hexdec(str_repeat($clean[2], 2)) / 255.0;
            return new self((float)$r, (float)$g, (float)$b, 1.0);
        }

        if ($len === 4) {
            $r = hexdec(str_repeat($clean[0], 2)) / 255.0;
            $g = hexdec(str_repeat($clean[1], 2)) / 255.0;
            $b = hexdec(str_repeat($clean[2], 2)) / 255.0;
            $a = hexdec(str_repeat($clean[3], 2)) / 255.0;
            return new self((float)$r, (float)$g, (float)$b, (float)$a);
        }

        if ($len === 6) {
            $r = hexdec(substr($clean, 0, 2)) / 255.0;
            $g = hexdec(substr($clean, 2, 2)) / 255.0;
            $b = hexdec(substr($clean, 4, 2)) / 255.0;
            return new self((float)$r, (float)$g, (float)$b, 1.0);
        }

        if ($len === 8) {
            $r = hexdec(substr($clean, 0, 2)) / 255.0;
            $g = hexdec(substr($clean, 2, 2)) / 255.0;
            $b = hexdec(substr($clean, 4, 2)) / 255.0;
            $a = hexdec(substr($clean, 6, 2)) / 255.0;
            return new self((float)$r, (float)$g, (float)$b, (float)$a);
        }

        return new self(1.0, 1.0, 1.0, 1.0);
    }

    public static function fromRgb(int $r, int $g, int $b, float $a = 1.0): self
    {
        return new self($r / 255.0, $g / 255.0, $b / 255.0, $a);
    }

    public static function fromHsl(float $h, float $s, float $l, float $a = 1.0): self
    {
        // h: [0, 360], s: [0, 1], l: [0, 1]
        $h = fmod($h, 360.0);
        if ($h < 0) {
            $h += 360.0;
        }
        $c = (1.0 - abs((2.0 * $l) - 1.0)) * $s;
        $x = $c * (1.0 - abs(fmod($h / 60.0, 2.0) - 1.0));
        $m = $l - ($c / 2.0);

        if ($h < 60) {
            [$r, $g, $b] = [$c, $x, 0.0];
        } elseif ($h < 120) {
            [$r, $g, $b] = [$x, $c, 0.0];
        } elseif ($h < 180) {
            [$r, $g, $b] = [0.0, $c, $x];
        } elseif ($h < 240) {
            [$r, $g, $b] = [0.0, $x, $c];
        } elseif ($h < 300) {
            [$r, $g, $b] = [$x, 0.0, $c];
        } else {
            [$r, $g, $b] = [$c, 0.0, $x];
        }

        return new self($r + $m, $g + $m, $b + $m, $a);
    }

    private function clamp(): void
    {
        $this->r = max(0.0, min(1.0, $this->r));
        $this->g = max(0.0, min(1.0, $this->g));
        $this->b = max(0.0, min(1.0, $this->b));
        $this->a = max(0.0, min(1.0, $this->a));
    }

    public function toHex(bool $includeAlpha = false): string
    {
        $rInt = (int) round($this->r * 255.0);
        $gInt = (int) round($this->g * 255.0);
        $bInt = (int) round($this->b * 255.0);
        if ($includeAlpha) {
            $aInt = (int) round($this->a * 255.0);
            return sprintf('#%02x%02x%02x%02x', $rInt, $gInt, $bInt, $aInt);
        }
        return sprintf('#%02x%02x%02x', $rInt, $gInt, $bInt);
    }

    public function toHexInt(): int
    {
        $rInt = (int) round($this->r * 255.0);
        $gInt = (int) round($this->g * 255.0);
        $bInt = (int) round($this->b * 255.0);
        return ($rInt << 16) | ($gInt << 8) | $bInt;
    }

    public function toRgbString(): string
    {
        return sprintf('rgb(%d, %d, %d)', (int) round($this->r * 255), (int) round($this->g * 255), (int) round($this->b * 255));
    }

    public function toRgbaString(): string
    {
        return sprintf('rgba(%d, %d, %d, %.3f)', (int) round($this->r * 255), (int) round($this->g * 255), (int) round($this->b * 255), $this->a);
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function toArray(): array
    {
        return [$this->r, $this->g, $this->b, $this->a];
    }

    public function toGlsl(): string
    {
        return sprintf('vec4(%.4f, %.4f, %.4f, %.4f)', $this->r, $this->g, $this->b, $this->a);
    }

    public function lerp(self $target, float $alpha): self
    {
        $t = max(0.0, min(1.0, $alpha));
        return new self(
            $this->r + (($target->r - $this->r) * $t),
            $this->g + (($target->g - $this->g) * $t),
            $this->b + (($target->b - $this->b) * $t),
            $this->a + (($target->a - $this->a) * $t)
        );
    }

    public function clone(): self
    {
        return new self($this->r, $this->g, $this->b, $this->a);
    }
}
