<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Testing\TestCase;
use Oshim\Ui\Dsl\Style;
use Oshim\Ui\Dsl\Element;
use Oshim\Ui\Dsl\Div;
use Oshim\Ui\Dsl\Section;
use Oshim\Ui\Dsl\Heading;
use Oshim\Ui\Dsl\Button;
use Oshim\Ui\Dsl\Form;
use Oshim\Ui\Dsl\Input;
use Oshim\Ui\Dsl\Select;
use Oshim\Ui\Dsl\Badge;
use Oshim\Ui\Dsl\Grid;
use Oshim\Ui\Dsl\Document;
use Oshim\Ui\Theme\OshimTheme;
use Oshim\Ui\Widgets\GlassCard;
use Oshim\Ui\Widgets\MetricWidget;
use Oshim\Ui\Widgets\NavbarWidget;
use Oshim\Ui\Widgets\FooterWidget;

class PurePhpDslTest extends TestCase
{
    public function testFluentStyleBuilder(): void
    {
        $style = Style::make()
            ->bg('#070a13')
            ->color('#f8fafc')
            ->padding('1.5rem')
            ->display('flex')
            ->border('1px solid #fff');

        $css = $style->render();
        $this->assertStringContainsString('background: #070a13;', $css);
        $this->assertStringContainsString('color: #f8fafc;', $css);
        $this->assertStringContainsString('padding: 1.5rem;', $css);
        $this->assertStringContainsString('display: flex;', $css);
    }

    public function testProgrammaticHtmlElements(): void
    {
        $div = Div::make()
            ->id('main-card')
            ->class('oshim-glass-card', 'active')
            ->child(Heading::h1('OSHIM Sovereign Cloud'))
            ->child(Badge::make('ONLINE', '#00e676'));

        $html = $div->render();
        $this->assertStringContainsString('<div id="main-card" class="oshim-glass-card active"', $html);
        $this->assertStringContainsString('<h1>OSHIM Sovereign Cloud</h1>', $html);
        $this->assertStringContainsString('ONLINE', $html);

        $form = Form::post('/cart/add')
            ->child(Input::hidden('plan', 'vps-kvm'))
            ->child(Input::textInput('domain', 'example.com'))
            ->child(Button::submit('অর্ডার করুন'));

        $formHtml = $form->render();
        $this->assertStringContainsString('<form method="POST" action="/cart/add">', $formHtml);
        $this->assertStringContainsString('<input type="hidden" name="plan" value="vps-kvm" />', $formHtml);
        $this->assertStringContainsString('<button type="submit">অর্ডার করুন</button>', $formHtml);
    }

    public function testOshimThemeEmbeddedCss(): void
    {
        $css = OshimTheme::getEmbeddedCss();
        $this->assertStringContainsString('--oshim-bg: #070a13;', $css);
        $this->assertStringContainsString('--oshim-primary: #00f2fe;', $css);
        $this->assertStringContainsString('.oshim-glass-card', $css);
    }

    public function testDocumentBuilderAndWidgets(): void
    {
        $doc = Document::make('টেস্ট ড্যাশবোর্ড')
            ->navbar(NavbarWidget::makeNavbar('vps'))
            ->body([
                MetricWidget::makeMetric('মোট নোড', 8, '#00f2fe'),
                GlassCard::widget('ক্লাউড VPS')->child(Div::make()->text('4 Cores, 8GB RAM')),
            ])
            ->footer(FooterWidget::makeFooter());

        $html = $doc->render();
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>টেস্ট ড্যাশবোর্ড</title>', $html);
        $this->assertStringContainsString('OSHIM Framework', $html);
        $this->assertStringContainsString('মোট নোড', $html);
        $this->assertStringContainsString('ক্লাউড VPS', $html);
        $this->assertStringContainsString('4 Cores, 8GB RAM', $html);
    }
}
