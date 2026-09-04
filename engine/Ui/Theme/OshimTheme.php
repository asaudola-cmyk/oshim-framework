<?php
declare(strict_types=1);

namespace Oshim\Ui\Theme;

class OshimTheme
{
    public static function getEmbeddedCss(): string
    {
        return <<<CSS
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --oshim-bg: #070a13;
    --oshim-surface: rgba(15, 23, 42, 0.7);
    --oshim-primary: #00f2fe;
    --oshim-secondary: #7F00FF;
    --oshim-accent: #4facfe;
    --oshim-success: #00e676;
    --oshim-danger: #ff5252;
    --oshim-warning: #ffd600;
    --oshim-text: #f8fafc;
    --oshim-text-muted: #94a3b8;
    --oshim-border: rgba(255, 255, 255, 0.1);
    --oshim-radius: 16px;
    --oshim-blur: 16px;
}
body {
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Hind Siliguri", "Kalpurush", "SolaimanLipi", Ubuntu, Cantarell, sans-serif;
    background-color: var(--oshim-bg);
    color: var(--oshim-text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    overflow-x: hidden;
    line-height: 1.6;
}
.oshim-brand-gradient {
    background: linear-gradient(135deg, var(--oshim-primary) 0%, var(--oshim-accent) 50%, var(--oshim-secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.oshim-glow-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: rgba(0, 242, 254, 0.12);
    border: 1px solid rgba(0, 242, 254, 0.35);
    border-radius: 9999px;
    color: var(--oshim-primary);
    font-size: 0.82rem;
    font-weight: 700;
    box-shadow: 0 0 20px rgba(0, 242, 254, 0.25);
}
.oshim-pulse-dot {
    width: 8px;
    height: 8px;
    background-color: var(--oshim-success);
    border-radius: 50%;
    box-shadow: 0 0 10px var(--oshim-success);
    animation: oshim-pulse 1.8s infinite;
}
@keyframes oshim-pulse {
    0% { transform: scale(0.95); opacity: 0.8; }
    50% { transform: scale(1.3); opacity: 1; box-shadow: 0 0 16px var(--oshim-success); }
    100% { transform: scale(0.95); opacity: 0.8; }
}
.oshim-top-navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 2rem;
    background: rgba(10, 15, 28, 0.92);
    backdrop-filter: blur(var(--oshim-blur));
    border-bottom: 1px solid var(--oshim-border);
    position: sticky;
    top: 0;
    z-index: 200;
}
.oshim-nav-links {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}
.oshim-nav-item {
    color: var(--oshim-text-muted);
    text-decoration: none;
    padding: 0.5rem 0.9rem;
    border-radius: 10px;
    font-size: 0.88rem;
    font-weight: 600;
    transition: all 0.2s ease;
}
.oshim-nav-item:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.08);
}
.oshim-nav-item.active {
    color: var(--oshim-primary);
    background: rgba(0, 242, 254, 0.12);
    border: 1px solid rgba(0, 242, 254, 0.3);
}
.oshim-hero-section {
    padding: 4.5rem 1.5rem 3rem 1.5rem;
    text-align: center;
    position: relative;
    background: radial-gradient(circle at 50% 15%, rgba(0, 242, 254, 0.18) 0%, rgba(7, 10, 19, 0) 65%);
}
.oshim-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1.5rem;
}
.oshim-glass-card {
    background: var(--oshim-surface);
    backdrop-filter: blur(var(--oshim-blur));
    border: 1px solid var(--oshim-border);
    border-radius: var(--oshim-radius);
    padding: 1.75rem;
    transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
    position: relative;
    overflow: hidden;
}
.oshim-glass-card:hover {
    transform: translateY(-3px);
    border-color: rgba(0, 242, 254, 0.35);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6), 0 0 20px rgba(0, 242, 254, 0.15);
}
.oshim-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0.75rem 1.4rem;
    font-size: 0.9rem;
    font-weight: 700;
    border-radius: 12px;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
}
.oshim-btn-primary {
    background: linear-gradient(135deg, #00f2fe 0%, #0284c7 100%);
    color: #030712 !important;
    box-shadow: 0 4px 18px rgba(0, 242, 254, 0.4);
}
.oshim-btn-primary:hover {
    box-shadow: 0 6px 25px rgba(0, 242, 254, 0.6);
    transform: translateY(-2px);
}
.oshim-btn-secondary {
    background: rgba(30, 41, 59, 0.8);
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.15);
}
.oshim-btn-secondary:hover {
    background: rgba(51, 65, 85, 0.9);
    border-color: rgba(0, 242, 254, 0.4);
    transform: translateY(-2px);
}
.oshim-btn-purple {
    background: linear-gradient(135deg, #9333ea 0%, #6366f1 100%);
    color: #ffffff !important;
    box-shadow: 0 4px 18px rgba(147, 51, 234, 0.4);
}
.oshim-btn-purple:hover {
    box-shadow: 0 6px 25px rgba(147, 51, 234, 0.6);
    transform: translateY(-2px);
}
.oshim-code-box {
    background: #030712;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 14px;
    padding: 1.25rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.85rem;
    color: #e2e8f0;
    line-height: 1.6;
}
.oshim-footer {
    margin-top: auto;
    padding: 2.5rem 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(8, 12, 22, 0.95);
    text-align: center;
    color: var(--oshim-text-muted);
    font-size: 0.88rem;
}
CSS;
    }
}
