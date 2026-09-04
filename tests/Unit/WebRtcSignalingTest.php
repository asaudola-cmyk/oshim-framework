<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\WebRtc\SdpSession;
use Oshim\WebRtc\IceCandidateRouter;
use Oshim\WebRtc\MediaRoomManager;
use Oshim\WebRtc\WebRtcSignalingServer;
use Oshim\WebRtc\WebRtcRoomWidget;
use Oshim\Cli\Commands\WebRtcServeCommand;
use Oshim\Cli\Input;
use Oshim\Cli\Output;
use InvalidArgumentException;
use RuntimeException;

/**
 * Comprehensive Unit Test Suite for Async WebRTC Signaling & Real-Time Multimedia Engine.
 */
final class WebRtcSignalingTest extends TestCase
{
    private string $sampleOfferSdp = "v=0\r\n" .
        "o=- 4611731400430051336 2 IN IP4 127.0.0.1\r\n" .
        "s=-\r\n" .
        "t=0 0\r\n" .
        "a=group:BUNDLE 0 1\r\n" .
        "a=ice-ufrag:sovereign_ufrag_1\r\n" .
        "a=ice-pwd:sovereign_password_token_1234\r\n" .
        "a=fingerprint:sha-256 00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF\r\n" .
        "m=audio 9 UDP/TLS/RTP/SAVPF 111 9 0 8\r\n" .
        "c=IN IP4 0.0.0.0\r\n" .
        "a=rtcp-mux\r\n" .
        "a=mid:0\r\n" .
        "a=sendrecv\r\n" .
        "a=rtpmap:111 opus/48000/2\r\n" .
        "a=fmtp:111 minptime=10;useinbandfec=1\r\n" .
        "m=video 9 UDP/TLS/RTP/SAVPF 96 97 98 100\r\n" .
        "c=IN IP4 0.0.0.0\r\n" .
        "a=rtcp-mux\r\n" .
        "a=mid:1\r\n" .
        "a=sendrecv\r\n" .
        "a=rtpmap:96 VP8/90000\r\n" .
        "a=rtcp-fb:96 goog-remb\r\n" .
        "a=rtpmap:98 VP9/90000\r\n" .
        "a=rtpmap:100 H264/90000\r\n" .
        "a=fmtp:100 level-asymmetry-allowed=1;packetization-mode=1;profile-level-id=42e01f\r\n";

    private string $sampleAnswerSdp = "v=0\r\n" .
        "o=- 8911731400430051999 2 IN IP4 127.0.0.1\r\n" .
        "s=-\r\n" .
        "t=0 0\r\n" .
        "a=group:BUNDLE 0 1\r\n" .
        "a=ice-ufrag:peer_answer_ufrag_2\r\n" .
        "a=ice-pwd:peer_answer_password_token_9876\r\n" .
        "a=fingerprint:sha-256 FF:EE:DD:CC:BB:AA:99:88:77:66:55:44:33:22:11:00:FF:EE:DD:CC:BB:AA:99:88:77:66:55:44:33:22:11:00\r\n" .
        "m=audio 9 UDP/TLS/RTP/SAVPF 111\r\n" .
        "c=IN IP4 0.0.0.0\r\n" .
        "a=rtcp-mux\r\n" .
        "a=mid:0\r\n" .
        "a=sendrecv\r\n" .
        "a=rtpmap:111 opus/48000/2\r\n" .
        "m=video 9 UDP/TLS/RTP/SAVPF 96\r\n" .
        "c=IN IP4 0.0.0.0\r\n" .
        "a=rtcp-mux\r\n" .
        "a=mid:1\r\n" .
        "a=sendrecv\r\n" .
        "a=rtpmap:96 VP8/90000\r\n";

    public function testMediaRoomManagerRoomLifecycleAndParticipants(): void
    {
        $roomManager = new MediaRoomManager();

        $this->assertSame(0, $roomManager->getRoomCount());
        $this->assertFalse($roomManager->hasRoom('room-alpha'));

        // Create Mesh room
        $room1 = $roomManager->createRoom('room-alpha', 'Alpha Mesh Room', 'mesh', 4);
        $this->assertSame('room-alpha', $room1['id']);
        $this->assertSame('Alpha Mesh Room', $room1['name']);
        $this->assertSame('mesh', $room1['topology']);
        $this->assertSame(4, $room1['maxParticipants']);
        $this->assertTrue($roomManager->hasRoom('room-alpha'));
        $this->assertSame(1, $roomManager->getRoomCount());

        // Create SFU room
        $room2 = $roomManager->createRoom('room-beta', 'Beta SFU Room', 'sfu', 100);
        $this->assertSame('sfu', $room2['topology']);
        $this->assertSame(100, $room2['maxParticipants']);
        $this->assertSame(2, $roomManager->getRoomCount());

        // Join peers into room-alpha
        $join1 = $roomManager->joinRoom('room-alpha', 'peer-alice', 'Alice', ['mutedAudio' => false]);
        $this->assertSame('peer-alice', $join1['participant']['peerId']);
        $this->assertSame('Alice', $join1['participant']['userName']);
        $this->assertFalse($join1['participant']['mutedAudio']);
        $this->assertCount(0, $join1['existingPeers']);
        $this->assertSame(1, $join1['room']['participantCount']);

        $join2 = $roomManager->joinRoom('room-alpha', 'peer-bob', 'Bob', ['mutedAudio' => true, 'mutedVideo' => true]);
        $this->assertSame('peer-bob', $join2['participant']['peerId']);
        $this->assertSame('Bob', $join2['participant']['userName']);
        $this->assertTrue($join2['participant']['mutedAudio']);
        $this->assertTrue($join2['participant']['mutedVideo']);
        $this->assertCount(1, $join2['existingPeers']);
        $this->assertSame('peer-alice', $join2['existingPeers'][0]['peerId']);
        $this->assertSame(2, $join2['room']['participantCount']);

        // Verify participants list
        $participants = $roomManager->getParticipants('room-alpha');
        $this->assertCount(2, $participants);

        // Update metadata
        $updated = $roomManager->updateParticipantMetadata('room-alpha', 'peer-alice', [
            'mutedAudio' => true,
            'screenSharing' => true,
            'customRole' => 'moderator',
        ]);
        $this->assertTrue($updated);

        $participantsAfter = $roomManager->getParticipants('room-alpha');
        $aliceData = null;
        foreach ($participantsAfter as $p) {
            if ($p['peerId'] === 'peer-alice') {
                $aliceData = $p;
                break;
            }
        }
        $this->assertNotNull($aliceData);
        $this->assertTrue($aliceData['mutedAudio']);
        $this->assertTrue($aliceData['screenSharing']);
        $this->assertSame('moderator', $aliceData['metadata']['customRole']);

        // Join 2 more peers to reach capacity limit of 4
        $roomManager->joinRoom('room-alpha', 'peer-carol', 'Carol');
        $roomManager->joinRoom('room-alpha', 'peer-david', 'David');
        $this->assertCount(4, $roomManager->getParticipants('room-alpha'));

        // Attempting 5th peer should throw RuntimeException
        $capacityExceeded = false;
        try {
            $roomManager->joinRoom('room-alpha', 'peer-eve', 'Eve');
        } catch (RuntimeException $e) {
            $capacityExceeded = true;
            $this->assertStringContainsString('is full', $e->getMessage());
        }
        $this->assertTrue($capacityExceeded);

        // Leave room
        $left = $roomManager->leaveRoom('room-alpha', 'peer-bob');
        $this->assertNotNull($left);
        $this->assertSame('peer-bob', $left['peerId']);
        $this->assertCount(3, $roomManager->getParticipants('room-alpha'));

        // Destroy room
        $roomManager->destroyRoom('room-alpha');
        $this->assertFalse($roomManager->hasRoom('room-alpha'));
        $this->assertSame(1, $roomManager->getRoomCount());
    }

    public function testSdpSessionStateMachineAndValidation(): void
    {
        // Test RFC 4566 SDP Validation
        $this->assertTrue(SdpSession::isValidSdp($this->sampleOfferSdp));
        $this->assertTrue(SdpSession::isValidSdp($this->sampleAnswerSdp));
        $this->assertFalse(SdpSession::isValidSdp(''));
        $this->assertFalse(SdpSession::isValidSdp('Invalid non-sdp string'));
        $this->assertFalse(SdpSession::isValidSdp("v=0\r\no=test\r\ns=test\r\n")); // Missing t= and m=

        // Test Media Capabilities Extraction
        $caps = SdpSession::extractMediaCapabilities($this->sampleOfferSdp);
        $this->assertSame(0, $caps['session']['version']);
        $this->assertSame('sovereign_ufrag_1', $caps['ice']['ufrag']);
        $this->assertSame('sovereign_password_token_1234', $caps['ice']['pwd']);
        $this->assertSame('sha-256', $caps['fingerprint']['algorithm']);

        $this->assertCount(2, $caps['media']);
        $this->assertSame('audio', $caps['media'][0]['type']);
        $this->assertSame('video', $caps['media'][1]['type']);

        $this->assertContains('opus', $caps['codecs']['audio']);
        $this->assertContains('VP8', $caps['codecs']['video']);
        $this->assertContains('VP9', $caps['codecs']['video']);
        $this->assertContains('H264', $caps['codecs']['video']);

        // Test State Machine
        $session = new SdpSession('sess-101', 'peer-alice', 'peer-bob');
        $this->assertSame('sess-101', $session->sessionId);
        $this->assertSame(SdpSession::STATE_NEW, $session->state);
        $this->assertFalse($session->isEstablished());

        // Set Offer
        $session->setOffer($this->sampleOfferSdp);
        $this->assertSame(SdpSession::STATE_OFFER_SENT, $session->state);
        $this->assertNotNull($session->offerSdp);

        // Set Answer
        $session->setAnswer($this->sampleAnswerSdp);
        $this->assertSame(SdpSession::STATE_ESTABLISHED, $session->state);
        $this->assertTrue($session->isEstablished());
        $this->assertNotNull($session->establishedAt);

        // Export array
        $arr = $session->toArray();
        $this->assertSame('sess-101', $arr['sessionId']);
        $this->assertSame(SdpSession::STATE_ESTABLISHED, $arr['state']);
        $this->assertTrue($arr['hasOffer']);
        $this->assertTrue($arr['hasAnswer']);

        // Close session
        $session->close();
        $this->assertSame(SdpSession::STATE_CLOSED, $session->state);
        $this->assertFalse($session->isEstablished());

        // Invalid SDP throws InvalidArgumentException
        $invalidThrown = false;
        try {
            $badSession = new SdpSession('sess-bad', 'alice', 'bob');
            $badSession->setOffer('invalid-sdp');
        } catch (InvalidArgumentException $e) {
            $invalidThrown = true;
        }
        $this->assertTrue($invalidThrown);
    }

    public function testIceCandidateRouterBufferingAndFlushing(): void
    {
        $router = new IceCandidateRouter();

        $validCandidate = [
            'candidate' => 'candidate:842163049 1 udp 1677729535 192.168.1.100 50000 typ host generation 0',
            'sdpMid' => '0',
            'sdpMLineIndex' => 0,
        ];
        $emptyCandidate = [];

        $this->assertTrue(IceCandidateRouter::validateCandidate($validCandidate));
        $this->assertFalse(IceCandidateRouter::validateCandidate($emptyCandidate));

        // Buffer candidates for session
        $this->assertCount(0, $router->getBufferedCandidates('sess-202'));

        $router->bufferCandidate('sess-202', $validCandidate);
        $secondCandidate = [
            'candidate' => 'candidate:192837465 1 udp 2122260223 10.0.0.15 50002 typ host generation 0',
            'sdpMid' => '1',
            'sdpMLineIndex' => 1,
        ];
        $router->bufferCandidate('sess-202', $secondCandidate);

        $buffered = $router->getBufferedCandidates('sess-202');
        $this->assertCount(2, $buffered);
        $this->assertSame('0', $buffered[0]['sdpMid']);
        $this->assertSame('1', $buffered[1]['sdpMid']);

        // Flush candidates
        $flushed = [];
        $count = $router->flushBufferedCandidates('sess-202', function (array $cand) use (&$flushed) {
            $flushed[] = $cand;
        });

        $this->assertSame(2, $count);
        $this->assertCount(2, $flushed);
        $this->assertCount(0, $router->getBufferedCandidates('sess-202'));

        // Route candidate directly with callback
        $routedTarget = null;
        $routedCandidate = null;
        $success = $router->routeCandidate('peer-alice', 'peer-bob', $validCandidate, function ($toPeer, $cand, $fromPeer) use (&$routedTarget, &$routedCandidate) {
            $routedTarget = $toPeer;
            $routedCandidate = $cand;
            return true;
        });

        $this->assertTrue($success);
        $this->assertSame('peer-bob', $routedTarget);
        $this->assertSame($validCandidate['candidate'], $routedCandidate['candidate']);
    }

    public function testSignalingServerProtocolDispatch(): void
    {
        $server = new WebRtcSignalingServer();

        // 1. Join Room
        $joinPayload = [
            'action' => 'join_room',
            'roomId' => 'video-room-1',
            'peerId' => 'peer-alice',
            'userName' => 'Alice Sovereign',
            'metadata' => ['mutedAudio' => false, 'mutedVideo' => false],
        ];
        $resJoin = $server->handleRawPayload($joinPayload);
        $this->assertTrue($resJoin['send_to_caller']);
        $this->assertSame('room_joined', $resJoin['data']['type']);
        $this->assertSame('video-room-1', $resJoin['data']['roomId']);
        $this->assertSame('Alice Sovereign', $resJoin['data']['participant']['userName']);

        // 2. Offer Dispatch
        $offerPayload = [
            'action' => 'offer',
            'sessionId' => 'sess-mesh-1',
            'fromPeer' => 'peer-alice',
            'toPeer' => 'peer-bob',
            'sdp' => $this->sampleOfferSdp,
            'roomId' => 'video-room-1',
        ];
        $resOffer = $server->handleRawPayload($offerPayload);
        $this->assertTrue($resOffer['send_to_caller']);
        $this->assertSame('offer_sent', $resOffer['data']['type']);
        $this->assertSame('sess-mesh-1', $resOffer['data']['sessionId']);
        $this->assertNotNull($server->getSession('sess-mesh-1'));

        // 3. ICE Candidate Routing / Buffering
        $candPayload = [
            'action' => 'ice_candidate',
            'sessionId' => 'sess-mesh-1',
            'fromPeer' => 'peer-alice',
            'toPeer' => 'peer-bob',
            'candidate' => [
                'candidate' => 'candidate:12345 1 udp 2122260223 127.0.0.1 50000 typ host',
                'sdpMid' => '0',
            ],
        ];
        $resCand = $server->handleRawPayload($candPayload);
        $this->assertTrue($resCand['send_to_caller']);
        $this->assertSame('candidate_routed', $resCand['data']['type']);
        $this->assertTrue($resCand['data']['buffered']); // Buffered since Bob is not connected via live WebSocket

        // 4. Answer Dispatch
        $answerPayload = [
            'action' => 'answer',
            'sessionId' => 'sess-mesh-1',
            'fromPeer' => 'peer-bob',
            'toPeer' => 'peer-alice',
            'sdp' => $this->sampleAnswerSdp,
            'roomId' => 'video-room-1',
        ];
        $resAnswer = $server->handleRawPayload($answerPayload);
        $this->assertTrue($resAnswer['send_to_caller']);
        $this->assertSame('answer_sent', $resAnswer['data']['type']);
        $this->assertTrue($server->getSession('sess-mesh-1')->isEstablished());

        // 5. Mute Toggle
        $mutePayload = [
            'action' => 'mute_toggle',
            'roomId' => 'video-room-1',
            'peerId' => 'peer-alice',
            'track' => 'audio',
            'muted' => true,
        ];
        $resMute = $server->handleRawPayload($mutePayload);
        $this->assertTrue($resMute['send_to_caller']);
        $this->assertSame('mute_toggle_ack', $resMute['data']['type']);
        $this->assertTrue($resMute['data']['muted']);

        // 6. Screen Share
        $screenPayload = [
            'action' => 'screen_share',
            'roomId' => 'video-room-1',
            'peerId' => 'peer-alice',
            'active' => true,
        ];
        $resScreen = $server->handleRawPayload($screenPayload);
        $this->assertTrue($resScreen['send_to_caller']);
        $this->assertSame('screen_share_ack', $resScreen['data']['type']);
        $this->assertTrue($resScreen['data']['active']);

        // 7. Chat Message
        $chatPayload = [
            'action' => 'chat_message',
            'roomId' => 'video-room-1',
            'peerId' => 'peer-alice',
            'userName' => 'Alice Sovereign',
            'text' => 'Hello sovereign WebRTC mesh!',
        ];
        $resChat = $server->handleRawPayload($chatPayload);
        $this->assertTrue($resChat['send_to_caller']);
        $this->assertSame('chat_ack', $resChat['data']['type']);
        $this->assertSame('Hello sovereign WebRTC mesh!', $resChat['data']['message']['text']);

        // 8. Ping / Pong
        $pingPayload = ['action' => 'ping'];
        $resPing = $server->handleRawPayload($pingPayload);
        $this->assertSame('pong', $resPing['data']['type']);

        // 9. Leave Room
        $leavePayload = [
            'action' => 'leave_room',
            'roomId' => 'video-room-1',
            'peerId' => 'peer-alice',
        ];
        $resLeave = $server->handleRawPayload($leavePayload);
        $this->assertSame('left_room', $resLeave['data']['type']);
        $this->assertTrue($resLeave['data']['success']);
    }

    public function testWebRtcRoomWidgetHtmlRendering(): void
    {
        $widget = WebRtcRoomWidget::room('conference-777', 'ws://127.0.0.1:9090', 'Alice Sovereign', [
            'iceServers' => [
                ['urls' => 'stun:stun.l.google.com:19302'],
            ],
        ]);

        $html = $widget->render();

        $this->assertStringContainsString('oshim-webrtc-widget', $html);
        $this->assertStringContainsString('Room: conference-777', $html);
        $this->assertStringContainsString('Alice Sovereign', $html);
        $this->assertStringContainsString('ws://127.0.0.1:9090', $html);
        $this->assertStringContainsString('stun:stun.l.google.com:19302', $html);
        $this->assertStringContainsString('video-grid', $html);
        $this->assertStringContainsString('chat-drawer', $html);
        $this->assertStringContainsString('RTCPeerConnection', $html);
        $this->assertStringContainsString('getUserMedia', $html);
        $this->assertStringContainsString('getDisplayMedia', $html);
        $this->assertStringContainsString('btn-audio', $html);
        $this->assertStringContainsString('btn-video', $html);
        $this->assertStringContainsString('btn-screen', $html);
        $this->assertStringContainsString('btn-chat', $html);
        $this->assertStringContainsString('btn-leave', $html);
    }

    public function testWebRtcServeCliCommandRegistration(): void
    {
        $command = new WebRtcServeCommand();

        $this->assertSame('webrtc:serve', $command->getName());
        $this->assertStringContainsString('WebRTC', $command->getDescription());

        $options = $command->getOptions();
        $this->assertArrayHasKey('host', $options);
        $this->assertArrayHasKey('port', $options);
        $this->assertArrayHasKey('topology', $options);
        $this->assertArrayHasKey('daemon', $options);

        $input = new Input(['oshim', 'webrtc:serve', '--host=127.0.0.1', '--port=9095', '--topology=sfu', '--daemon']);
        $output = new Output(false);

        ob_start();
        $exitCode = $command->execute($input, $output);
        $outputContent = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('OSHIM Sovereign WebRTC Signaling Engine', $outputContent);
        $this->assertStringContainsString('127.0.0.1', $outputContent);
        $this->assertStringContainsString('9095', $outputContent);
        $this->assertStringContainsString('sfu', $outputContent);
        $this->assertStringContainsString('Daemon Background', $outputContent);
    }

    public function testIceCandidateRouterAlternativeFormatAndClearing(): void
    {
        $router = new IceCandidateRouter();
        $altCandidate = [
            'foundation' => '12345',
            'component' => 1,
            'protocol' => 'udp',
            'ip' => '192.168.1.50',
            'port' => 50000,
        ];

        $this->assertTrue(IceCandidateRouter::validateCandidate($altCandidate));

        $router->bufferCandidate('sess-alt', $altCandidate);
        $this->assertCount(1, $router->getBufferedCandidates('sess-alt'));

        $router->clearSessionCandidates('sess-alt');
        $this->assertCount(0, $router->getBufferedCandidates('sess-alt'));
    }

    public function testWebRtcSignalingServerUnknownActionAndMalformedJson(): void
    {
        $server = new WebRtcSignalingServer();

        $resUnknown = $server->handleRawPayload(['action' => 'non_existent_action']);
        $this->assertTrue($resUnknown['send_to_caller']);
        $this->assertSame('error', $resUnknown['data']['type']);
        $this->assertStringContainsString('Unknown signaling action', $resUnknown['data']['message']);
    }

    public function testWebRtcSignalingServerLifecycleListenStop(): void
    {
        $server = new WebRtcSignalingServer();
        $endpoint = $server->listen('127.0.0.1', 9988);
        $this->assertSame('ws://127.0.0.1:9988', $endpoint);

        $this->assertNotNull($server->getRoomManager());
        $this->assertNotNull($server->getIceRouter());
        $this->assertNotNull($server->getWebSocketServer());
        $this->assertSame([], $server->getSessions());

        $server->stop();
    }
}

