<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Testing\TestCase;
use Oshim\Ui\Layouts\LandingPageLayout;
use Oshim\Ui\Theme\CyberThemeEngine;
use Oshim\Ui\Widgets\BentoGridWidget;
use Oshim\Ui\Widgets\DrawerWidget;
use Oshim\Ui\Widgets\FloatingDockWidget;
use Oshim\Ui\Widgets\TabsWidget;
use Oshim\Ui\Widgets\WizardWidget;

class NextJsRivalUiTest extends TestCase
{
    public function testBentoGridWidgetRendersSpansAndAccents(): void
    {
        $bento = BentoGridWidget::create();
        $bento->addItem('Async Fiber Runtime', '1.4M RPS Concurrency', '<p>Zero-overhead fibers</p>', 2, 1, '#00f2fe', '⚡', 'CORE');

        $html = $bento->render();

        $this->assertStringContainsString('Async Fiber Runtime', $html);
        $this->assertStringContainsString('md:col-span-2', $html);
        $this->assertStringContainsString('CORE', $html);
        $this->assertStringContainsString('#00f2fe', $html);
    }

    public function testFloatingDockWidgetRendersMagnificationAndShortcuts(): void
    {
        $dock = FloatingDockWidget::create();
        $dock->addItem('Terminal', '💻', '/terminal', '⌘T', 'NEW');

        $html = $dock->render();

        $this->assertStringContainsString('Terminal', $html);
        $this->assertStringContainsString('⌘T', $html);
        $this->assertStringContainsString('NEW', $html);
        $this->assertStringContainsString('hover:scale-125', $html);
    }

    public function testDrawerWidgetRendersPositionsAndBackdrop(): void
    {
        $drawer = DrawerWidget::create(
            'drawer_settings',
            'Cluster Configuration',
            '<p>Config form fields</p>',
            'right',
            '<button>Save</button>'
        );

        $html = $drawer->render();

        $this->assertStringContainsString('drawer_settings', $html);
        $this->assertStringContainsString('Cluster Configuration', $html);
        $this->assertStringContainsString('Config form fields', $html);
        $this->assertStringContainsString('Save', $html);
        $this->assertStringContainsString('translate-x-full', $html);
    }

    public function testTabsWidgetRendersPillNavAndPanels(): void
    {
        $tabs = TabsWidget::create();
        $tabs->addTab('tab_code', 'Source Code', '<pre>echo 1;</pre>', '📄', true);
        $tabs->addTab('tab_preview', 'Live Preview', '<div>Rendered</div>', '👁️', false);

        $html = $tabs->render();

        $this->assertStringContainsString('tab_code', $html);
        $this->assertStringContainsString('Source Code', $html);
        $this->assertStringContainsString('Live Preview', $html);
        $this->assertStringContainsString('oshim-tab-btn', $html);
        $this->assertStringContainsString('oshim-tab-panel', $html);
    }

    public function testWizardWidgetRendersStepsAndProgressNav(): void
    {
        $wiz = WizardWidget::create([], 'Finish Setup', '/setup');
        $wiz->addStep('Profile', 'Enter credentials', '<input name="name"/>');
        $wiz->addStep('Cluster', 'Select datacenter', '<select name="dc"></select>');

        $html = $wiz->render();

        $this->assertStringContainsString('Profile', $html);
        $this->assertStringContainsString('Cluster', $html);
        $this->assertStringContainsString('Finish Setup', $html);
        $this->assertStringContainsString('oshimSwitchStep', $html);
    }

    public function testCyberThemeEngineRendersThemeVariablesAndSwitcher(): void
    {
        $vars = CyberThemeEngine::renderThemeVariables('cyber-neon');
        $this->assertStringContainsString('--oshim-accent', $vars);
        $this->assertStringContainsString('#00f2fe', $vars);

        $switcher = CyberThemeEngine::renderThemeSwitcher();
        $this->assertStringContainsString('oshimSetTheme', $switcher);
        $this->assertStringContainsString('localStorage', $switcher);
    }

    public function testLandingPageLayoutGeneratesFullPageWithNextJsComparisonMatrix(): void
    {
        $page = LandingPageLayout::renderFullPage('OSHIM vs Next.js');

        $this->assertStringContainsString('<!DOCTYPE html>', $page);
        $this->assertStringContainsString('Crush Next.js & React.', $page);
        $this->assertStringContainsString('Next.js vs OSHIM Sovereign Matrix', $page);
        $this->assertStringContainsString('Node.js Runtime Dependency', $page);
        $this->assertStringContainsString('1.4M RPS (Turbo SQPOLL)', $page);
        $this->assertStringContainsString('<style>', $page);
    }
}
