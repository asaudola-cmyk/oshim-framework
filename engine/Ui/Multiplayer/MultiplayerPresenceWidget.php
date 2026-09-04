<?php
declare(strict_types=1);

namespace Oshim\Ui\Multiplayer;

use Oshim\Ui\Dsl\Element;

/**
 * MultiplayerPresenceWidget: Real-time Live Cursors & Peer Presence Stack.
 * Renders smooth multi-user cursor tracking, active collaborator avatars, and WebSockets telemetry.
 */
class MultiplayerPresenceWidget extends Element
{
    private string $roomId;
    private string $wsEndpoint;
    private string $userName;
    private string $userRole;
    private ?string $userColor;
    private bool $showCursorOverlay = true;
    private bool $showAvatarStack = true;
    private bool $showBroadcastDock = true;
    private array $options = [];

    public function __construct(
        string $roomId = 'global-workspace',
        string $wsEndpoint = 'ws://127.0.0.1:9090',
        string $userName = 'Guest Collaborator',
        string $userRole = 'editor',
        ?string $userColor = null,
        array $options = []
    ) {
        parent::__construct('div');
        $this->roomId = $roomId;
        $this->wsEndpoint = $wsEndpoint;
        $this->userName = $userName;
        $this->userRole = $userRole;
        $this->userColor = $userColor;
        $this->options = $options;
        $this->class('oshim-multiplayer-presence-root relative w-full');
    }

    public static function create(
        string $roomId = 'global-workspace',
        string $wsEndpoint = 'ws://127.0.0.1:9090',
        string $userName = 'Guest Collaborator',
        string $userRole = 'editor',
        ?string $userColor = null,
        array $options = []
    ): self {
        return new self($roomId, $wsEndpoint, $userName, $userRole, $userColor, $options);
    }

    public function showCursorOverlay(bool $show = true): self
    {
        $this->showCursorOverlay = $show;
        return $this;
    }

    public function showAvatarStack(bool $show = true): self
    {
        $this->showAvatarStack = $show;
        return $this;
    }

    public function showBroadcastDock(bool $show = true): self
    {
        $this->showBroadcastDock = $show;
        return $this;
    }

    public function render(): string
    {
        $uid = 'mp_' . substr(md5($this->roomId . uniqid('', true)), 0, 8);
        $escapedRoomId = htmlspecialchars($this->roomId, ENT_QUOTES, 'UTF-8');
        $escapedWs = htmlspecialchars($this->wsEndpoint, ENT_QUOTES, 'UTF-8');
        $escapedName = htmlspecialchars($this->userName, ENT_QUOTES, 'UTF-8');
        $escapedRole = htmlspecialchars($this->userRole, ENT_QUOTES, 'UTF-8');
        $userColor = $this->userColor ?? Peer::generateColor($this->userName);

        $avatarStackHtml = '';
        if ($this->showAvatarStack) {
            $avatarStackHtml = <<<HTML
            <!-- Peer Presence Avatar Stack -->
            <div id="{$uid}_presence_bar" class="flex items-center gap-2 bg-slate-900/80 backdrop-blur-md px-3 py-1.5 rounded-full border border-slate-700/60 shadow-lg select-none">
                <div class="flex items-center gap-1.5 pr-2 border-r border-slate-700/80 text-xs font-medium text-slate-300">
                    <span id="{$uid}_status_dot" class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="font-mono text-cyan-400 font-semibold uppercase tracking-wider text-[10px]">ROOM: {$escapedRoomId}</span>
                </div>
                <!-- Connected Avatars Container -->
                <div id="{$uid}_peer_avatars" class="flex -space-x-2 overflow-hidden">
                    <div class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold text-white shadow-sm ring-2 ring-slate-900" style="background: {$userColor};" title="You ({$escapedName})">
                        {$escapedName[0]}
                    </div>
                </div>
                <!-- Peer Count Badge -->
                <span id="{$uid}_peer_count" class="text-xs font-mono font-medium text-slate-400 px-1.5 py-0.5 rounded-md bg-slate-800">1 Online</span>
            </div>
HTML;
        }

        $broadcastDockHtml = '';
        if ($this->showBroadcastDock) {
            $broadcastDockHtml = <<<HTML
            <!-- Floating Reactions / Bursts Dock -->
            <div id="{$uid}_reactions" class="flex items-center gap-1 bg-slate-900/80 backdrop-blur-md p-1 rounded-full border border-slate-700/60 shadow-lg text-sm select-none">
                <button type="button" class="reaction-btn hover:scale-125 transition-transform p-1 rounded-full hover:bg-slate-800" data-emoji="🔥">🔥</button>
                <button type="button" class="reaction-btn hover:scale-125 transition-transform p-1 rounded-full hover:bg-slate-800" data-emoji="⚡">⚡</button>
                <button type="button" class="reaction-btn hover:scale-125 transition-transform p-1 rounded-full hover:bg-slate-800" data-emoji="🚀">🚀</button>
                <button type="button" class="reaction-btn hover:scale-125 transition-transform p-1 rounded-full hover:bg-slate-800" data-emoji="❤️">❤️</button>
                <button type="button" class="reaction-btn hover:scale-125 transition-transform p-1 rounded-full hover:bg-slate-800" data-emoji="👋">👋</button>
            </div>
HTML;
        }

        $cursorLayerHtml = '';
        if ($this->showCursorOverlay) {
            $cursorLayerHtml = <<<HTML
            <!-- SVG Live Cursors Overlay Container -->
            <div id="{$uid}_cursor_layer" class="pointer-events-none fixed inset-0 z-50 overflow-hidden"></div>
            <!-- Reaction Emoji Burst Layer -->
            <div id="{$uid}_burst_layer" class="pointer-events-none fixed inset-0 z-50 overflow-hidden"></div>
HTML;
        }

        return <<<HTML
<div id="{$uid}_wrapper" class="oshim-multiplayer-container relative w-full flex items-center justify-between p-2">
    {$avatarStackHtml}
    {$broadcastDockHtml}
    {$cursorLayerHtml}

    <!-- Autonomous Sovereign Multiplayer & Presence Client Runtime -->
    <script>
    (function() {
        const uid = "{$uid}";
        const roomId = "{$escapedRoomId}";
        const wsEndpoint = "{$escapedWs}";
        const selfPeer = {
            id: "p_" + Math.random().toString(36).substr(2, 9),
            name: "{$escapedName}",
            role: "{$escapedRole}",
            color: "{$userColor}"
        };

        const peers = new Map();
        const cursorLayer = document.getElementById(uid + "_cursor_layer");
        const burstLayer = document.getElementById(uid + "_burst_layer");
        const avatarsContainer = document.getElementById(uid + "_peer_avatars");
        const peerCountEl = document.getElementById(uid + "_peer_count");
        const statusDot = document.getElementById(uid + "_status_dot");

        let ws = null;
        let isConnected = false;
        let lastCursorSent = 0;

        function updatePeerCount() {
            if (peerCountEl) {
                const total = peers.size + 1;
                peerCountEl.textContent = total + (total === 1 ? " Online" : " Online");
            }
        }

        function renderAvatar(peer) {
            if (!avatarsContainer) return;
            let el = document.getElementById(uid + "_av_" + peer.id);
            if (!el) {
                el = document.createElement("div");
                el.id = uid + "_av_" + peer.id;
                el.className = "inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold text-white shadow-sm ring-2 ring-slate-900 transition-all duration-300";
                el.style.backgroundColor = peer.color || "#00f2fe";
                el.title = peer.name + " (" + (peer.role || "member") + ")";
                el.textContent = (peer.name || "U")[0].toUpperCase();
                avatarsContainer.appendChild(el);
            }
        }

        function removeAvatar(peerId) {
            const el = document.getElementById(uid + "_av_" + peerId);
            if (el) el.remove();
        }

        function renderCursor(peerId, name, color, presence) {
            if (!cursorLayer) return;
            let cursorEl = document.getElementById(uid + "_cur_" + peerId);
            if (!cursorEl) {
                cursorEl = document.createElement("div");
                cursorEl.id = uid + "_cur_" + peerId;
                cursorEl.className = "absolute transition-[left,top] duration-75 ease-out flex items-center gap-1.5 select-none";
                cursorEl.style.zIndex = "9999";
                cursorEl.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="\${color}" stroke="#ffffff" stroke-width="1.5" class="drop-shadow-md">
                        <polygon points="0,0 24,10 13,13 10,24" />
                    </svg>
                    <span class="px-2 py-0.5 rounded-md text-[11px] font-medium font-sans text-white shadow-md" style="background:\${color}">
                        \${name}
                    </span>
                `;
                cursorLayer.appendChild(cursorEl);
            }

            const x = presence.cursorX || 0;
            const y = presence.cursorY || 0;
            cursorEl.style.left = x + "px";
            cursorEl.style.top = y + "px";

            if (presence.cursorActive === false) {
                cursorEl.style.opacity = "0";
            } else {
                cursorEl.style.opacity = "1";
            }
        }

        function removeCursor(peerId) {
            const el = document.getElementById(uid + "_cur_" + peerId);
            if (el) el.remove();
        }

        function spawnReaction(emoji, x, y) {
            if (!burstLayer) return;
            const el = document.createElement("div");
            el.textContent = emoji;
            el.className = "absolute text-2xl animate-bounce select-none transition-all duration-1000";
            el.style.left = (x || (window.innerWidth / 2)) + "px";
            el.style.top = (y || (window.innerHeight / 2)) + "px";
            burstLayer.appendChild(el);
            setTimeout(() => {
                el.style.opacity = "0";
                el.style.transform = "translateY(-60px) scale(1.5)";
                setTimeout(() => el.remove(), 1000);
            }, 100);
        }

        // Connect WebSocket
        function connect() {
            try {
                ws = new WebSocket(wsEndpoint);

                ws.onopen = function() {
                    isConnected = true;
                    if (statusDot) {
                        statusDot.className = "w-2 h-2 rounded-full bg-emerald-400 animate-pulse";
                    }
                    // Send join message
                    ws.send(JSON.stringify({
                        type: "join",
                        roomId: roomId,
                        senderId: selfPeer.id,
                        payload: { peer: selfPeer }
                    }));
                };

                ws.onmessage = function(event) {
                    try {
                        const msg = JSON.parse(event.data);
                        if (!msg || !msg.type) return;

                        if (msg.type === "state_sync") {
                            const peerList = msg.payload?.peers || [];
                            peerList.forEach(p => {
                                if (p.id !== selfPeer.id) {
                                    peers.set(p.id, p);
                                    renderAvatar(p);
                                }
                            });
                            updatePeerCount();
                        } else if (msg.type === "join") {
                            const p = msg.payload?.peer;
                            if (p && p.id !== selfPeer.id) {
                                peers.set(p.id, p);
                                renderAvatar(p);
                                updatePeerCount();
                            }
                        } else if (msg.type === "presence") {
                            const pid = msg.senderId;
                            if (pid && pid !== selfPeer.id) {
                                renderCursor(pid, msg.payload?.name || "Peer", msg.payload?.color || "#00f2fe", msg.payload?.presence || {});
                            }
                        } else if (msg.type === "broadcast" && msg.payload?.type === "reaction") {
                            spawnReaction(msg.payload.emoji, msg.payload.x, msg.payload.y);
                        } else if (msg.type === "leave") {
                            const pid = msg.payload?.peerId || msg.senderId;
                            if (pid) {
                                peers.delete(pid);
                                removeAvatar(pid);
                                removeCursor(pid);
                                updatePeerCount();
                            }
                        }
                    } catch (e) {
                        console.error("Multiplayer message parse error:", e);
                    }
                };

                ws.onclose = function() {
                    isConnected = false;
                    if (statusDot) {
                        statusDot.className = "w-2 h-2 rounded-full bg-amber-400";
                    }
                    setTimeout(connect, 3000); // Auto reconnect
                };

                ws.onerror = function() {
                    isConnected = false;
                    if (statusDot) {
                        statusDot.className = "w-2 h-2 rounded-full bg-rose-500";
                    }
                };
            } catch (err) {
                // Standalone / offline fallback simulation
                if (statusDot) {
                    statusDot.className = "w-2 h-2 rounded-full bg-cyan-400";
                }
            }
        }

        connect();

        // Throttled mouse cursor broadcasting (30ms rate limit)
        window.addEventListener("mousemove", (e) => {
            const now = performance.now();
            if (now - lastCursorSent < 30) return;
            lastCursorSent = now;

            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({
                    type: "presence",
                    roomId: roomId,
                    senderId: selfPeer.id,
                    payload: {
                        name: selfPeer.name,
                        color: selfPeer.color,
                        presence: {
                            cursorX: e.clientX,
                            cursorY: e.clientY,
                            cursorActive: true,
                            cursorState: "default"
                        }
                    }
                }));
            }
        });

        // Reaction button handlers
        const reactionButtons = document.querySelectorAll("#" + uid + "_reactions .reaction-btn");
        reactionButtons.forEach(btn => {
            btn.addEventListener("click", () => {
                const emoji = btn.getAttribute("data-emoji") || "⚡";
                spawnReaction(emoji, window.innerWidth / 2, window.innerHeight / 2);
                if (ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(JSON.stringify({
                        type: "broadcast",
                        roomId: roomId,
                        senderId: selfPeer.id,
                        payload: {
                            type: "reaction",
                            emoji: emoji,
                            x: window.innerWidth / 2,
                            y: window.innerHeight / 2
                        }
                    }));
                }
            });
        });

        // Periodic heartbeat ping (every 10s)
        setInterval(() => {
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({
                    type: "heartbeat",
                    roomId: roomId,
                    senderId: selfPeer.id
                }));
            }
        }, 10000);
    })();
    </script>
</div>
HTML;
    }
}
