<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Ui\Dsl\Table;
use Oshim\Ui\Dsl\Thead;
use Oshim\Ui\Dsl\Tbody;
use Oshim\Ui\Dsl\Tr;
use Oshim\Ui\Dsl\Th;
use Oshim\Ui\Dsl\Td;
use Oshim\Ui\Widgets\ModalWidget;
use Oshim\Ui\Widgets\ToastWidget;
use Oshim\Ui\Widgets\SidebarWidget;
use Oshim\Ui\Widgets\FormWidget;
use Oshim\Ui\Widgets\FileUploadWidget;
use Oshim\Ui\Widgets\TimelineWidget;

final class AdvancedWidgetsSuiteTest extends TestCase
{
    public function testTableDslRendering(): void
    {
        $table = Table::table()
            ->child(
                Thead::thead()->child(
                    Tr::tr()->child(Th::th('ID'))->child(Th::th('Name'))
                )
            )
            ->child(
                Tbody::tbody()->child(
                    Tr::tr()->child(Td::td('1'))->child(Td::td('Node 01'))
                )
            );

        $html = $table->render();
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<th>ID</th>', $html);
        $this->assertStringContainsString('<td>Node 01</td>', $html);
    }

    public function testModalAndToastWidgets(): void
    {
        $modal = ModalWidget::modal('vps-modal', 'Create MicroVM', '<p>Select resources</p>');
        $modalHtml = $modal->render();
        $this->assertStringContainsString('id="vps-modal"', $modalHtml);
        $this->assertStringContainsString('Create MicroVM', $modalHtml);

        $toast = ToastWidget::success('MicroVM provisioned successfully!');
        $toastHtml = $toast->render();
        $this->assertStringContainsString('MicroVM provisioned successfully!', $toastHtml);
    }

    public function testSidebarAndFormWidgets(): void
    {
        $sidebar = SidebarWidget::sidebar('Enterprise Console', 'vps')
            ->addItem('dashboard', 'Dashboard', '/dashboard', '📊')
            ->addItem('vps', 'MicroVMs', '/vps', '⚡', '3 Active');

        $sidebarHtml = $sidebar->render();
        $this->assertStringContainsString('Enterprise Console', $sidebarHtml);
        $this->assertStringContainsString('MicroVMs', $sidebarHtml);
        $this->assertStringContainsString('3 Active', $sidebarHtml);

        $form = FormWidget::form('/login', 'POST', 'Sign In')
            ->addField('email', 'Email Address', 'email', 'user@oshim.cloud', true);
        $formHtml = $form->render();
        $this->assertStringContainsString('action="/login"', $formHtml);
        $this->assertStringContainsString('Email Address', $formHtml);
        $this->assertStringContainsString('Sign In', $formHtml);

        $timeline = TimelineWidget::timeline()
            ->addEvent('KVM MicroVM Started', '2 mins ago', 'Node booted in 1.8ms');
        $this->assertStringContainsString('KVM MicroVM Started', $timeline->render());

        $uploader = FileUploadWidget::uploader('/upload', 'avatar');
        $this->assertStringContainsString('Drop your file here', $uploader->render());
    }
}
