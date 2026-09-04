<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;
use Oshim\Ui\Dsl\Anchor;
use Oshim\Ui\Dsl\Div;
use Oshim\Ui\Dsl\Span;

class NavbarWidget extends Element
{
    public function __construct(string $activeNav = 'home')
    {
        parent::__construct('header');
        $this->class('oshim-top-navbar');

        // Brand logo
        $logoBox = Div::make()
            ->style('width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #00f2fe, #7F00FF); display: flex; align-items: center; justify-content: center; font-weight: 800; color: #070a13; font-size: 1.2rem; box-shadow: 0 0 15px rgba(0, 242, 254, 0.5);')
            ->text('👑');

        $brandText = Span::make()
            ->class('oshim-brand-gradient')
            ->style('font-size: 1.4rem; font-weight: 800; letter-spacing: -0.5px;')
            ->text('OSHIM Framework');

        $brandAnchor = Anchor::link('/', '')
            ->style('text-decoration: none; display: flex; align-items: center; gap: 10px;')
            ->child($logoBox)
            ->child($brandText);

        // Official Framework Navigation Links
        $navLinks = [
            ['id' => 'home', 'label' => 'হোম', 'url' => '/'],
            ['id' => 'docs', 'label' => 'ডকুমেন্টেশন', 'url' => '/docs'],
            ['id' => 'cli', 'label' => 'সিএলআই কমান্ডস (৩৬)', 'url' => '/docs/cli'],
            ['id' => 'benchmarks', 'label' => 'বেঞ্চমার্কস (1.4M+ RPS)', 'url' => '/docs/benchmarks'],
            ['id' => 'plugins', 'label' => 'প্লাগইন ও স্যান্ডবক্স', 'url' => '/docs/plugins'],
            ['id' => 'ai', 'label' => 'AI স্টুডিও', 'url' => '/docs/ai'],
            ['id' => 'canvas', 'label' => 'AI ক্যানভাস', 'url' => '/ai/canvas'],
        ];

        $navContainer = Element::make('nav')->class('oshim-nav-links');
        foreach ($navLinks as $link) {
            $itemClass = ($activeNav === $link['id']) ? 'oshim-nav-item active' : 'oshim-nav-item';
            $navContainer->child(
                Anchor::link($link['url'], $link['label'])->class($itemClass)
            );
        }

        // Action Buttons
        $actions = Div::make()
            ->style('display: flex; gap: 10px; align-items: center;')
            ->child(Anchor::link('/docs', '🚀 শুরু করুন (Get Started)')->class('oshim-btn', 'oshim-btn-primary')->style('padding: 0.5rem 1.2rem; font-size: 0.85rem; font-weight: 700;'));

        $this->child($brandAnchor);
        $this->child($navContainer);
        $this->child($actions);
    }

    public static function makeNavbar(string $active = 'home'): self
    {
        return new self($active);
    }
}
