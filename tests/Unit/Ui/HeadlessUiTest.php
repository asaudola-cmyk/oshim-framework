<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Testing\TestCase;
use Oshim\Ui\Headless\Dialog;
use Oshim\Ui\Headless\DropdownMenu;
use Oshim\Ui\Headless\Combobox;
use Oshim\Ui\Headless\Popover;
use Oshim\Ui\Headless\Accordion;
use Oshim\Ui\Headless\HeadlessRuntime;
use Oshim\Ui\Headless\Support\Aria;
use Oshim\Ui\Headless\Support\FocusManager;
use Oshim\Ui\Headless\Support\KeyboardNavigation;

class HeadlessUiTest extends TestCase
{
    public function testDialogRendersWaiAriaRolesAndStates(): void
    {
        $dialog = Dialog::make('user_modal')
            ->trigger('Edit Profile', ['class' => 'btn-primary'])
            ->overlay(['class' => 'backdrop-blur'])
            ->title('Profile Settings')
            ->description('Update your sovereign credentials.')
            ->content('<div>Profile Form Controls</div>')
            ->closeButton('Cancel', ['class' => 'btn-ghost']);

        // Test closed state
        $htmlClosed = $dialog->render();

        $this->assertStringContainsString('data-headless="dialog"', $htmlClosed);
        $this->assertStringContainsString('data-state="closed"', $htmlClosed);
        $this->assertStringContainsString('id="user_modal-trigger"', $htmlClosed);
        $this->assertStringContainsString('aria-haspopup="dialog"', $htmlClosed);
        $this->assertStringContainsString('aria-expanded="false"', $htmlClosed);
        $this->assertStringContainsString('aria-controls="user_modal-content"', $htmlClosed);
        $this->assertStringContainsString('role="dialog"', $htmlClosed);
        $this->assertStringContainsString('aria-modal="true"', $htmlClosed);
        $this->assertStringContainsString('aria-labelledby="user_modal-title"', $htmlClosed);
        $this->assertStringContainsString('aria-describedby="user_modal-desc"', $htmlClosed);
        $this->assertStringContainsString('aria-label="Close"', $htmlClosed);
        $this->assertStringContainsString('hidden', $htmlClosed);

        // Test open state
        $dialog->open(true);
        $htmlOpen = $dialog->render();

        $this->assertStringContainsString('aria-expanded="true"', $htmlOpen);
        $this->assertStringContainsString('data-state="open"', $htmlOpen);
        $this->assertFalse(str_contains($htmlOpen, 'id="user_modal-content" role="dialog" aria-modal="true" tabindex="-1" data-state="open" data-headless-content="user_modal" data-headless-focus-trap="true" data-headless-restore-focus="true" data-headless-keyboard="{&quot;Escape&quot;:&quot;close&quot;}" hidden'));
    }

    public function testAlertDialogRendersAlertRoleAndSemantics(): void
    {
        $alert = Dialog::make('confirm_dialog')
            ->alert(true)
            ->open(true)
            ->title('Delete Account?')
            ->description('This action cannot be undone.')
            ->closeButton('Confirm Delete');

        $this->assertTrue($alert->isAlert());
        $html = $alert->render();

        $this->assertStringContainsString('role="alertdialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('Delete Account?', $html);
    }

    public function testDialogFocusManagerAndKeyboardTrap(): void
    {
        $dialog = Dialog::make('security_modal')
            ->open(true);

        $dialog->getFocusManager()
            ->trap(true)
            ->initialFocus('#token_input')
            ->restoreFocus(true, '#modal_open_btn');

        $html = $dialog->render();

        $this->assertStringContainsString('data-headless-focus-trap="true"', $html);
        $this->assertStringContainsString('data-headless-initial-focus="#token_input"', $html);
        $this->assertStringContainsString('data-headless-restore-focus="#modal_open_btn"', $html);
        $this->assertStringContainsString('data-headless-keyboard="{&quot;Escape&quot;:&quot;close&quot;}"', $html);
    }

    public function testDropdownMenuRendersWaiAriaRolesAndRovingTabindex(): void
    {
        $menu = DropdownMenu::make('options_menu')
            ->trigger('Actions')
            ->item('edit', 'Edit Record')
            ->item('duplicate', 'Duplicate Record')
            ->item('delete', 'Delete Record', [], true) // disabled
            ->activeIndex(0);

        $html = $menu->render();

        $this->assertStringContainsString('data-headless="dropdown-menu"', $html);
        $this->assertStringContainsString('aria-haspopup="menu"', $html);
        $this->assertStringContainsString('role="menu"', $html);
        $this->assertStringContainsString('aria-orientation="vertical"', $html);
        $this->assertStringContainsString('role="menuitem"', $html);

        // Active item has roving tabindex="0" and highlighted="true"
        $this->assertStringContainsString('id="options_menu-item-edit" tabindex="0" data-highlighted="true" data-disabled="false"', $html);
        // Second item has tabindex="-1" and highlighted="false"
        $this->assertStringContainsString('id="options_menu-item-duplicate" tabindex="-1" data-highlighted="false"', $html);
        // Disabled item has aria-disabled="true"
        $this->assertStringContainsString('id="options_menu-item-delete" tabindex="-1" data-highlighted="false" data-disabled="true" aria-disabled="true"', $html);

        // Keyboard navigation bindings
        $this->assertStringContainsString('data-headless-keyboard', $html);
        $this->assertStringContainsString('ArrowDown', $html);
        $this->assertStringContainsString('ArrowUp', $html);
        $this->assertStringContainsString('Home', $html);
        $this->assertStringContainsString('End', $html);
    }

    public function testDropdownMenuCheckboxAndRadioItems(): void
    {
        $menu = DropdownMenu::make('view_menu')
            ->trigger('View Options')
            ->label('Layout')
            ->radioGroup('theme', 'dark', [
                ['id' => 'theme_light', 'value' => 'light', 'label' => 'Light Mode'],
                ['id' => 'theme_dark',  'value' => 'dark',  'label' => 'Dark Mode'],
            ])
            ->separator()
            ->label('Display')
            ->checkboxItem('show_grid', 'Show Gridlines', true)
            ->checkboxItem('show_mini', 'Show Minimap', false);

        $html = $menu->render();

        // Label
        $this->assertStringContainsString('role="none">Layout</div>', $html);

        // Radio items
        $this->assertStringContainsString('role="menuitemradio"', $html);
        $this->assertStringContainsString('aria-checked="false" data-radio-group="theme" data-value="light"', $html);
        $this->assertStringContainsString('aria-checked="true" data-radio-group="theme" data-value="dark"', $html);

        // Separator
        $this->assertStringContainsString('role="separator" aria-orientation="horizontal"', $html);

        // Checkbox items
        $this->assertStringContainsString('role="menuitemcheckbox"', $html);
        $this->assertStringContainsString('id="view_menu-item-show_grid"', $html);
        $this->assertStringContainsString('aria-checked="true"', $html);
        $this->assertStringContainsString('id="view_menu-item-show_mini"', $html);
        $this->assertStringContainsString('aria-checked="false"', $html);
    }

    public function testComboboxRendersInputAndListboxLinkage(): void
    {
        $cb = Combobox::make('country_select')
            ->input('Select country...', 'US')
            ->trigger('▼')
            ->option('US', 'United States')
            ->option('BD', 'Bangladesh')
            ->option('JP', 'Japan')
            ->selected('BD')
            ->activeDescendant('country_select-opt-BD');

        $html = $cb->render();

        $this->assertStringContainsString('data-headless="combobox"', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('aria-autocomplete="list"', $html);
        $this->assertStringContainsString('aria-haspopup="listbox"', $html);
        $this->assertStringContainsString('aria-controls="country_select-listbox"', $html);
        $this->assertStringContainsString('aria-activedescendant="country_select-opt-BD"', $html);

        // Listbox
        $this->assertStringContainsString('role="listbox"', $html);
        $this->assertStringContainsString('id="country_select-listbox"', $html);
        $this->assertStringContainsString('aria-labelledby="country_select-input"', $html);

        // Options
        $this->assertStringContainsString('role="option"', $html);
        $this->assertStringContainsString('id="country_select-opt-BD"', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
        $this->assertStringContainsString('data-highlighted="true"', $html);
        $this->assertStringContainsString('aria-selected="false"', $html);
    }

    public function testComboboxFilteringAndGrouping(): void
    {
        $cb = Combobox::make('tech_stack')
            ->group('Backend', [
                'php'  => 'PHP 8.4',
                'rust' => 'Rust Lang',
            ])
            ->group('Frontend', [
                'html' => 'HTML5 / CSS3',
                'webgl'=> 'WebGL Canvas',
            ])
            ->query('rust'); // Live search filter

        $html = $cb->render();

        $this->assertStringContainsString('Rust Lang', $html);
        $this->assertFalse(str_contains($html, 'PHP 8.4'));
        $this->assertFalse(str_contains($html, 'HTML5 / CSS3'));
        $this->assertStringContainsString('role="group"', $html);

        // When query matches nothing, renders empty state
        $cb->query('nonexistent_framework');
        $emptyHtml = $cb->render();
        $this->assertStringContainsString('data-headless-empty="true"', $emptyHtml);
        $this->assertStringContainsString('No results found.', $emptyHtml);
    }

    public function testPopoverRendersPositioningAndDisclosureSemantics(): void
    {
        $pop = Popover::make('settings_popover')
            ->trigger('Settings')
            ->side('top')
            ->align('end')
            ->offset(8, 12)
            ->arrow(true)
            ->content('<p>Contextual Popover Content</p>')
            ->closeButton('Dismiss');

        $this->assertSame('top', $pop->getSide());
        $this->assertSame('end', $pop->getAlign());

        $html = $pop->render();

        $this->assertStringContainsString('data-headless="popover"', $html);
        $this->assertStringContainsString('id="settings_popover-trigger"', $html);
        $this->assertStringContainsString('aria-haspopup="dialog"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('data-side="top"', $html);
        $this->assertStringContainsString('data-align="end"', $html);
        $this->assertStringContainsString('data-side-offset="8"', $html);
        $this->assertStringContainsString('data-align-offset="12"', $html);
        $this->assertStringContainsString('data-headless-dismiss-outside="true"', $html);
        $this->assertStringContainsString('data-headless-arrow="true"', $html);
        $this->assertStringContainsString('data-headless-close="settings_popover"', $html);
    }

    public function testAccordionRendersHeadersRegionsAndModes(): void
    {
        $acc = Accordion::make('faq_accordion')
            ->type('single')
            ->collapsible(true)
            ->headerLevel(3)
            ->item('q1', 'What is Oshim Sovereign Framework?', '<p>A zero-dependency high-performance PHP framework.</p>')
            ->item('q2', 'Is it fully WAI-ARIA accessible?', '<p>Yes, all Headless primitives strictly adhere to W3C APG.</p>', false)
            ->item('q3', 'Can items be disabled?', '<p>Disabled panel content.</p>', true)
            ->value('q1'); // q1 is initially expanded

        $this->assertTrue($acc->isExpanded('q1'));
        $this->assertFalse($acc->isExpanded('q2'));

        $html = $acc->render();

        $this->assertStringContainsString('data-headless="accordion"', $html);
        $this->assertStringContainsString('data-type="single"', $html);
        $this->assertStringContainsString('data-collapsible="true"', $html);

        // Item 1: Open
        $this->assertStringContainsString('<h3 role="heading" aria-level="3">', $html);
        $this->assertStringContainsString('id="faq_accordion-trigger-q1"', $html);
        $this->assertStringContainsString('aria-expanded="true"', $html);
        $this->assertStringContainsString('aria-controls="faq_accordion-content-q1"', $html);
        $this->assertStringContainsString('role="region" aria-labelledby="faq_accordion-trigger-q1" data-state="open"', $html);
        $this->assertStringContainsString('A zero-dependency high-performance PHP framework.', $html);

        // Item 2: Closed (hidden)
        $this->assertStringContainsString('id="faq_accordion-trigger-q2"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('hidden', $html);

        // Item 3: Disabled
        $this->assertStringContainsString('data-disabled="true"', $html);
        $this->assertStringContainsString('disabled aria-disabled="true"', $html);

        // Multiple expansion mode
        $acc->type('multiple');
        $acc->value(['q1', 'q2']);
        $this->assertSame('multiple', $acc->getType());
        $multiHtml = $acc->render();
        $this->assertStringContainsString('data-type="multiple"', $multiHtml);
        $this->assertStringContainsString('id="faq_accordion-trigger-q1" type="button" aria-expanded="true"', $multiHtml);
        $this->assertStringContainsString('id="faq_accordion-trigger-q2" type="button" aria-expanded="true"', $multiHtml);
    }

    public function testSupportAriaAndFocusManager(): void
    {
        // Aria compiler
        $compiled = Aria::compile([
            'id'            => 'sample_id',
            'aria-expanded' => true,
            'data-active'   => true,
            'disabled'      => true,
            'hidden'        => false, // should be omitted
            'data-empty'    => null,  // should be omitted
        ]);

        $this->assertStringContainsString('id="sample_id"', $compiled);
        $this->assertStringContainsString('aria-expanded="true"', $compiled);
        $this->assertStringContainsString('data-active="true"', $compiled);
        $this->assertStringContainsString('disabled', $compiled);
        $this->assertFalse(str_contains($compiled, 'hidden'));
        $this->assertFalse(str_contains($compiled, 'data-empty'));

        // FocusManager
        $fm = FocusManager::make()
            ->trap(true)
            ->rovingTabindex(true, 1)
            ->initialFocus('#first_field');

        $this->assertTrue($fm->isTrapped());
        $this->assertTrue($fm->isRovingTabindex());
        $this->assertSame(-1, $fm->getItemTabindex(0));
        $this->assertSame(0, $fm->getItemTabindex(1));
        $this->assertSame(-1, $fm->getItemTabindex(2));

        $attrs = $fm->toAttributes();
        $this->assertSame('true', $attrs['data-headless-focus-trap']);
        $this->assertSame('#first_field', $attrs['data-headless-initial-focus']);
        $this->assertSame('true', $attrs['data-headless-roving-tabindex']);
        $this->assertSame('1', $attrs['data-headless-active-index']);

        // KeyboardNavigation
        $kb = KeyboardNavigation::forDropdownMenu();
        $bindings = $kb->getBindings();
        $this->assertSame(KeyboardNavigation::ACTION_NEXT, $bindings['ArrowDown']);
        $this->assertSame(KeyboardNavigation::ACTION_PREV, $bindings['ArrowUp']);
        $this->assertSame(KeyboardNavigation::ACTION_CLOSE, $bindings['Escape']);
    }

    public function testHeadlessRuntimeOutput(): void
    {
        $script = HeadlessRuntime::script();
        $js = HeadlessRuntime::js();

        $this->assertStringStartsWith('<script>', $script);
        $this->assertStringEndsWith('</script>', $script);

        $this->assertStringContainsString('OSHIM Headless UI Client Runtime', $js);
        $this->assertStringContainsString('openDialog', $js);
        $this->assertStringContainsString('openMenu', $js);
        $this->assertStringContainsString('openCombobox', $js);
        $this->assertStringContainsString('openPopover', $js);
        $this->assertStringContainsString('toggleAccordionItem', $js);
        $this->assertStringContainsString('ArrowDown', $js);
        $this->assertStringContainsString('ArrowUp', $js);
        $this->assertStringContainsString('Escape', $js);
    }

    public function testCompoundSubcomponentPartRendering(): void
    {
        $dialog = Dialog::make('part_dialog')
            ->trigger('Open Part Dialog', ['class' => 'my-trigger-btn'])
            ->title('Part Dialog Title')
            ->description('Part Dialog Description')
            ->closeButton('Dismiss Part', ['class' => 'my-close-btn'])
            ->content('<span>Custom Inner Content</span>')
            ->open(true);

        $triggerHtml = $dialog->renderTrigger();
        $overlayHtml = $dialog->renderOverlay();
        $contentHtml = $dialog->renderContent();
        $titleHtml = $dialog->renderTitle();
        $descHtml = $dialog->renderDescription();
        $closeHtml = $dialog->renderClose();

        $this->assertStringContainsString('my-trigger-btn', $triggerHtml);
        $this->assertStringContainsString('aria-haspopup="dialog"', $triggerHtml);
        $this->assertStringContainsString('id="part_dialog-overlay"', $overlayHtml);
        $this->assertStringContainsString('role="dialog"', $contentHtml);
        $this->assertStringContainsString('Part Dialog Title', $titleHtml);
        $this->assertStringContainsString('Part Dialog Description', $descHtml);
        $this->assertStringContainsString('my-close-btn', $closeHtml);
        $this->assertStringContainsString('aria-label="Close"', $closeHtml);
    }

    public function testStringableInterfaceAndCustomAttributes(): void
    {
        $popover = Popover::make('custom_pop')
            ->class('shadow-2xl', 'rounded-lg')
            ->attr('data-analytics', 'popover_opened')
            ->trigger('Help')
            ->content('Help details');

        // Cast to string tests __toString()
        $str = (string)$popover;

        $this->assertStringContainsString('class="shadow-2xl rounded-lg"', $str);
        $this->assertStringContainsString('data-analytics="popover_opened"', $str);
        $this->assertStringContainsString('data-headless="popover"', $str);
    }

    public function testFocusManagerAndKeyboardNavigationEdgeCases(): void
    {
        $fm = FocusManager::make(true)
            ->restoreFocus(false)
            ->loop(false);

        $this->assertFalse($fm->shouldRestoreFocus());
        $this->assertFalse($fm->shouldLoop());
        $attrs = $fm->toAttributes();
        $this->assertSame('false', $attrs['data-headless-restore-focus']);
        $this->assertSame('false', $attrs['data-headless-focus-loop']);

        $kb = KeyboardNavigation::make()
            ->bind('Ctrl+k', 'search')
            ->bind('Escape', 'close');

        $this->assertSame('search', $kb->getBindings()['Ctrl+k']);
        $json = $kb->toJson();
        $this->assertStringContainsString('"Ctrl+k":"search"', $json);

        // Accordion keyboard contract
        $accKb = KeyboardNavigation::forAccordion();
        $this->assertSame(KeyboardNavigation::ACTION_TOGGLE, $accKb->getBindings()['Enter']);
        $this->assertSame(KeyboardNavigation::ACTION_TOGGLE, $accKb->getBindings()[' ']);
    }

    public function testComboboxAutocompleteVariants(): void
    {
        $cb = Combobox::make('auto_cb')
            ->autocomplete('both');

        $html = $cb->render();
        $this->assertStringContainsString('aria-autocomplete="both"', $html);

        $cb->autocomplete('inline');
        $this->assertStringContainsString('aria-autocomplete="inline"', $cb->render());

        $cb->autocomplete('none');
        $this->assertStringContainsString('aria-autocomplete="none"', $cb->render());
    }

    public function testAccordionCollapsibleAndHeaderLevels(): void
    {
        $acc = Accordion::make('levels_acc')
            ->headerLevel(5)
            ->collapsible(false)
            ->item('sec1', 'Section 1', 'Content 1');

        $this->assertSame(5, $acc->getHeaderLevel());
        $this->assertFalse($acc->isCollapsible());

        $html = $acc->render();
        $this->assertStringContainsString('<h5 role="heading" aria-level="5">', $html);
        $this->assertStringContainsString('data-collapsible="false"', $html);
    }
}

