<?php
declare(strict_types=1);

namespace Oshim\Ui\Widgets;

use Oshim\Ui\Dsl\Element;
use Oshim\Ui\Dsl\Div;
use Oshim\Ui\Dsl\Span;

class FooterWidget extends Element
{
    public function __construct()
    {
        parent::__construct('footer');
        $this->class('oshim-footer');

        $topRow = Div::make()
            ->class('oshim-container')
            ->style('display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;');

        $left = Div::make()
            ->style('text-align: left;')
            ->child(Div::make()->class('oshim-brand-gradient')->style('font-weight: 700; font-size: 1.1rem;')->text('OSHIM Sovereign Framework'))
            ->child(Div::make()->style('margin-top: 4px; color: #64748b; font-size: 0.85rem;')->text('The Zero-Dependency Universal Meta-Framework for PHP 8.3+ • 100% Sovereign'));

        $badges = Div::make()
            ->style('display: flex; gap: 1.5rem; font-size: 0.85rem; color: #94a3b8;')
            ->child(Span::make()->text('⚡ 1.4M+ RPS Throughput'))
            ->child(Span::make()->text('🛡️ Zero Dependencies'))
            ->child(Span::make()->text('🎨 Pure PHP Tailwind JIT'))
            ->child(Span::make()->text('🤖 Native AI & RAG'));

        $topRow->child($left);
        $topRow->child($badges);

        $copy = Div::make()
            ->style('margin-top: 1.5rem; font-size: 0.8rem; color: #475569;')
            ->text('© 2026 OSHIM Sovereign Framework. Sovereign Open Software. Built 100% in Bangladesh for the World 🇧🇩.');

        $this->child($topRow);
        $this->child($copy);
    }

    public static function makeFooter(): static
    {
        return new static();
    }
}
