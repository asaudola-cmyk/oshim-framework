<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

/**
 * Sovereign Vanilla JS Client Runtime Generator for Oshim LiveDOM.
 * Pure Vanilla JavaScript (< 5KB), zero external dependencies.
 * Contains DOM Morphing Engine, Declarative Directives, Event Delegation, and Two-Way Model Sync.
 */
class LiveDomClient
{
    private static ?string $cachedScript = null;

    /**
     * Get the raw Vanilla JS client runtime script.
     */
    public static function getScript(): string
    {
        if (self::$cachedScript !== null) {
            return self::$cachedScript;
        }

        return self::$cachedScript = <<<'JS'
/**
 * ⚡ Oshim LiveDOM Client Runtime (Zero-Dependency Reactive Engine)
 */
(function(window, document) {
    'use strict';

    if (window.LiveDom) return;

    var LiveDom = {
        endpoint: '/_oshim/livedom',
        csrfToken: null,
        activePolls: new Map(),
        pendingRequests: new Map(),
        debounces: new Map(),

        init: function() {
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) this.csrfToken = csrfMeta.getAttribute('content');

            this.initEventDelegation();
            this.initPolls();
            this.initModelBindings();
        },

        // --- DOM Morphing Algorithm ---
        morph: function(fromNode, toInput) {
            if (!fromNode) return;

            var toNode;
            if (typeof toInput === 'string') {
                var template = document.createElement('template');
                template.innerHTML = toInput.trim();
                toNode = template.content.firstElementChild;
                if (!toNode) return;
            } else {
                toNode = toInput;
            }

            // Save active element focus and selection range
            var activeEl = document.activeElement;
            var activePath = (activeEl && fromNode.contains(activeEl)) ? this.getNodePath(fromNode, activeEl) : null;
            var selection = null;
            if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
                try {
                    selection = { start: activeEl.selectionStart, end: activeEl.selectionEnd };
                } catch (e) {}
            }

            // Perform in-place morph
            this.morphNode(fromNode, toNode);

            // Restore active element focus and selection
            if (activePath) {
                var restoredEl = this.getNodeByPath(fromNode, activePath);
                if (restoredEl && typeof restoredEl.focus === 'function') {
                    restoredEl.focus();
                    if (selection && (restoredEl.tagName === 'INPUT' || restoredEl.tagName === 'TEXTAREA')) {
                        try {
                            restoredEl.setSelectionRange(selection.start, selection.end);
                        } catch (e) {}
                    }
                }
            }

            this.initPolls(fromNode);
            this.initModelBindings(fromNode);
            fromNode.dispatchEvent(new CustomEvent('livedom:morphed', { bubbles: true }));
        },

        morphNode: function(from, to) {
            // If completely different node type or tag, replace in parent
            if (from.nodeType !== to.nodeType || from.nodeName !== to.nodeName) {
                if (from.parentNode) {
                    from.parentNode.replaceChild(to, from);
                }
                return;
            }

            // Text node morphing
            if (from.nodeType === Node.TEXT_NODE) {
                if (from.nodeValue !== to.nodeValue) {
                    from.nodeValue = to.nodeValue;
                }
                return;
            }

            // Element node morphing
            if (from.nodeType === Node.ELEMENT_NODE) {
                // Morph attributes
                this.morphAttributes(from, to);

                // Preserve form input interactive properties
                if (from.tagName === 'INPUT') {
                    if (from.type === 'checkbox' || from.type === 'radio') {
                        if (from.checked !== to.checked) from.checked = to.checked;
                    } else if (document.activeElement !== from && from.value !== to.value) {
                        from.value = to.value;
                    }
                } else if (from.tagName === 'TEXTAREA') {
                    if (document.activeElement !== from && from.value !== to.value) {
                        from.value = to.value;
                    }
                } else if (from.tagName === 'SELECT') {
                    if (from.value !== to.value) from.value = to.value;
                }

                // Morph child nodes
                this.morphChildren(from, to);
            }
        },

        morphAttributes: function(from, to) {
            var toAttrs = to.attributes;
            var fromAttrs = from.attributes;

            // Set new or changed attributes
            for (var i = 0; i < toAttrs.length; i++) {
                var attr = toAttrs[i];
                if (from.getAttribute(attr.name) !== attr.value) {
                    from.setAttribute(attr.name, attr.value);
                }
            }

            // Remove attributes that no longer exist
            for (var j = fromAttrs.length - 1; j >= 0; j--) {
                var name = fromAttrs[j].name;
                if (!to.hasAttribute(name)) {
                    from.removeAttribute(name);
                }
            }
        },

        morphChildren: function(from, to) {
            var fromChildren = Array.from(from.childNodes);
            var toChildren = Array.from(to.childNodes);

            // Keyed elements indexing
            var fromKeys = new Map();
            for (var i = 0; i < fromChildren.length; i++) {
                var key = this.getKey(fromChildren[i]);
                if (key) fromKeys.set(key, fromChildren[i]);
            }

            var toIndex = 0;
            var fromIndex = 0;

            while (toIndex < toChildren.length) {
                var toChild = toChildren[toIndex];
                var fromChild = fromChildren[fromIndex];
                var toKey = this.getKey(toChild);

                if (toKey && fromKeys.has(toKey)) {
                    var matchingFrom = fromKeys.get(toKey);
                    if (matchingFrom !== fromChild) {
                        from.insertBefore(matchingFrom, fromChild);
                    }
                    this.morphNode(matchingFrom, toChild);
                    fromIndex++;
                    toIndex++;
                    continue;
                }

                if (!fromChild) {
                    // Append new child
                    from.appendChild(toChild);
                    toIndex++;
                    continue;
                }

                var fromKey = this.getKey(fromChild);
                if (fromKey && !toChildren.some(function(c) { return LiveDom.getKey(c) === fromKey; })) {
                    // Stale keyed element removed
                    var next = fromChild.nextSibling;
                    from.removeChild(fromChild);
                    fromChild = next;
                    fromIndex++;
                    continue;
                }

                this.morphNode(fromChild, toChild);
                fromIndex++;
                toIndex++;
            }

            // Remove excess trailing children
            while (from.childNodes.length > toChildren.length) {
                from.removeChild(from.lastChild);
            }
        },

        getKey: function(node) {
            if (node && node.nodeType === Node.ELEMENT_NODE) {
                return node.getAttribute('live:key') || node.getAttribute('data-key') || node.getAttribute('key') || null;
            }
            return null;
        },

        getNodePath: function(root, target) {
            var path = [];
            var current = target;
            while (current && current !== root && current.parentNode) {
                var parent = current.parentNode;
                var index = Array.prototype.indexOf.call(parent.childNodes, current);
                path.unshift(index);
                current = parent;
            }
            return current === root ? path : null;
        },

        getNodeByPath: function(root, path) {
            var current = root;
            for (var i = 0; i < path.length; i++) {
                if (!current || !current.childNodes[path[i]]) return null;
                current = current.childNodes[path[i]];
            }
            return current;
        },

        // --- Event Delegation & Directives ---
        initEventDelegation: function() {
            var self = this;

            // Click delegation
            document.addEventListener('click', function(e) {
                var el = e.target.closest('[live\\:click], [data-live-click]');
                if (!el) return;

                var attr = self.getLiveAttr(el, 'click');
                if (!attr) return;

                self.handleDirectiveEvent(e, el, attr, 'click');
            });

            // Submit delegation
            document.addEventListener('submit', function(e) {
                var form = e.target.closest('form[live\\:submit], form[data-live-submit]');
                if (!form) return;

                var attr = self.getLiveAttr(form, 'submit');
                if (!attr) return;

                if (attr.modifiers.includes('prevent') || !attr.modifiers.includes('no-prevent')) {
                    e.preventDefault();
                }

                var formData = new FormData(form);
                var dataObj = {};
                formData.forEach(function(val, key) {
                    if (dataObj[key] !== undefined) {
                        if (!Array.isArray(dataObj[key])) dataObj[key] = [dataObj[key]];
                        dataObj[key].push(val);
                    } else {
                        dataObj[key] = val;
                    }
                });

                var parsed = self.parseActionExpression(attr.value);
                var params = parsed.args.length > 0 ? parsed.args : [dataObj];

                self.executeAction(form, parsed.action, params, attr.modifiers);
            });

            // Keydown delegation
            document.addEventListener('keydown', function(e) {
                var el = e.target.closest('[live\\:keydown], [data-live-keydown]');
                if (!el) return;

                var attr = self.getLiveAttr(el, 'keydown');
                if (!attr) return;

                if (attr.modifiers.includes('enter') && e.key !== 'Enter') return;
                if (attr.modifiers.includes('escape') && e.key !== 'Escape') return;
                if (attr.modifiers.includes('tab') && e.key !== 'Tab') return;

                self.handleDirectiveEvent(e, el, attr, 'keydown');
            });
        },

        initModelBindings: function(root) {
            var container = root || document;
            var self = this;
            var models = container.querySelectorAll('[live\\:model], [data-live-model]');

            models.forEach(function(el) {
                if (el._liveModelBound) return;
                el._liveModelBound = true;

                var attr = self.getLiveAttr(el, 'model');
                if (!attr) return;

                var prop = attr.value;
                var isLazy = attr.modifiers.includes('lazy');
                var debounceMs = self.getDebounce(attr.modifiers) || 150;
                var eventName = isLazy || el.type === 'checkbox' || el.type === 'radio' || el.tagName === 'SELECT' ? 'change' : 'input';

                var handler = function() {
                    var val = el.type === 'checkbox' ? el.checked : el.value;
                    self.set(el, prop, val, isLazy ? 0 : debounceMs);
                };

                el.addEventListener(eventName, handler);
            });
        },

        initPolls: function(root) {
            var container = root || document;
            var self = this;
            var polls = container.querySelectorAll('[live\\:poll], [data-live-poll]');

            polls.forEach(function(el) {
                var comp = el.closest('[data-live-id]');
                if (!comp) return;
                var compId = comp.getAttribute('data-live-id');

                if (self.activePolls.has(compId)) {
                    clearInterval(self.activePolls.get(compId));
                }

                var attr = self.getLiveAttr(el, 'poll');
                if (!attr) return;

                var intervalMs = 2000; // default 2s
                for (var i = 0; i < attr.modifiers.length; i++) {
                    var m = attr.modifiers[i];
                    if (m.endsWith('ms')) intervalMs = parseInt(m, 10);
                    else if (m.endsWith('s')) intervalMs = parseInt(m, 10) * 1000;
                }

                var actionName = attr.value || '$refresh';
                var timer = setInterval(function() {
                    if (!document.body.contains(el)) {
                        clearInterval(timer);
                        self.activePolls.delete(compId);
                        return;
                    }
                    self.call(comp, actionName);
                }, intervalMs);

                self.activePolls.set(compId, timer);
            });
        },

        handleDirectiveEvent: function(e, el, attr, eventType) {
            if (attr.modifiers.includes('prevent')) e.preventDefault();
            if (attr.modifiers.includes('stop')) e.stopPropagation();
            if (attr.modifiers.includes('self') && e.target !== el) return;

            var parsed = this.parseActionExpression(attr.value);
            var debounceMs = this.getDebounce(attr.modifiers);

            this.executeAction(el, parsed.action, parsed.args, attr.modifiers, debounceMs);
        },

        executeAction: function(el, action, params, modifiers, debounceMs) {
            var self = this;
            var comp = el.closest('[data-live-id]');
            if (!comp) return;

            var run = function() {
                self.call(comp, action, params);
            };

            if (debounceMs && debounceMs > 0) {
                var compId = comp.getAttribute('data-live-id');
                var debounceKey = compId + ':' + action;
                if (this.debounces.has(debounceKey)) clearTimeout(this.debounces.get(debounceKey));
                this.debounces.set(debounceKey, setTimeout(run, debounceMs));
            } else {
                run();
            }
        },

        // --- Network & Action Invocation ---
        call: function(target, action, params) {
            var comp = typeof target === 'string' ? document.querySelector('[data-live-id="' + target + '"]') : target.closest('[data-live-id]');
            if (!comp) return Promise.reject(new Error('LiveDOM component not found'));

            var compId = comp.getAttribute('data-live-id');
            var snapshot = comp.getAttribute('data-live-snapshot');
            var self = this;

            this.toggleLoading(comp, action, true);

            var headers = {
                'Content-Type': 'application/json',
                'X-LiveDOM': '1'
            };
            if (this.csrfToken) headers['X-CSRF-TOKEN'] = this.csrfToken;

            var payload = {
                id: compId,
                action: action,
                params: params || [],
                snapshot: snapshot
            };

            return fetch(this.endpoint, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                self.toggleLoading(comp, action, false);

                if (!data.success) {
                    console.error('[LiveDOM Error]', data.error || data.errors);
                    comp.dispatchEvent(new CustomEvent('livedom:error', { detail: data }));
                    return data;
                }

                if (data.redirect) {
                    window.location.href = data.redirect;
                    return data;
                }

                // Update snapshot
                if (data.snapshot && typeof data.snapshot === 'string') {
                    comp.setAttribute('data-live-snapshot', data.snapshot);
                } else if (data.snapshot && data.snapshot.encoded) {
                    comp.setAttribute('data-live-snapshot', data.snapshot.encoded);
                }

                // Morph DOM
                if (data.html) {
                    self.morph(comp, data.html);
                }

                // Dispatch server browser events
                if (data.events && Array.isArray(data.events)) {
                    data.events.forEach(function(ev) {
                        window.dispatchEvent(new CustomEvent(ev.name, { detail: ev.detail }));
                    });
                }

                comp.dispatchEvent(new CustomEvent('livedom:response', { detail: data }));
                return data;
            })
            .catch(function(err) {
                self.toggleLoading(comp, action, false);
                console.error('[LiveDOM Network Error]', err);
                comp.dispatchEvent(new CustomEvent('livedom:network-error', { detail: err }));
            });
        },

        set: function(target, property, value, debounceMs) {
            var comp = typeof target === 'string' ? document.querySelector('[data-live-id="' + target + '"]') : target.closest('[data-live-id]');
            if (!comp) return;

            var self = this;
            var run = function() {
                self.call(comp, '$set', [property, value]);
            };

            if (debounceMs && debounceMs > 0) {
                var compId = comp.getAttribute('data-live-id');
                var debounceKey = compId + ':$set:' + property;
                if (this.debounces.has(debounceKey)) clearTimeout(this.debounces.get(debounceKey));
                this.debounces.set(debounceKey, setTimeout(run, debounceMs));
            } else {
                run();
            }
        },

        // --- Loading States ---
        toggleLoading: function(comp, action, isLoading) {
            var loaders = comp.querySelectorAll('[live\\:loading], [data-live-loading]');
            loaders.forEach(function(el) {
                var targetAction = el.getAttribute('live:target') || el.getAttribute('data-live-target');
                if (targetAction && targetAction !== action) return;

                if (isLoading) {
                    el.classList.add('live-loading-active');
                    if (el.hasAttribute('live:loading.class')) {
                        el.classList.add(el.getAttribute('live:loading.class'));
                    }
                    if (el.hasAttribute('live:loading.attr')) {
                        el.setAttribute(el.getAttribute('live:loading.attr'), 'true');
                    }
                } else {
                    el.classList.remove('live-loading-active');
                    if (el.hasAttribute('live:loading.class')) {
                        el.classList.remove(el.getAttribute('live:loading.class'));
                    }
                    if (el.hasAttribute('live:loading.attr')) {
                        el.removeAttribute(el.getAttribute('live:loading.attr'));
                    }
                }
            });
        },

        // --- Utilities ---
        getLiveAttr: function(el, prefix) {
            var livePrefix = 'live:' + prefix;
            var dataPrefix = 'data-live-' + prefix;

            for (var i = 0; i < el.attributes.length; i++) {
                var attr = el.attributes[i];
                var name = attr.name;
                if (name === livePrefix || name.startsWith(livePrefix + '.') ||
                    name === dataPrefix || name.startsWith(dataPrefix + '.')) {
                    var parts = name.split('.');
                    parts.shift(); // remove prefix
                    return {
                        name: name,
                        value: attr.value,
                        modifiers: parts
                    };
                }
            }
            return null;
        },

        getDebounce: function(modifiers) {
            for (var i = 0; i < modifiers.length; i++) {
                var m = modifiers[i];
                if (m === 'debounce' && modifiers[i + 1]) {
                    var val = modifiers[i + 1];
                    if (val.endsWith('ms')) return parseInt(val, 10);
                    if (val.endsWith('s')) return parseInt(val, 10) * 1000;
                    return parseInt(val, 10);
                }
                if (m.endsWith('ms')) return parseInt(m, 10);
                if (m.endsWith('s')) return parseInt(m, 10) * 1000;
            }
            return null;
        },

        parseActionExpression: function(expr) {
            expr = (expr || '').trim();
            if (!expr) return { action: '', args: [] };

            var match = expr.match(/^([a-zA-Z0-9_$]+)(?:\((.*)\))?$/);
            if (!match) return { action: expr, args: [] };

            var action = match[1];
            var argsStr = match[2];
            if (!argsStr || !argsStr.trim()) return { action: action, args: [] };

            try {
                // Parse arguments
                var fn = new Function('return [' + argsStr + '];');
                return { action: action, args: fn() };
            } catch (e) {
                return { action: action, args: [argsStr] };
            }
        },

        on: function(event, callback) {
            window.addEventListener(event, callback);
        },

        dispatch: function(event, detail) {
            window.dispatchEvent(new CustomEvent(event, { detail: detail }));
        }
    };

    window.LiveDom = LiveDom;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { LiveDom.init(); });
    } else {
        LiveDom.init();
    }
})(window, document);
JS;
    }

    /**
     * Get default CSS for loading states and transitions.
     */
    public static function getStyles(): string
    {
        return <<<'CSS'
[live\:loading], [data-live-loading] {
    display: none;
}
[live\:loading].live-loading-active, [data-live-loading].live-loading-active {
    display: inline-block;
}
[live\:loading\.remove].live-loading-active, [data-live-loading\.remove].live-loading-active {
    display: none !important;
}
.livedom-morph-enter {
    animation: livedomFadeIn 0.15s ease-out;
}
@keyframes livedomFadeIn {
    from { opacity: 0.7; }
    to { opacity: 1; }
}
CSS;
    }

    /**
     * Render the runtime as an inline script tag.
     */
    public static function renderScriptTag(): string
    {
        $script = self::getScript();
        return "<script>\n{$script}\n</script>";
    }

    /**
     * Render CSS styles tag.
     */
    public static function renderStyleTag(): string
    {
        $styles = self::getStyles();
        return "<style>\n{$styles}\n</style>";
    }
}
