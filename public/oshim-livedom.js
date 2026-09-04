/**
 * 👑 Sovereign OSHIM LiveDOM Client Bridge (Advanced VDOM & Multiplayer)
 * 
 * ADVANCED: Uses true DOM Morphing algorithm to sync changes without losing input focus.
 * Supports multiplayer state synchronization over WebSockets.
 */

class LiveDom {
    constructor(wsUrl = 'ws://localhost:8080') {
        this.socket = new WebSocket(wsUrl);
        this.socket.onopen = () => console.log('⚡ OSHIM Multiplayer LiveDOM Connected');
        this.socket.onmessage = (e) => this.handleServerMessage(e);
        this.attachEventListeners();
    }

    attachEventListeners() {
        document.addEventListener('click', (e) => {
            const target = e.target.closest('[oshim-click]');
            if (!target) return;
            e.preventDefault();
            this.handleAction(target, target.getAttribute('oshim-click'));
        });
        
        let timeout = null;
        document.addEventListener('input', (e) => {
            const target = e.target.closest('[oshim-model]');
            if (!target) return;
            
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                this.handleAction(target, 'update_model', target.value);
            }, 100); // 100ms Debounce for fast typing
        });
    }

    handleAction(element, method, value = null) {
        const componentRoot = element.closest('[oshim-component]');
        if (!componentRoot) return;

        const id = componentRoot.getAttribute('id');
        const componentName = componentRoot.getAttribute('oshim-component');
        const stateStr = componentRoot.getAttribute('oshim-state') || '{}';
        
        // Optimistic UI for buttons
        if (method !== 'update_model') {
            element.style.opacity = '0.7';
            element.classList.add('oshim-loading');
        }

        this.socket.send(JSON.stringify({
            id: id,
            component: componentName,
            method: method,
            value: value,
            state: JSON.parse(stateStr)
        }));
    }

    handleServerMessage(event) {
        const { id, html, state } = JSON.parse(event.data);
        const componentRoot = document.getElementById(id);
        if (!componentRoot) return;

        const template = document.createElement('div');
        template.innerHTML = html.trim();
        const newElement = template.firstElementChild;
        newElement.setAttribute('oshim-state', JSON.stringify(state));
        
        // 🚀 ADVANCED DOM MORPHING ALGORITHM
        // WHY: We cannot just replace outerHTML. If we do, the user loses cursor focus
        // while typing in an input field when the server syncs state!
        this.morphDOM(componentRoot, newElement);
    }

    /**
     * Recursive DOM Morphing Algorithm (Preserves Focus & Cursor)
     */
    morphDOM(oldNode, newNode) {
        // 1. Update Attributes
        if (oldNode.nodeType === 1 && newNode.nodeType === 1) { // ELEMENT_NODE
            // Sync new attributes
            for (let attr of newNode.attributes) {
                if (oldNode.getAttribute(attr.name) !== attr.value) {
                    oldNode.setAttribute(attr.name, attr.value);
                    // Edge Case: Sync actual input value so cursor doesn't jump
                    if (attr.name === 'value' && oldNode.tagName === 'INPUT') {
                        if (oldNode.value !== attr.value) {
                            oldNode.value = attr.value;
                        }
                    }
                }
            }
            // Remove old attributes
            for (let attr of oldNode.attributes) {
                if (!newNode.hasAttribute(attr.name)) {
                    oldNode.removeAttribute(attr.name);
                }
            }
        }

        // 2. Sync Text Nodes
        if (oldNode.nodeType === 3 && newNode.nodeType === 3) { // TEXT_NODE
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
                // Node added
                oldNode.appendChild(newChildren[i].cloneNode(true));
            } else if (!newChildren[i]) {
                // Node removed
                oldNode.removeChild(oldChildren[i]);
            } else if (oldChildren[i].nodeType !== newChildren[i].nodeType || 
                       (oldChildren[i].nodeType === 1 && oldChildren[i].tagName !== newChildren[i].tagName)) {
                // Node completely changed type/tag
                oldNode.replaceChild(newChildren[i].cloneNode(true), oldChildren[i]);
            } else {
                // Nodes match type/tag, morph deeper
                this.morphDOM(oldChildren[i], newChildren[i]);
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.LiveDom = new LiveDom();
});
