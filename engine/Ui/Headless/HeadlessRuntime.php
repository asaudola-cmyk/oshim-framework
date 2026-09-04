<?php
declare(strict_types=1);

namespace Oshim\Ui\Headless;

/**
 * Headless UI Client-Side Runtime Engine.
 * Ultra-lightweight, zero-dependency vanilla JS engine that brings full
 * interactive WAI-ARIA keyboard navigation, focus trapping, and state
 * transitions to Headless UI primitives in the browser.
 */
class HeadlessRuntime
{
    /**
     * Return minified or formatted client-side script tag.
     */
    public static function script(): string
    {
        return '<script>' . self::js() . '</script>';
    }

    /**
     * Return raw JavaScript code for Headless UI interactive contracts.
     */
    public static function js(): string
    {
        return <<<'JS'
/** 👑 OSHIM Headless UI Client Runtime - Zero External Dependencies */
(function() {
    if (window.__oshim_headless_initialized) return;
    window.__oshim_headless_initialized = true;

    const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    // Helper: Find all focusable elements within container
    function getFocusable(container) {
        return Array.from(container.querySelectorAll(FOCUSABLE)).filter(el => el.offsetParent !== null);
    }

    // --- 1. DIALOG / MODAL HANDLERS ---
    function openDialog(dialogId) {
        const dialog = document.getElementById(dialogId);
        if (!dialog) return;
        const trigger = dialog.querySelector(`[data-headless-trigger="${dialogId}"]`);
        const overlay = dialog.querySelector(`[data-headless-overlay="${dialogId}"]`);
        const content = dialog.querySelector(`[data-headless-content="${dialogId}"]`);

        dialog.setAttribute('data-state', 'open');
        if (trigger) trigger.setAttribute('data-state', 'open'), trigger.setAttribute('aria-expanded', 'true');
        if (overlay) overlay.setAttribute('data-state', 'open'), overlay.removeAttribute('hidden');
        if (content) {
            content.setAttribute('data-state', 'open');
            content.removeAttribute('hidden');

            // Save trigger to restore focus on close
            if (trigger) content.__restoreTrigger = trigger;

            // Focus initial element or first focusable
            const initialSelector = content.getAttribute('data-headless-initial-focus');
            const target = initialSelector ? content.querySelector(initialSelector) : null;
            const focusables = getFocusable(content);
            const toFocus = target || focusables[0] || content;
            setTimeout(() => toFocus.focus(), 20);
        }
    }

    function closeDialog(dialogId) {
        const dialog = document.getElementById(dialogId);
        if (!dialog) return;
        const trigger = dialog.querySelector(`[data-headless-trigger="${dialogId}"]`);
        const overlay = dialog.querySelector(`[data-headless-overlay="${dialogId}"]`);
        const content = dialog.querySelector(`[data-headless-content="${dialogId}"]`);

        dialog.setAttribute('data-state', 'closed');
        if (trigger) trigger.setAttribute('data-state', 'closed'), trigger.setAttribute('aria-expanded', 'false');
        if (overlay) overlay.setAttribute('data-state', 'closed'), overlay.setAttribute('hidden', '');
        if (content) {
            content.setAttribute('data-state', 'closed');
            content.setAttribute('hidden', '');

            // Restore focus
            const restoreAttr = content.getAttribute('data-headless-restore-focus');
            if (restoreAttr !== 'false') {
                const returnTarget = (restoreAttr && restoreAttr !== 'true') ? document.querySelector(restoreAttr) : (content.__restoreTrigger || trigger);
                if (returnTarget) setTimeout(() => returnTarget.focus(), 10);
            }
        }
    }

    // --- 2. DROPDOWN MENU HANDLERS ---
    function openMenu(menuId) {
        const root = document.getElementById(menuId);
        if (!root) return;
        const trigger = root.querySelector(`[data-headless-trigger="${menuId}"]`);
        const content = root.querySelector(`[data-headless-content="${menuId}"]`);

        root.setAttribute('data-state', 'open');
        if (trigger) trigger.setAttribute('data-state', 'open'), trigger.setAttribute('aria-expanded', 'true');
        if (content) {
            content.setAttribute('data-state', 'open');
            content.removeAttribute('hidden');
            const items = Array.from(content.querySelectorAll('[role="menuitem"], [role="menuitemcheckbox"], [role="menuitemradio"]')).filter(i => i.getAttribute('aria-disabled') !== 'true');
            if (items.length > 0) {
                highlightMenuItem(content, 0);
                setTimeout(() => items[0].focus(), 15);
            }
        }
    }

    function closeMenu(menuId) {
        const root = document.getElementById(menuId);
        if (!root) return;
        const trigger = root.querySelector(`[data-headless-trigger="${menuId}"]`);
        const content = root.querySelector(`[data-headless-content="${menuId}"]`);

        root.setAttribute('data-state', 'closed');
        if (trigger) {
            trigger.setAttribute('data-state', 'closed');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
        }
        if (content) {
            content.setAttribute('data-state', 'closed');
            content.setAttribute('hidden', '');
        }
    }

    function highlightMenuItem(content, index) {
        const items = Array.from(content.querySelectorAll('[role="menuitem"], [role="menuitemcheckbox"], [role="menuitemradio"]')).filter(i => i.getAttribute('aria-disabled') !== 'true');
        if (items.length === 0) return;
        const loop = content.getAttribute('data-headless-focus-loop') !== 'false';
        let targetIndex = index;
        if (targetIndex < 0) targetIndex = loop ? items.length - 1 : 0;
        if (targetIndex >= items.length) targetIndex = loop ? 0 : items.length - 1;

        items.forEach((item, idx) => {
            if (idx === targetIndex) {
                item.setAttribute('data-highlighted', 'true');
                item.setAttribute('tabindex', '0');
                item.focus();
            } else {
                item.setAttribute('data-highlighted', 'false');
                item.setAttribute('tabindex', '-1');
            }
        });
        content.__activeIndex = targetIndex;
    }

    // --- 3. COMBOBOX HANDLERS ---
    function openCombobox(cbId) {
        const root = document.getElementById(cbId);
        if (!root) return;
        const input = root.querySelector(`[data-headless-input="${cbId}"]`);
        const content = root.querySelector(`[data-headless-content="${cbId}"]`);
        root.setAttribute('data-state', 'open');
        if (input) input.setAttribute('aria-expanded', 'true');
        if (content) {
            content.setAttribute('data-state', 'open');
            content.removeAttribute('hidden');
        }
    }

    function closeCombobox(cbId) {
        const root = document.getElementById(cbId);
        if (!root) return;
        const input = root.querySelector(`[data-headless-input="${cbId}"]`);
        const content = root.querySelector(`[data-headless-content="${cbId}"]`);
        root.setAttribute('data-state', 'closed');
        if (input) {
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }
        if (content) {
            content.setAttribute('data-state', 'closed');
            content.setAttribute('hidden', '');
            content.querySelectorAll('[role="option"]').forEach(o => o.setAttribute('data-highlighted', 'false'));
        }
    }

    function highlightComboboxOption(root, index) {
        const content = root.querySelector('[role="listbox"]');
        const input = root.querySelector('[role="combobox"]');
        if (!content || !input) return;
        const options = Array.from(content.querySelectorAll('[role="option"]')).filter(o => o.getAttribute('aria-disabled') !== 'true');
        if (options.length === 0) return;

        let targetIndex = index;
        if (targetIndex < 0) targetIndex = options.length - 1;
        if (targetIndex >= options.length) targetIndex = 0;

        options.forEach((opt, idx) => {
            if (idx === targetIndex) {
                opt.setAttribute('data-highlighted', 'true');
                input.setAttribute('aria-activedescendant', opt.id);
                opt.scrollIntoView({ block: 'nearest' });
            } else {
                opt.setAttribute('data-highlighted', 'false');
            }
        });
        root.__comboboxActiveIndex = targetIndex;
    }

    // --- 4. POPOVER HANDLERS ---
    function openPopover(popoverId) {
        const root = document.getElementById(popoverId);
        if (!root) return;
        const trigger = root.querySelector(`[data-headless-trigger="${popoverId}"]`);
        const content = root.querySelector(`[data-headless-content="${popoverId}"]`);
        root.setAttribute('data-state', 'open');
        if (trigger) trigger.setAttribute('data-state', 'open'), trigger.setAttribute('aria-expanded', 'true');
        if (content) {
            content.setAttribute('data-state', 'open');
            content.removeAttribute('hidden');
            if (trigger) content.__restoreTrigger = trigger;
            const focusables = getFocusable(content);
            if (focusables.length > 0) setTimeout(() => focusables[0].focus(), 15);
        }
    }

    function closePopover(popoverId) {
        const root = document.getElementById(popoverId);
        if (!root) return;
        const trigger = root.querySelector(`[data-headless-trigger="${popoverId}"]`);
        const content = root.querySelector(`[data-headless-content="${popoverId}"]`);
        root.setAttribute('data-state', 'closed');
        if (trigger) {
            trigger.setAttribute('data-state', 'closed');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
        }
        if (content) {
            content.setAttribute('data-state', 'closed');
            content.setAttribute('hidden', '');
        }
    }

    // --- 5. ACCORDION HANDLERS ---
    function toggleAccordionItem(accordionRoot, itemValue) {
        const type = accordionRoot.getAttribute('data-type') || 'single';
        const collapsible = accordionRoot.getAttribute('data-collapsible') !== 'false';
        const items = Array.from(accordionRoot.querySelectorAll('[data-headless-accordion-item]'));

        items.forEach(item => {
            const val = item.getAttribute('data-value');
            const trigger = item.querySelector('[data-headless-accordion-trigger]');
            const content = item.querySelector('[data-headless-accordion-content]');
            const isTarget = (val === itemValue);

            if (isTarget) {
                const isCurrentlyOpen = (item.getAttribute('data-state') === 'open');
                if (isCurrentlyOpen) {
                    if (type === 'multiple' || collapsible) {
                        item.setAttribute('data-state', 'closed');
                        if (trigger) trigger.setAttribute('data-state', 'closed'), trigger.setAttribute('aria-expanded', 'false');
                        if (content) content.setAttribute('data-state', 'closed'), content.setAttribute('hidden', '');
                    }
                } else {
                    item.setAttribute('data-state', 'open');
                    if (trigger) trigger.setAttribute('data-state', 'open'), trigger.setAttribute('aria-expanded', 'true');
                    if (content) content.setAttribute('data-state', 'open'), content.removeAttribute('hidden');
                }
            } else if (type === 'single') {
                // Close other items in single mode
                item.setAttribute('data-state', 'closed');
                if (trigger) trigger.setAttribute('data-state', 'closed'), trigger.setAttribute('aria-expanded', 'false');
                if (content) content.setAttribute('data-state', 'closed'), content.setAttribute('hidden', '');
            }
        });
    }

    // --- GLOBAL EVENT DELEGATION ---
    document.addEventListener('click', function(e) {
        // Trigger clicks
        const trigger = e.target.closest('[data-headless-trigger]');
        if (trigger) {
            const id = trigger.getAttribute('data-headless-trigger');
            const root = document.getElementById(id);
            if (!root) return;
            const kind = root.getAttribute('data-headless');
            const isOpen = root.getAttribute('data-state') === 'open';

            if (kind === 'dialog') isOpen ? closeDialog(id) : openDialog(id);
            else if (kind === 'dropdown-menu') isOpen ? closeMenu(id) : openMenu(id);
            else if (kind === 'combobox') isOpen ? closeCombobox(id) : openCombobox(id);
            else if (kind === 'popover') isOpen ? closePopover(id) : openPopover(id);
            return;
        }

        // Close button clicks
        const closeBtn = e.target.closest('[data-headless-close]');
        if (closeBtn) {
            const id = closeBtn.getAttribute('data-headless-close');
            const root = document.getElementById(id);
            if (!root) return;
            const kind = root.getAttribute('data-headless');
            if (kind === 'dialog') closeDialog(id);
            else if (kind === 'popover') closePopover(id);
            return;
        }

        // Overlay clicks
        const overlay = e.target.closest('[data-headless-close-overlay]');
        if (overlay) {
            const id = overlay.getAttribute('data-headless-overlay');
            if (id) closeDialog(id);
            return;
        }

        // Accordion trigger click
        const accTrigger = e.target.closest('[data-headless-accordion-trigger]');
        if (accTrigger && !accTrigger.disabled) {
            const root = accTrigger.closest('[data-headless="accordion"]');
            const val = accTrigger.getAttribute('data-headless-accordion-trigger');
            if (root && val) toggleAccordionItem(root, val);
            return;
        }

        // Combobox option select click
        const opt = e.target.closest('[data-headless-option]');
        if (opt && opt.getAttribute('aria-disabled') !== 'true') {
            const root = opt.closest('[data-headless="combobox"]');
            if (root) {
                const input = root.querySelector('[role="combobox"]');
                const val = opt.getAttribute('data-value');
                if (input && val) input.value = val;
                closeCombobox(root.id);
            }
            return;
        }

        // Outside clicks for light dismiss (DropdownMenu and Popover)
        document.querySelectorAll('[data-headless="dropdown-menu"][data-state="open"]').forEach(menu => {
            if (!menu.contains(e.target)) closeMenu(menu.id);
        });
        document.querySelectorAll('[data-headless="popover"][data-state="open"]').forEach(pop => {
            if (!pop.contains(e.target)) closePopover(pop.id);
        });
        document.querySelectorAll('[data-headless="combobox"][data-state="open"]').forEach(cb => {
            if (!cb.contains(e.target)) closeCombobox(cb.id);
        });
    });

    // KEYBOARD EVENT DELEGATION
    document.addEventListener('keydown', function(e) {
        // 1. Escape key: Close any active modal dialog or popover or menu
        if (e.key === 'Escape') {
            const openDialogEl = Array.from(document.querySelectorAll('[data-headless="dialog"][data-state="open"]')).pop();
            if (openDialogEl) {
                closeDialog(openDialogEl.id);
                return;
            }
            const openMenuEl = Array.from(document.querySelectorAll('[data-headless="dropdown-menu"][data-state="open"]')).pop();
            if (openMenuEl) {
                closeMenu(openMenuEl.id);
                return;
            }
            const openCbEl = Array.from(document.querySelectorAll('[data-headless="combobox"][data-state="open"]')).pop();
            if (openCbEl) {
                closeCombobox(openCbEl.id);
                return;
            }
            const openPopEl = Array.from(document.querySelectorAll('[data-headless="popover"][data-state="open"]')).pop();
            if (openPopEl) {
                closePopover(openPopEl.id);
                return;
            }
        }

        // 2. Tab key Focus Trapping inside open dialogs
        if (e.key === 'Tab') {
            const openDialogEl = Array.from(document.querySelectorAll('[data-headless="dialog"][data-state="open"]')).pop();
            if (openDialogEl) {
                const content = openDialogEl.querySelector('[data-headless-content]');
                if (content && content.getAttribute('data-headless-focus-trap') === 'true') {
                    const focusables = getFocusable(content);
                    if (focusables.length > 0) {
                        const first = focusables[0];
                        const last = focusables[focusables.length - 1];
                        if (e.shiftKey && document.activeElement === first) {
                            e.preventDefault();
                            last.focus();
                        } else if (!e.shiftKey && document.activeElement === last) {
                            e.preventDefault();
                            first.focus();
                        }
                    }
                }
            }
        }

        // 3. Dropdown Menu Arrow navigation
        const activeMenuContent = document.querySelector('[data-headless="dropdown-menu"][data-state="open"] [role="menu"]');
        if (activeMenuContent && activeMenuContent.contains(document.activeElement)) {
            const currentIdx = activeMenuContent.__activeIndex ?? 0;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightMenuItem(activeMenuContent, currentIdx + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightMenuItem(activeMenuContent, currentIdx - 1);
            } else if (e.key === 'Home') {
                e.preventDefault();
                highlightMenuItem(activeMenuContent, 0);
            } else if (e.key === 'End') {
                e.preventDefault();
                highlightMenuItem(activeMenuContent, 9999);
            } else if (e.key === 'Enter' || e.key === ' ') {
                const item = document.activeElement;
                if (item && item.getAttribute('role')?.startsWith('menuitem')) {
                    e.preventDefault();
                    item.click();
                    const menuRoot = activeMenuContent.closest('[data-headless="dropdown-menu"]');
                    if (menuRoot) closeMenu(menuRoot.id);
                }
            }
        }

        // 4. Combobox Arrow and Enter navigation
        const activeCbInput = document.activeElement;
        if (activeCbInput && activeCbInput.getAttribute('role') === 'combobox') {
            const cbRoot = activeCbInput.closest('[data-headless="combobox"]');
            if (cbRoot) {
                const isOpen = cbRoot.getAttribute('data-state') === 'open';
                const currentIdx = cbRoot.__comboboxActiveIndex ?? -1;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!isOpen) openCombobox(cbRoot.id);
                    highlightComboboxOption(cbRoot, currentIdx + 1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (!isOpen) openCombobox(cbRoot.id);
                    highlightComboboxOption(cbRoot, currentIdx - 1);
                } else if (e.key === 'Enter' && isOpen) {
                    e.preventDefault();
                    const activeDescId = activeCbInput.getAttribute('aria-activedescendant');
                    if (activeDescId) {
                        const opt = document.getElementById(activeDescId);
                        if (opt) {
                            activeCbInput.value = opt.getAttribute('data-value') || opt.textContent.trim();
                            closeCombobox(cbRoot.id);
                        }
                    }
                }
            }
        }

        // 5. Accordion Arrow navigation between triggers
        const activeAccTrigger = document.activeElement;
        if (activeAccTrigger && activeAccTrigger.hasAttribute('data-headless-accordion-trigger')) {
            const accRoot = activeAccTrigger.closest('[data-headless="accordion"]');
            if (accRoot) {
                const triggers = Array.from(accRoot.querySelectorAll('[data-headless-accordion-trigger]')).filter(t => !t.disabled);
                const currIdx = triggers.indexOf(activeAccTrigger);
                if (currIdx !== -1) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const nextIdx = (currIdx + 1) % triggers.length;
                        triggers[nextIdx].focus();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        const prevIdx = (currIdx - 1 + triggers.length) % triggers.length;
                        triggers[prevIdx].focus();
                    } else if (e.key === 'Home') {
                        e.preventDefault();
                        triggers[0].focus();
                    } else if (e.key === 'End') {
                        e.preventDefault();
                        triggers[triggers.length - 1].focus();
                    }
                }
            }
        }
    });
})();
JS;
    }
}
