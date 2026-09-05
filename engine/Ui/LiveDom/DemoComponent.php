<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

use Oshim\Ui\Dsl\Element;
use Oshim\Ui\Dsl\Div;
use Oshim\Ui\Dsl\H1;
use Oshim\Ui\Dsl\Button;
use Oshim\Ui\Dsl\Signal;

class DemoComponent extends Component
{
    public Signal $count;

    public function mount(): void
    {
        // Reactive State (Signal) Initialization
        $this->count = Signal::create(0);
    }

    public function increment(): void
    {
        $this->count->set($this->count->get() + 1);
    }

    public function render(): Element
    {
        // 🚀 THE ULTIMATE UI ENGINE (Tailwind Fluent API + Signals)
        return Div::make()
            ->p(6)
            ->bg('gray-900')
            ->textWhite()
            ->rounded('lg')
            ->shadow('xl')
            ->children([
                H1::make()->text("OSHIM Fluent UI")->text2xl()->fontBold()->mb(4),
                
                Div::make()
                    ->flex()
                    ->itemsCenter()
                    ->spaceX(4)
                    ->children([
                        // Reactive text automatically binds to the Signal
                        Div::make()->text("Live Count: ")->child($this->count),
                        
                        Button::make('Increment (+)')
                            ->onClick('increment')
                            ->bg('blue-600')
                            ->px(4)
                            ->py(2)
                            ->rounded()
                            ->hoverBg('blue-500')
                            ->transition()
                    ])
            ]);
    }
}
