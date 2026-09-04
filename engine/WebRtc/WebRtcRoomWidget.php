<?php
declare(strict_types=1);

namespace Oshim\WebRtc;

use Oshim\Ui\Dsl\Element;

/**
 * 1-Click Native HTML5/JS WebRTC Multimedia Room Widget.
 * Renders a dark neon glassmorphism video conference interface with peer grid,
 * screen sharing, audio/video controls, and live chat.
 */
class WebRtcRoomWidget extends Element
{
    private string $roomId;
    private string $wsEndpoint;
    private string $userName;
    private array $options;

    public function __construct(
        string $roomId = 'default-room',
        string $wsEndpoint = 'ws://127.0.0.1:9090',
        string $userName = 'Guest',
        array $options = []
    ) {
        parent::__construct('div');
        $this->roomId = $roomId;
        $this->wsEndpoint = $wsEndpoint;
        $this->userName = $userName;
        $this->options = $options;
        $this->class('oshim-webrtc-container');
    }

    /**
     * Fluent factory method to create a WebRTC Room Widget.
     */
    public static function room(
        string $roomId = 'default-room',
        string $wsEndpoint = 'ws://127.0.0.1:9090',
        string $userName = 'Guest',
        array $options = []
    ): self {
        return new self($roomId, $wsEndpoint, $userName, $options);
    }

    /**
     * Render the WebRTC Room Widget into responsive HTML, CSS, and sovereign JavaScript.
     */
    public function render(): string
    {
        $uid = 'oshim-webrtc-' . substr(md5($this->roomId . uniqid('', true)), 0, 8);
        $escapedRoomId = htmlspecialchars($this->roomId, ENT_QUOTES, 'UTF-8');
        $escapedWs = htmlspecialchars($this->wsEndpoint, ENT_QUOTES, 'UTF-8');
        $escapedUser = htmlspecialchars($this->userName, ENT_QUOTES, 'UTF-8');
        $iceServersJson = json_encode($this->options['iceServers'] ?? [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun1.l.google.com:19302'],
        ], JSON_UNESCAPED_SLASHES);

        return <<<HTML
<div id="{$uid}" class="oshim-webrtc-widget" style="display:flex; flex-direction:column; width:100%; height:100%; min-height:650px; background:#0b0f19; border:1px solid rgba(255,255,255,0.08); border-radius:20px; overflow:hidden; font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:#f8fafc; position:relative; box-shadow:0 25px 50px -12px rgba(0,0,0,0.7);">
    <!-- Room Header -->
    <header style="padding:1rem 1.5rem; background:rgba(15,23,42,0.85); backdrop-filter:blur(16px); border-bottom:1px solid rgba(255,255,255,0.08); display:flex; justify-content:space-between; align-items:center; z-index:10;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:12px; height:12px; border-radius:50%; background:#00e676; box-shadow:0 0 12px #00e676;"></div>
            <div>
                <h2 style="font-size:1.1rem; font-weight:700; margin:0; letter-spacing:-0.02em; color:#ffffff;">Room: {$escapedRoomId}</h2>
                <span style="font-size:0.75rem; color:#94a3b8;">Sovereign WebRTC Mesh • Encrypted DTLS/SRTP</span>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:12px;">
            <span id="{$uid}-peer-count" style="font-size:0.8rem; padding:4px 10px; border-radius:9999px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); color:#cbd5e1;">👥 1 Participant</span>
            <span style="font-size:0.8rem; padding:4px 10px; border-radius:9999px; background:rgba(0,242,254,0.15); color:#00f2fe; border:1px solid rgba(0,242,254,0.3); font-weight:600;">{$escapedUser}</span>
        </div>
    </header>

    <!-- Main Multimedia Canvas & Chat Drawer -->
    <div style="flex:1; display:flex; position:relative; overflow:hidden;">
        <!-- Video Grid Container -->
        <main id="{$uid}-video-grid" style="flex:1; padding:1.5rem; display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1.25rem; align-content:center; justify-content:center; overflow-y:auto; background:radial-gradient(ellipse at center, #111827 0%, #030712 100%);">
            <!-- Local Participant Box -->
            <div id="{$uid}-local-box" style="position:relative; aspect-ratio:16/9; background:#1e293b; border-radius:16px; overflow:hidden; border:2px solid rgba(0,242,254,0.4); box-shadow:0 10px 25px -5px rgba(0,0,0,0.5);">
                <video id="{$uid}-local-video" autoplay playsinline muted style="width:100%; height:100%; object-fit:cover; transform:scaleX(-1);"></video>
                <div style="position:absolute; bottom:12px; left:12px; padding:4px 10px; background:rgba(15,23,42,0.75); backdrop-filter:blur(8px); border-radius:8px; font-size:0.8rem; font-weight:600; display:flex; align-items:center; gap:6px;">
                    <span>{$escapedUser} (You)</span>
                    <span id="{$uid}-local-mic-status">🎙️</span>
                </div>
            </div>
        </main>

        <!-- Live Chat Sidebar / Drawer -->
        <aside id="{$uid}-chat-drawer" style="width:320px; background:rgba(15,23,42,0.95); backdrop-filter:blur(20px); border-left:1px solid rgba(255,255,255,0.08); display:none; flex-direction:column; z-index:20;">
            <div style="padding:1rem; border-bottom:1px solid rgba(255,255,255,0.08); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="font-size:0.95rem; font-weight:700; margin:0; color:#f8fafc;">Live Chat</h3>
                <button type="button" onclick="document.getElementById('{$uid}-chat-drawer').style.display='none';" style="background:transparent; border:none; color:#94a3b8; font-size:1.2rem; cursor:pointer;">&times;</button>
            </div>
            <div id="{$uid}-chat-messages" style="flex:1; padding:1rem; overflow-y:auto; display:flex; flex-direction:column; gap:0.75rem; font-size:0.85rem;">
                <div style="background:rgba(255,255,255,0.04); padding:0.6rem 0.8rem; border-radius:8px; color:#94a3b8; font-size:0.8rem; text-align:center;">
                    Welcome to the room chat! Messages are peer-routed in real-time.
                </div>
            </div>
            <div style="padding:0.75rem 1rem; border-top:1px solid rgba(255,255,255,0.08); display:flex; gap:0.5rem;">
                <input type="text" id="{$uid}-chat-input" placeholder="Type a message..." style="flex:1; padding:0.6rem 0.85rem; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:8px; color:#ffffff; font-size:0.85rem; outline:none;" />
                <button type="button" id="{$uid}-chat-send" style="padding:0.6rem 1rem; background:#00f2fe; color:#020617; font-weight:700; border:none; border-radius:8px; cursor:pointer;">Send</button>
            </div>
        </aside>
    </div>

    <!-- Floating Glass Control Dock -->
    <footer style="padding:1rem 1.5rem; background:rgba(15,23,42,0.9); backdrop-filter:blur(20px); border-top:1px solid rgba(255,255,255,0.08); display:flex; justify-content:center; align-items:center; gap:1rem; z-index:10;">
        <button type="button" id="{$uid}-btn-audio" style="display:flex; align-items:center; gap:6px; padding:0.75rem 1.25rem; border-radius:12px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:#ffffff; font-weight:600; font-size:0.9rem; cursor:pointer; transition:all 0.15s ease;">
            <span>🎙️</span> <span id="{$uid}-txt-audio">Mute</span>
        </button>
        <button type="button" id="{$uid}-btn-video" style="display:flex; align-items:center; gap:6px; padding:0.75rem 1.25rem; border-radius:12px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:#ffffff; font-weight:600; font-size:0.9rem; cursor:pointer; transition:all 0.15s ease;">
            <span>📹</span> <span id="{$uid}-txt-video">Stop Video</span>
        </button>
        <button type="button" id="{$uid}-btn-screen" style="display:flex; align-items:center; gap:6px; padding:0.75rem 1.25rem; border-radius:12px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:#ffffff; font-weight:600; font-size:0.9rem; cursor:pointer; transition:all 0.15s ease;">
            <span>🖥️</span> <span id="{$uid}-txt-screen">Share Screen</span>
        </button>
        <button type="button" id="{$uid}-btn-chat" style="display:flex; align-items:center; gap:6px; padding:0.75rem 1.25rem; border-radius:12px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:#ffffff; font-weight:600; font-size:0.9rem; cursor:pointer; transition:all 0.15s ease;">
            <span>💬</span> <span>Chat</span>
        </button>
        <button type="button" id="{$uid}-btn-leave" style="display:flex; align-items:center; gap:6px; padding:0.75rem 1.25rem; border-radius:12px; background:#ef4444; border:none; color:#ffffff; font-weight:700; font-size:0.9rem; cursor:pointer; transition:all 0.15s ease;">
            <span>📞</span> <span>Leave</span>
        </button>
    </footer>

    <!-- Sovereign Native WebRTC Client Engine Script -->
    <script>
    (function() {
        var roomId = '{$escapedRoomId}';
        var wsUrl = '{$escapedWs}';
        var userName = '{$escapedUser}';
        var peerId = 'peer_' + Math.random().toString(36).substr(2, 9);
        var rtcConfig = { iceServers: {$iceServersJson} };

        var localStream = null;
        var screenStream = null;
        var ws = null;
        var peerConnections = {}; // targetPeerId -> RTCPeerConnection
        var audioEnabled = true;
        var videoEnabled = true;
        var isScreenSharing = false;

        var localVideo = document.getElementById('{$uid}-local-video');
        var videoGrid = document.getElementById('{$uid}-video-grid');
        var chatDrawer = document.getElementById('{$uid}-chat-drawer');
        var chatMessages = document.getElementById('{$uid}-chat-messages');
        var chatInput = document.getElementById('{$uid}-chat-input');
        var btnAudio = document.getElementById('{$uid}-btn-audio');
        var btnVideo = document.getElementById('{$uid}-btn-video');
        var btnScreen = document.getElementById('{$uid}-btn-screen');
        var btnChat = document.getElementById('{$uid}-btn-chat');
        var btnLeave = document.getElementById('{$uid}-btn-leave');
        var peerCountBadge = document.getElementById('{$uid}-peer-count');

        // Initialize local camera and microphone
        async function initMedia() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
                localVideo.srcObject = localStream;
            } catch (err) {
                console.warn('[WebRtcRoomWidget] Audio/Video access denied or unavailable:', err);
                try {
                    localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                } catch (e) {
                    console.warn('[WebRtcRoomWidget] Fallback audio-only failed:', e);
                }
            }
            initSignaling();
        }

        // Initialize WebSocket Signaling
        function initSignaling() {
            try {
                ws = new WebSocket(wsUrl);
            } catch (e) {
                console.error('[WebRtcRoomWidget] WebSocket connection failed:', e);
                return;
            }

            ws.onopen = function() {
                sendSignaling({
                    action: 'join_room',
                    roomId: roomId,
                    peerId: peerId,
                    userName: userName,
                    metadata: { mutedAudio: !audioEnabled, mutedVideo: !videoEnabled, screenSharing: isScreenSharing }
                });
            };

            ws.onmessage = async function(evt) {
                try {
                    var data = JSON.parse(evt.data);
                    handleSignalingMessage(data);
                } catch (err) {
                    console.error('[WebRtcRoomWidget] Message parse error:', err);
                }
            };

            ws.onclose = function() {
                console.log('[WebRtcRoomWidget] Signaling connection closed.');
            };
        }

        function sendSignaling(msg) {
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify(msg));
            }
        }

        async function handleSignalingMessage(data) {
            var action = data.action || data.type;
            switch (action) {
                case 'room_joined':
                    updatePeerCount((data.existingPeers ? data.existingPeers.length : 0) + 1);
                    if (data.existingPeers && data.existingPeers.length > 0) {
                        for (var i = 0; i < data.existingPeers.length; i++) {
                            var target = data.existingPeers[i];
                            await initiateCall(target.peerId, target.userName);
                        }
                    }
                    break;

                case 'peer_joined':
                    updatePeerCount(Object.keys(peerConnections).length + 2);
                    appendChatMessage('System', data.peer.userName + ' joined the room.');
                    break;

                case 'peer_left':
                    removePeer(data.peerId);
                    updatePeerCount(Object.keys(peerConnections).length + 1);
                    appendChatMessage('System', 'A peer left the room.');
                    break;

                case 'offer':
                    await handleOffer(data);
                    break;

                case 'answer':
                    await handleAnswer(data);
                    break;

                case 'ice_candidate':
                    await handleCandidate(data);
                    break;

                case 'screen_share_changed':
                    break;

                case 'peer_mute_status':
                    break;

                case 'chat_message':
                    appendChatMessage(data.userName || 'Peer', data.text || '');
                    break;
            }
        }

        function createPeerConnection(targetPeerId, targetName) {
            if (peerConnections[targetPeerId]) {
                return peerConnections[targetPeerId];
            }

            var pc = new RTCPeerConnection(rtcConfig);
            peerConnections[targetPeerId] = pc;

            if (localStream) {
                localStream.getTracks().forEach(function(track) {
                    pc.addTrack(track, localStream);
                });
            }

            pc.onicecandidate = function(event) {
                if (event.candidate) {
                    sendSignaling({
                        action: 'ice_candidate',
                        sessionId: 'sess_' + [peerId, targetPeerId].sort().join('_'),
                        fromPeer: peerId,
                        toPeer: targetPeerId,
                        candidate: event.candidate
                    });
                }
            };

            pc.ontrack = function(event) {
                var remoteBoxId = '{$uid}-box-' + targetPeerId;
                var videoElem = document.getElementById('{$uid}-video-' + targetPeerId);
                if (!videoElem) {
                    var box = document.createElement('div');
                    box.id = remoteBoxId;
                    box.style = 'position:relative; aspect-ratio:16/9; background:#1e293b; border-radius:16px; overflow:hidden; border:2px solid rgba(255,255,255,0.1); box-shadow:0 10px 25px -5px rgba(0,0,0,0.5);';

                    videoElem = document.createElement('video');
                    videoElem.id = '{$uid}-video-' + targetPeerId;
                    videoElem.autoplay = true;
                    videoElem.playsInline = true;
                    videoElem.style = 'width:100%; height:100%; object-fit:cover;';

                    var badge = document.createElement('div');
                    badge.style = 'position:absolute; bottom:12px; left:12px; padding:4px 10px; background:rgba(15,23,42,0.75); backdrop-filter:blur(8px); border-radius:8px; font-size:0.8rem; font-weight:600;';
                    badge.textContent = targetName || targetPeerId;

                    box.appendChild(videoElem);
                    box.appendChild(badge);
                    videoGrid.appendChild(box);
                }

                if (event.streams && event.streams[0]) {
                    videoElem.srcObject = event.streams[0];
                }
            };

            return pc;
        }

        async function initiateCall(targetPeerId, targetName) {
            var pc = createPeerConnection(targetPeerId, targetName);
            var offer = await pc.createOffer();
            await pc.setLocalDescription(offer);

            sendSignaling({
                action: 'offer',
                sessionId: 'sess_' + [peerId, targetPeerId].sort().join('_'),
                fromPeer: peerId,
                toPeer: targetPeerId,
                sdp: offer.sdp,
                roomId: roomId
            });
        }

        async function handleOffer(data) {
            var pc = createPeerConnection(data.fromPeer, data.fromPeer);
            await pc.setRemoteDescription(new RTCSessionDescription({ type: 'offer', sdp: data.sdp }));
            var answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);

            sendSignaling({
                action: 'answer',
                sessionId: data.sessionId,
                fromPeer: peerId,
                toPeer: data.fromPeer,
                sdp: answer.sdp,
                roomId: roomId
            });
        }

        async function handleAnswer(data) {
            var pc = peerConnections[data.fromPeer];
            if (pc) {
                await pc.setRemoteDescription(new RTCSessionDescription({ type: 'answer', sdp: data.sdp }));
            }
        }

        async function handleCandidate(data) {
            var pc = peerConnections[data.fromPeer];
            if (pc && data.candidate) {
                try {
                    await pc.addIceCandidate(new RTCIceCandidate(data.candidate));
                } catch (e) {
                    console.warn('[WebRtcRoomWidget] Error adding ICE candidate:', e);
                }
            }
        }

        function removePeer(targetPeerId) {
            if (peerConnections[targetPeerId]) {
                peerConnections[targetPeerId].close();
                delete peerConnections[targetPeerId];
            }
            var elem = document.getElementById('{$uid}-box-' + targetPeerId);
            if (elem) {
                elem.remove();
            }
        }

        function updatePeerCount(count) {
            peerCountBadge.textContent = '👥 ' + count + (count === 1 ? ' Participant' : ' Participants');
        }

        function appendChatMessage(sender, text) {
            var item = document.createElement('div');
            var isMe = sender === userName;
            item.style = 'align-self:' + (isMe ? 'flex-end' : 'flex-start') + '; max-width:85%; background:' + (isMe ? 'rgba(0,242,254,0.15)' : 'rgba(255,255,255,0.06)') + '; padding:0.5rem 0.75rem; border-radius:10px; border:1px solid ' + (isMe ? 'rgba(0,242,254,0.3)' : 'rgba(255,255,255,0.08)') + ';';
            item.innerHTML = '<strong style="color:' + (isMe ? '#00f2fe' : '#94a3b8') + '; font-size:0.75rem;">' + sender + '</strong><div style="color:#ffffff; margin-top:2px;">' + text + '</div>';
            chatMessages.appendChild(item);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Event Listeners for UI Controls
        btnAudio.onclick = function() {
            audioEnabled = !audioEnabled;
            if (localStream) {
                localStream.getAudioTracks().forEach(function(t) { t.enabled = audioEnabled; });
            }
            document.getElementById('{$uid}-txt-audio').textContent = audioEnabled ? 'Mute' : 'Unmute';
            document.getElementById('{$uid}-local-mic-status').textContent = audioEnabled ? '🎙️' : '🔇';
            btnAudio.style.background = audioEnabled ? 'rgba(255,255,255,0.08)' : 'rgba(239,68,68,0.2)';
            sendSignaling({ action: 'mute_toggle', roomId: roomId, peerId: peerId, track: 'audio', muted: !audioEnabled });
        };

        btnVideo.onclick = function() {
            videoEnabled = !videoEnabled;
            if (localStream) {
                localStream.getVideoTracks().forEach(function(t) { t.enabled = videoEnabled; });
            }
            document.getElementById('{$uid}-txt-video').textContent = videoEnabled ? 'Stop Video' : 'Start Video';
            btnVideo.style.background = videoEnabled ? 'rgba(255,255,255,0.08)' : 'rgba(239,68,68,0.2)';
            sendSignaling({ action: 'mute_toggle', roomId: roomId, peerId: peerId, track: 'video', muted: !videoEnabled });
        };

        btnScreen.onclick = async function() {
            if (!isScreenSharing) {
                try {
                    screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                    var screenTrack = screenStream.getVideoTracks()[0];
                    for (var id in peerConnections) {
                        var senders = peerConnections[id].getSenders();
                        var videoSender = senders.find(function(s) { return s.track && s.track.kind === 'video'; });
                        if (videoSender) {
                            videoSender.replaceTrack(screenTrack);
                        }
                    }
                    localVideo.srcObject = screenStream;
                    localVideo.style.transform = 'none';
                    isScreenSharing = true;
                    document.getElementById('{$uid}-txt-screen').textContent = 'Stop Share';
                    btnScreen.style.background = 'rgba(0,242,254,0.2)';
                    sendSignaling({ action: 'screen_share', roomId: roomId, peerId: peerId, active: true });

                    screenTrack.onended = function() {
                        btnScreen.click();
                    };
                } catch (e) {
                    console.warn('[WebRtcRoomWidget] Screen share cancelled or failed:', e);
                }
            } else {
                if (screenStream) {
                    screenStream.getTracks().forEach(function(t) { t.stop(); });
                }
                if (localStream) {
                    var localVideoTrack = localStream.getVideoTracks()[0];
                    for (var id in peerConnections) {
                        var senders = peerConnections[id].getSenders();
                        var videoSender = senders.find(function(s) { return s.track && s.track.kind === 'video'; });
                        if (videoSender && localVideoTrack) {
                            videoSender.replaceTrack(localVideoTrack);
                        }
                    }
                    localVideo.srcObject = localStream;
                    localVideo.style.transform = 'scaleX(-1)';
                }
                isScreenSharing = false;
                document.getElementById('{$uid}-txt-screen').textContent = 'Share Screen';
                btnScreen.style.background = 'rgba(255,255,255,0.08)';
                sendSignaling({ action: 'screen_share', roomId: roomId, peerId: peerId, active: false });
            }
        };

        btnChat.onclick = function() {
            chatDrawer.style.display = chatDrawer.style.display === 'none' || chatDrawer.style.display === '' ? 'flex' : 'none';
        };

        btnLeave.onclick = function() {
            if (localStream) {
                localStream.getTracks().forEach(function(t) { t.stop(); });
            }
            if (screenStream) {
                screenStream.getTracks().forEach(function(t) { t.stop(); });
            }
            for (var id in peerConnections) {
                peerConnections[id].close();
            }
            peerConnections = {};
            sendSignaling({ action: 'leave_room', roomId: roomId, peerId: peerId });
            if (ws) {
                ws.close();
            }
            videoGrid.innerHTML = '<div style="grid-column:1/-1; text-align:center; color:#94a3b8; padding:3rem;"><h3 style="color:#ffffff; margin-bottom:0.5rem;">Call Ended</h3><p>You have left the WebRTC room.</p></div>';
        };

        function sendChat() {
            var val = chatInput.value.trim();
            if (!val) return;
            chatInput.value = '';
            sendSignaling({ action: 'chat_message', roomId: roomId, peerId: peerId, userName: userName, text: val });
        }

        document.getElementById('{$uid}-chat-send').onclick = sendChat;
        chatInput.onkeydown = function(e) { if (e.key === 'Enter') sendChat(); };

        // Auto start
        initMedia();
    })();
    </script>
</div>
HTML;
    }
}
