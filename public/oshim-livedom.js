/**
 * 👑 Sovereign OSHIM LiveDOM Client Runtime (Dual-Transport Edition)
 * 
 * WHY: Provides instantaneous zero-reload reactive UI updates in Pure PHP apps.
 * Supports WebSockets when running on Universal Fiber Reactor, with seamless
 * zero-reload HTTP fetch() fallback on standard servers.
 */

class LiveDom {
    constructor(wsUrl = null) {
        if (!wsUrl && typeof window !== 'undefined') {
            const loc = window.location;
            const wsProto = loc.protocol === 'https:' ? 'wss:' : 'ws:';
            wsUrl = `${wsProto}//${loc.host}`;
        }
        this.wsUrl = wsUrl;
        this.isWsConnected = false;
        this.initSocket();
        this.attachEventListeners();
    }

    initSocket() {
        if (typeof WebSocket === 'undefined') return;
        try {
            this.socket = new WebSocket(this.wsUrl);
            this.socket.onopen = () => {
                this.isWsConnected = true;
                console.log('⚡ OSHIM LiveDOM WebSocket Connected');
            };
            this.socket.onmessage = (e) => this.handleServerMessage(e);
            this.socket.onclose = () => {
                this.isWsConnected = false;
            };
            this.socket.onerror = () => {
                this.isWsConnected = false;
            };
        } catch (e) {
            this.isWsConnected = false;
        }
    }

    attachEventListeners() {
        // Intercept all [oshim-click] elements
        document.addEventListener('click', (e) => {
            const target = e.target.closest('[oshim-click]');
            if (!target) return;
            e.preventDefault();
            e.stopPropagation();
            this.handleAction(target, target.getAttribute('oshim-click'));
        });
        
        // Intercept two-way data-binding [oshim-model] elements
        let timeout = null;
        document.addEventListener('input', (e) => {
            const target = e.target.closest('[oshim-model]');
            if (!target) return;
            
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const prop = target.getAttribute('oshim-model');
                this.handleAction(target, 'updating', { property: prop, value: target.value });
            }, 100);
        });
    }

    handleAction(element, methodCall, directValue = null) {
        const componentRoot = element.closest('[oshim-component]');
        if (!componentRoot) return;

        const id = componentRoot.getAttribute('id');
        const componentName = componentRoot.getAttribute('oshim-component');
        const stateStr = componentRoot.getAttribute('oshim-state') || '{}';
        
        // Parse method signatures like "setScore(90)" or "increment"
        let method = methodCall;
        let value = directValue;
        const callMatch = methodCall.match(/^([a-zA-Z0-9_]+)\((.*)\)$/);
        if (callMatch) {
            method = callMatch[1];
            const rawArg = callMatch[2].trim();
            if (rawArg.startsWith("'") && rawArg.endsWith("'")) {
                value = rawArg.slice(1, -1);
            } else if (rawArg.startsWith('"') && rawArg.endsWith('"')) {
                value = rawArg.slice(1, -1);
            } else if (!isNaN(rawArg) && rawArg !== '') {
                value = Number(rawArg);
            } else if (rawArg === 'true') {
                value = true;
            } else if (rawArg === 'false') {
                value = false;
            } else {
                value = rawArg;
            }
        }

        // Visual loading indicator (optimistic feedback)
        element.style.opacity = '0.65';
        element.classList.add('oshim-pulsing');

        const payload = {
            id: id,
            component: componentName,
            method: method,
            value: value,
            state: JSON.parse(stateStr)
        };

        // 1. Primary: WebSocket transmission (Instantaneous)
        if (this.isWsConnected && this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify(payload));
            setTimeout(() => {
                element.style.opacity = '1';
                element.classList.remove('oshim-pulsing');
            }, 200);
            return;
        }

        // 2. Dual-Transport Fallback: HTTP fetch POST (Zero Page Reload)
        fetch('/api/livedom', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(res => {
            if (!res.ok) throw new Error('LiveDOM server responded with HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            element.style.opacity = '1';
            element.classList.remove('oshim-pulsing');
            this.updateComponent(data);
        })
        .catch(err => {
            element.style.opacity = '1';
            element.classList.remove('oshim-pulsing');
            console.error('OSHIM LiveDOM Action Error:', err);
        });
    }

    handleServerMessage(event) {
        try {
            const data = JSON.parse(event.data);
            this.updateComponent(data);
        } catch (e) {
            console.error('Failed to parse LiveDOM message:', e);
        }
    }

    updateComponent(data) {
        if (!data || !data.id || !data.html) return;
        const componentRoot = document.getElementById(data.id);
        if (!componentRoot) return;

        const template = document.createElement('div');
        template.innerHTML = data.html.trim();
        const newElement = template.firstElementChild;
        if (newElement && data.state) {
            newElement.setAttribute('oshim-state', JSON.stringify(data.state));
        }

        // Execute Morphing Algorithm without any browser reload
        if (newElement) {
            this.morphDOM(componentRoot, newElement);
        }
    }

    /**
     * Recursive Virtual-DOM Morphing Algorithm.
     * Preserves active focus, cursor position, and scroll states.
     */
    morphDOM(oldNode, newNode) {
        // 1. Update Attributes
        if (oldNode.nodeType === 1 && newNode.nodeType === 1) {
            for (let attr of newNode.attributes) {
                if (oldNode.getAttribute(attr.name) !== attr.value) {
                    oldNode.setAttribute(attr.name, attr.value);
                    if (attr.name === 'value' && oldNode.tagName === 'INPUT') {
                        if (oldNode.value !== attr.value) {
                            oldNode.value = attr.value;
                        }
                    }
                }
            }
            for (let attr of oldNode.attributes) {
                if (!newNode.hasAttribute(attr.name)) {
                    oldNode.removeAttribute(attr.name);
                }
            }
        }

        // 2. Sync Text Nodes
        if (oldNode.nodeType === 3 && newNode.nodeType === 3) {
            if (oldNode.nodeValue !== newNode.nodeValue) {
                oldNode.nodeValue = newNode.nodeValue;
            }
            return;
        }

        // 3. Morph Children Recursively
        const oldChildren = Array.from(oldNode.childNodes);
        const newChildren = Array.from(newNode.childNodes);
        const max = Math.max(oldChildren.length, newChildren.length);

        for (let i = 0; i < max; i++) {
            if (!oldChildren[i]) {
                oldNode.appendChild(newChildren[i].cloneNode(true));
            } else if (!newChildren[i]) {
                oldNode.removeChild(oldChildren[i]);
            } else if (oldChildren[i].nodeType !== newChildren[i].nodeType || 
                       (oldChildren[i].nodeType === 1 && oldChildren[i].tagName !== newChildren[i].tagName)) {
                oldNode.replaceChild(newChildren[i].cloneNode(true), oldChildren[i]);
            } else {
                this.morphDOM(oldChildren[i], newChildren[i]);
            }
        }
    }
}

// Auto-boot LiveDOM client on DOM ready
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.LiveDom = new LiveDom();
        });
    } else {
        window.LiveDom = new LiveDom();
    }
}
