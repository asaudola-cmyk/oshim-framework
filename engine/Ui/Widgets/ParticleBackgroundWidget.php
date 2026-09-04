<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;

/**
 * ParticleBackgroundWidget: 60 FPS Hardware-Accelerated Cyberpunk Particle Starfield.
 * Ultra-lightweight zero-dependency canvas background with floating glow nodes.
 */
class ParticleBackgroundWidget extends Element
{
    private string $particleColor;
    private int $particleCount;

    public function __construct(string $particleColor = '#00f2fe', int $particleCount = 60)
    {
        parent::__construct('div');
        $this->particleColor = $particleColor;
        $this->particleCount = $particleCount;
        $this->class('oshim-particle-background-wrapper');
    }

    public static function create(string $particleColor = '#00f2fe', int $particleCount = 60): self
    {
        return new self($particleColor, $particleCount);
    }

    public function render(): string
    {
        return <<<HTML
<canvas id="oshim-particle-canvas" class="fixed inset-0 pointer-events-none z-0 opacity-40"></canvas>
<script>
(function() {
    const canvas = document.getElementById('oshim-particle-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let width = canvas.width = window.innerWidth;
    let height = canvas.height = window.innerHeight;

    window.addEventListener('resize', () => {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    });

    const particles = [];
    for (let i = 0; i < {$this->particleCount}; i++) {
        particles.push({
            x: Math.random() * width,
            y: Math.random() * height,
            vx: (Math.random() - 0.5) * 0.8,
            vy: (Math.random() - 0.5) * 0.8,
            radius: Math.random() * 2 + 1,
            alpha: Math.random() * 0.5 + 0.2
        });
    }

    function animate() {
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = '{$this->particleColor}';
        ctx.strokeStyle = '{$this->particleColor}15';

        for (let i = 0; i < particles.length; i++) {
            const p = particles[i];
            p.x += p.vx;
            p.y += p.vy;
            if (p.x < 0) p.x = width;
            if (p.x > width) p.x = 0;
            if (p.y < 0) p.y = height;
            if (p.y > height) p.y = 0;

            ctx.globalAlpha = p.alpha;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fill();

            for (let j = i + 1; j < particles.length; j++) {
                const p2 = particles[j];
                const dx = p.x - p2.x;
                const dy = p.y - p2.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 120) {
                    ctx.globalAlpha = (1 - dist / 120) * 0.2;
                    ctx.beginPath();
                    ctx.moveTo(p.x, p.y);
                    ctx.lineTo(p2.x, p2.y);
                    ctx.stroke();
                }
            }
        }
        requestAnimationFrame(animate);
    }
    animate();
})();
</script>
HTML;
    }
}
