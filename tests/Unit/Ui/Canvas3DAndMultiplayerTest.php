<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Testing\TestCase;
use Oshim\Ui\Canvas3D\Canvas3DWidget;
use Oshim\Ui\Canvas3D\Core\AmbientLight;
use Oshim\Ui\Canvas3D\Core\DirectionalLight;
use Oshim\Ui\Canvas3D\Core\Mesh3D;
use Oshim\Ui\Canvas3D\Core\Node3D;
use Oshim\Ui\Canvas3D\Core\OrthographicCamera;
use Oshim\Ui\Canvas3D\Core\PerspectiveCamera;
use Oshim\Ui\Canvas3D\Core\PointLight;
use Oshim\Ui\Canvas3D\Core\Scene3D;
use Oshim\Ui\Canvas3D\Geometries\BoxGeometry;
use Oshim\Ui\Canvas3D\Geometries\CylinderGeometry;
use Oshim\Ui\Canvas3D\Geometries\HologramIcosahedronGeometry;
use Oshim\Ui\Canvas3D\Geometries\PlaneGeometry;
use Oshim\Ui\Canvas3D\Geometries\SphereGeometry;
use Oshim\Ui\Canvas3D\Geometries\TorusGeometry;
use Oshim\Ui\Canvas3D\Materials\HologramShaderMaterial;
use Oshim\Ui\Canvas3D\Materials\MeshBasicMaterial;
use Oshim\Ui\Canvas3D\Materials\MeshStandardMaterial;
use Oshim\Ui\Canvas3D\Materials\ParticleMaterial;
use Oshim\Ui\Canvas3D\Math\Color;
use Oshim\Ui\Canvas3D\Math\Euler;
use Oshim\Ui\Canvas3D\Math\Matrix4;
use Oshim\Ui\Canvas3D\Math\Vector3;
use Oshim\Ui\Canvas3D\Serialization\ThreeJsSerializer;
use Oshim\Ui\Multiplayer\MultiplayerHub;
use Oshim\Ui\Multiplayer\MultiplayerMessage;
use Oshim\Ui\Multiplayer\MultiplayerPresenceWidget;
use Oshim\Ui\Multiplayer\MultiplayerRoom;
use Oshim\Ui\Multiplayer\Peer;
use Oshim\Ui\Multiplayer\PresenceState;
use Oshim\Ui\Multiplayer\SharedStateStore;

class Canvas3DAndMultiplayerTest extends TestCase
{
    // ==========================================
    // Part 1: Canvas3D & WebGL Tests
    // ==========================================

    public function testVector3MathOperations(): void
    {
        $v1 = new Vector3(1.0, 2.0, 3.0);
        $v2 = new Vector3(4.0, 5.0, 6.0);

        // Add
        $sum = $v1->add($v2);
        $this->assertEquals(5.0, $sum->x);
        $this->assertEquals(7.0, $sum->y);
        $this->assertEquals(9.0, $sum->z);

        // Sub
        $diff = $v2->sub($v1);
        $this->assertEquals(3.0, $diff->x);
        $this->assertEquals(3.0, $diff->y);
        $this->assertEquals(3.0, $diff->z);

        // Scale
        $scaled = $v1->scale(2.0);
        $this->assertEquals(2.0, $scaled->x);
        $this->assertEquals(4.0, $scaled->y);
        $this->assertEquals(6.0, $scaled->z);

        // Dot product: 1*4 + 2*5 + 3*6 = 4 + 10 + 18 = 32
        $this->assertEquals(32.0, $v1->dot($v2));

        // Cross product: (2*6 - 3*5, 3*4 - 1*6, 1*5 - 2*4) = (-3, 6, -3)
        $cross = $v1->cross($v2);
        $this->assertEquals(-3.0, $cross->x);
        $this->assertEquals(6.0, $cross->y);
        $this->assertEquals(-3.0, $cross->z);

        // Length & Normalization
        $unitX = new Vector3(3.0, 0.0, 0.0);
        $this->assertEquals(3.0, $unitX->length());
        $normalized = $unitX->normalize();
        $this->assertEquals(1.0, $normalized->length());
        $this->assertEquals(1.0, $normalized->x);

        // Lerp
        $start = new Vector3(0.0, 0.0, 0.0);
        $end = new Vector3(10.0, 20.0, 30.0);
        $mid = $start->lerp($end, 0.5);
        $this->assertEquals(5.0, $mid->x);
        $this->assertEquals(10.0, $mid->y);
        $this->assertEquals(15.0, $mid->z);

        // GLSL string
        $this->assertStringContainsString('vec3', $v1->toGlsl());
    }

    public function testColorRgbHexHslTransformations(): void
    {
        // 6-digit hex
        $cyan = Color::fromHex('#00f2fe');
        $this->assertEquals(0.0, $cyan->r);
        $this->assertGreaterThan(0.9, $cyan->g);
        $this->assertGreaterThan(0.99, $cyan->b);

        // 3-digit hex
        $white = Color::fromHex('#fff');
        $this->assertEquals(1.0, $white->r);
        $this->assertEquals(1.0, $white->g);
        $this->assertEquals(1.0, $white->b);

        // fromRgb
        $red = Color::fromRgb(255, 0, 0);
        $this->assertEquals(1.0, $red->r);
        $this->assertEquals(0.0, $red->g);
        $this->assertEquals(0.0, $red->b);
        $this->assertSame('#ff0000', $red->toHex());

        // fromHsl (green: h=120, s=1, l=0.5)
        $green = Color::fromHsl(120, 1.0, 0.5);
        $this->assertEquals(0.0, $green->r);
        $this->assertEquals(1.0, $green->g);
        $this->assertEquals(0.0, $green->b);

        // Color Lerp
        $black = Color::create(0.0, 0.0, 0.0);
        $fullWhite = Color::create(1.0, 1.0, 1.0);
        $gray = $black->lerp($fullWhite, 0.5);
        $this->assertEquals(0.5, $gray->r);
        $this->assertEquals(0.5, $gray->g);
        $this->assertEquals(0.5, $gray->b);

        // GLSL vec4 representation
        $this->assertStringContainsString('vec4', $cyan->toGlsl());
    }

    public function testMatrix4TransformationsAndProjections(): void
    {
        // Identity
        $identity = Matrix4::identity();
        $this->assertEquals(1.0, $identity->elements[0]);
        $this->assertEquals(1.0, $identity->elements[5]);
        $this->assertEquals(1.0, $identity->elements[10]);
        $this->assertEquals(1.0, $identity->elements[15]);

        // Translation
        $trans = Matrix4::translation(5.0, 10.0, -15.0);
        $point = new Vector3(1.0, 2.0, 3.0);
        $transformed = $trans->multiplyVector3($point);
        $this->assertEquals(6.0, $transformed->x);
        $this->assertEquals(12.0, $transformed->y);
        $this->assertEquals(-12.0, $transformed->z);

        // Scaling
        $scale = Matrix4::scaling(2.0, 3.0, 4.0);
        $scaledPoint = $scale->multiplyVector3(new Vector3(1.0, 1.0, 1.0));
        $this->assertEquals(2.0, $scaledPoint->x);
        $this->assertEquals(3.0, $scaledPoint->y);
        $this->assertEquals(4.0, $scaledPoint->z);

        // Compose: Translation + Rotation + Scale
        $composed = Matrix4::compose(
            new Vector3(10.0, 0.0, 0.0),
            Euler::fromDegrees(0, 90, 0),
            new Vector3(1.0, 1.0, 1.0)
        );
        $this->assertCount(16, $composed->toArray());

        // Perspective Projection
        $proj = Matrix4::perspective(60.0, 1.7777, 0.1, 1000.0);
        $this->assertCount(16, $proj->toArray());
        $this->assertNotEquals(0.0, $proj->elements[0]);

        // LookAt View Matrix
        $lookAt = Matrix4::lookAt(
            new Vector3(0.0, 0.0, 10.0),
            new Vector3(0.0, 0.0, 0.0),
            new Vector3(0.0, 1.0, 0.0)
        );
        $this->assertCount(16, $lookAt->toArray());
    }

    public function testGeometriesGenerationAndAttributes(): void
    {
        // 1. BoxGeometry
        $box = new BoxGeometry(2.0, 4.0, 6.0);
        $this->assertSame(24, $box->getVertexCount());
        $this->assertSame(12, $box->getFaceCount());
        $bbox = $box->computeBoundingBox();
        $this->assertEquals([-1.0, -2.0, -3.0], $bbox['min']);
        $this->assertEquals([1.0, 2.0, 3.0], $bbox['max']);

        // 2. SphereGeometry
        $sphere = new SphereGeometry(1.5, 12, 8);
        $this->assertGreaterThan(0, $sphere->getVertexCount());
        $this->assertNotEmpty($sphere->normals);
        $this->assertNotEmpty($sphere->uvs);
        $this->assertNotEmpty($sphere->indices);

        // 3. CylinderGeometry
        $cylinder = new CylinderGeometry(1.0, 1.0, 3.0, 12);
        $this->assertGreaterThan(0, $cylinder->getVertexCount());
        $this->assertNotEmpty($cylinder->indices);

        // 4. PlaneGeometry
        $plane = new PlaneGeometry(10.0, 10.0, 2, 2);
        $this->assertSame(9, $plane->getVertexCount());
        $this->assertSame(8, $plane->getFaceCount());

        // 5. TorusGeometry
        $torus = new TorusGeometry(2.0, 0.5, 8, 16);
        $this->assertGreaterThan(0, $torus->getVertexCount());

        // 6. HologramIcosahedronGeometry (Cyberpunk 3D Crystal)
        $hologramCrystal = new HologramIcosahedronGeometry(2.5);
        $this->assertSame(12, $hologramCrystal->getVertexCount());
        $this->assertSame(20, $hologramCrystal->getFaceCount()); // Exactly 20 faces

        // Three.js BufferGeometry schema
        $threeData = $hologramCrystal->toThreeJsData();
        $this->assertSame('BufferGeometry', $threeData['type']);
        $this->assertArrayHasKey('position', $threeData['data']['attributes']);
        $this->assertArrayHasKey('normal', $threeData['data']['attributes']);
        $this->assertArrayHasKey('uv', $threeData['data']['attributes']);
        $this->assertArrayHasKey('index', $threeData['data']);
    }

    public function testMaterialsAndHologramShaders(): void
    {
        // Basic Material
        $basic = new MeshBasicMaterial('#10b981', true, 0.75);
        $this->assertTrue($basic->wireframe);
        $this->assertEquals(0.75, $basic->opacity);
        $this->assertTrue($basic->transparent);

        // Standard PBR Material
        $pbr = new MeshStandardMaterial('#ffffff', 0.2, 0.8, '#ff0055', 1.5);
        $this->assertEquals(0.2, $pbr->roughness);
        $this->assertEquals(0.8, $pbr->metalness);
        $this->assertEquals(1.5, $pbr->emissiveIntensity);
        $pbrData = $pbr->toThreeJsData();
        $this->assertSame('MeshStandardMaterial', $pbrData['type']);

        // Hologram Shader Material
        $holo = new HologramShaderMaterial('#00f2fe', '#051329', 3.0, 2.5, 2.0);
        $this->assertEquals(3.0, $holo->scanlineSpeed);
        $this->assertEquals(2.5, $holo->glowIntensity);
        $this->assertTrue($holo->transparent);

        $vs = $holo->getVertexShader();
        $this->assertStringContainsString('aPosition', $vs);
        $this->assertStringContainsString('uProjectionMatrix', $vs);
        $this->assertStringContainsString('uGlitch', $vs);

        $fs = $holo->getFragmentShader();
        $this->assertStringContainsString('uRimColor', $fs);
        $this->assertStringContainsString('fresnel', $fs);
        $this->assertStringContainsString('scanline', $fs);

        // Particle Material
        $particles = new ParticleMaterial('#00f2fe', 3.5, 0.9);
        $this->assertEquals(3.5, $particles->size);
        $this->assertSame('PointsMaterial', $particles->type);
    }

    public function testSceneGraphHierarchyAndTransforms(): void
    {
        $scene = Scene3D::create('QuantumMatrixScene');
        $scene->setBackground('#030712');
        $scene->setFog('#00f2fe', 1.0, 50.0);

        // Group Node hierarchy
        $parentGroup = new Node3D('ParentPivot');
        $parentGroup->setPosition(0.0, 5.0, 0.0);

        $childMeshNode = new Mesh3D(
            new BoxGeometry(1.0, 1.0, 1.0),
            new MeshBasicMaterial('#00f2fe'),
            'ChildCube'
        );
        $childMeshNode->setPosition(0.0, 2.0, 0.0);
        $childMeshNode->setSpin(0.5, 1.0, 0.0);
        $childMeshNode->setFloating(0.2, 1.5);

        $parentGroup->add($childMeshNode);
        $scene->add($parentGroup);

        // Add Hologram Mesh
        $hologramMesh = new Mesh3D(
            new HologramIcosahedronGeometry(2.0),
            new HologramShaderMaterial('#00f2fe', '#090d16'),
            'QuantumHologram'
        );
        $scene->add($hologramMesh);

        // Add Lights & Camera
        $ambient = new AmbientLight('#ffffff', 0.4);
        $dirLight = new DirectionalLight('#00f2fe', 1.2);
        $pointLight = new PointLight('#ec4899', 2.0, 30.0);
        $camera = new PerspectiveCamera(45.0, 16 / 9, 0.1, 500.0);
        $camera->lookAt(0.0, 0.0, 0.0);

        $scene->add($ambient)->add($dirLight)->add($pointLight);
        $scene->setCamera($camera);

        // Test Find & Traverse
        $this->assertSame($childMeshNode, $scene->findByName('ChildCube'));
        $this->assertSame($hologramMesh, $scene->findByName('QuantumHologram'));
        $this->assertNull($scene->findByName('NonExistent'));

        // Test World Matrix Propagation
        $parentMatrix = $parentGroup->getWorldMatrix();
        $childWorldMatrix = $childMeshNode->getWorldMatrix();
        // Child Y should be 5 + 2 = 7
        $this->assertEquals(7.0, $childWorldMatrix->elements[13]);

        // Test Collections
        $this->assertCount(2, $scene->getAllMeshes());
        $this->assertCount(3, $scene->getAllLights());
        $this->assertCount(2, $scene->getAllGeometries());
        $this->assertCount(2, $scene->getAllMaterials());
    }

    public function testThreeJsSerializerExport(): void
    {
        $scene = Scene3D::create('ThreeExportScene');
        $mesh = new Mesh3D(new SphereGeometry(1.0), new MeshStandardMaterial('#00f2fe'), 'SphereMesh');
        $scene->add($mesh);

        $data = ThreeJsSerializer::serialize($scene);

        $this->assertArrayHasKey('metadata', $data);
        $this->assertEquals(4.5, $data['metadata']['version']);
        $this->assertSame('Object', $data['metadata']['type']);
        $this->assertArrayHasKey('geometries', $data);
        $this->assertArrayHasKey('materials', $data);
        $this->assertArrayHasKey('object', $data);
        $this->assertCount(1, $data['geometries']);
        $this->assertCount(1, $data['materials']);

        $json = ThreeJsSerializer::toJson($scene, true);
        $this->assertStringContainsString('ThreeExportScene', $json);
        $this->assertStringContainsString('BufferGeometry', $json);
        $this->assertStringContainsString('MeshStandardMaterial', $json);
    }

    public function testCanvas3DWidgetRendersWebGLHtmlAndScripts(): void
    {
        $scene = Scene3D::create('CyberCanvas');
        $holoMesh = new Mesh3D(
            new HologramIcosahedronGeometry(1.5),
            new HologramShaderMaterial('#00f2fe', '#000000'),
            'HoloCore'
        );
        $scene->add($holoMesh);

        $widget = Canvas3DWidget::create($scene, '100%', '600px', 'Cyber Core Hologram')
            ->showHud(true)
            ->setAutoRotate(true, 1.2)
            ->enableOrbit(true);

        $html = $widget->render();

        $this->assertStringContainsString('Cyber Core Hologram', $html);
        $this->assertStringContainsString('<canvas', $html);
        $this->assertStringContainsString('getContext("webgl"', $html);
        $this->assertStringContainsString('Hologram scanline effect', $html);
        $this->assertStringContainsString('Fresnel edge glow', $html);
        $this->assertStringContainsString('FPS', $html);
        $this->assertStringContainsString('Wireframe', $html);
        $this->assertStringContainsString('application/json', $html);
        $this->assertStringContainsString('BufferGeometry', $html);
    }

    // ==========================================
    // Part 2: Multiplayer & Real-Time Presence Tests
    // ==========================================

    public function testPresenceStateAndPeerTelemetry(): void
    {
        $presence = PresenceState::create();
        $presence->cursorX = 450.5;
        $presence->cursorY = 280.0;
        $presence->cursorActive = true;
        $presence->cursorState = 'pointer';
        $presence->targetSelector = '#submit-btn';
        $presence->status = 'online';

        $arr = $presence->toArray();
        $this->assertEquals(450.5, $arr['cursorX']);
        $this->assertEquals(280.0, $arr['cursorY']);
        $this->assertSame('#submit-btn', $arr['targetSelector']);

        $restored = PresenceState::fromArray($arr);
        $this->assertEquals(450.5, $restored->cursorX);
        $this->assertSame('pointer', $restored->cursorState);

        // Peer Model
        $peer = Peer::create('usr_1', 'Alice Developer', '#00f2fe', null, 'admin');
        $this->assertSame('usr_1', $peer->id);
        $this->assertSame('Alice Developer', $peer->name);
        $this->assertSame('#00f2fe', $peer->color);
        $this->assertSame('admin', $peer->role);
        $this->assertFalse($peer->isStale(10.0));

        // Update Presence
        $peer->updatePresence(['cursorX' => 120.0, 'cursorY' => 340.0, 'cursorState' => 'clicking']);
        $this->assertEquals(120.0, $peer->presence->cursorX);
        $this->assertEquals(340.0, $peer->presence->cursorY);
        $this->assertSame('clicking', $peer->presence->cursorState);

        // Stale detection
        $peer->lastSeen = microtime(true) - 20.0;
        $this->assertTrue($peer->isStale(15.0));
    }

    public function testMultiplayerMessageSerialization(): void
    {
        $msg = MultiplayerMessage::create(
            MultiplayerMessage::TYPE_PRESENCE,
            'room_dev',
            'peer_alpha',
            ['x' => 100, 'y' => 200]
        );

        $json = $msg->toJson();
        $this->assertStringContainsString('presence', $json);
        $this->assertStringContainsString('room_dev', $json);
        $this->assertStringContainsString('peer_alpha', $json);

        $decoded = MultiplayerMessage::fromJson($json);
        $this->assertSame(MultiplayerMessage::TYPE_PRESENCE, $decoded->type);
        $this->assertSame('room_dev', $decoded->roomId);
        $this->assertSame('peer_alpha', $decoded->senderId);
        $this->assertEquals(100, $decoded->payload['x']);
    }

    public function testSharedStateStoreLwwConflictResolution(): void
    {
        $store = new SharedStateStore(['title' => 'Initial Document']);

        $this->assertSame('Initial Document', $store->get('title'));
        $this->assertTrue($store->has('title'));
        $this->assertSame(1, $store->count());

        // Peer 1 updates title
        $store->set('title', 'Updated by Alice', 'peer_alice');
        $this->assertSame('Updated by Alice', $store->get('title'));

        // Apply remote newer mutation
        $futureTime = microtime(true) + 10.0;
        $applied = $store->applyMutation([
            'key' => 'title',
            'value' => 'Updated by Bob (Newer)',
            'version' => 3,
            'updatedAt' => $futureTime,
            'updatedBy' => 'peer_bob',
        ]);
        $this->assertTrue($applied);
        $this->assertSame('Updated by Bob (Newer)', $store->get('title'));

        // Apply older stale mutation -> MUST BE REJECTED by LWW
        $rejected = $store->applyMutation([
            'key' => 'title',
            'value' => 'Stale Update (Old)',
            'version' => 1,
            'updatedAt' => 1000.0,
            'updatedBy' => 'peer_stale',
        ]);
        $this->assertFalse($rejected);
        $this->assertSame('Updated by Bob (Newer)', $store->get('title'));

        // Delete key
        $deletedRecord = $store->delete('title', 'peer_alice');
        $this->assertNotNull($deletedRecord);
        $this->assertFalse($store->has('title'));
        $this->assertNull($store->get('title'));
    }

    public function testMultiplayerRoomLifecycleAndPresence(): void
    {
        $room = MultiplayerRoom::create('workspace-design', 'Design Canvas', 10.0);

        $peer1 = Peer::create('p1', 'Alice Designer', '#00f2fe');
        $peer2 = Peer::create('p2', 'Bob Architect', '#10b981');

        // Join
        $joinMsg1 = $room->join($peer1);
        $this->assertSame(MultiplayerMessage::TYPE_JOIN, $joinMsg1->type);
        $this->assertSame(1, $room->getPeerCount());

        $room->join($peer2);
        $this->assertSame(2, $room->getPeerCount());
        $this->assertSame($peer1, $room->getPeer('p1'));

        // Update Presence
        $presenceMsg = $room->updatePresence('p1', ['cursorX' => 500, 'cursorY' => 300]);
        $this->assertNotNull($presenceMsg);
        $this->assertSame(MultiplayerMessage::TYPE_PRESENCE, $presenceMsg->type);
        $this->assertEquals(500, $presenceMsg->payload['presence']['cursorX']);

        // Mutate Shared State
        $mutateMsg = $room->mutateState('p1', 'activeLayer', 'Layer_Vector_3');
        $this->assertSame(MultiplayerMessage::TYPE_STATE_MUTATE, $mutateMsg->type);
        $this->assertSame('Layer_Vector_3', $room->state->get('activeLayer'));

        // Create Full Snapshot Sync
        $syncMsg = $room->createSyncMessage('p2');
        $this->assertSame(MultiplayerMessage::TYPE_STATE_SYNC, $syncMsg->type);
        $this->assertCount(2, $syncMsg->payload['peers']);
        $this->assertArrayHasKey('activeLayer', $syncMsg->payload['state']);

        // Stale Peering & Prune
        $peer2->lastSeen = microtime(true) - 15.0;
        $pruned = $room->pruneStalePeers();
        $this->assertContains('p2', $pruned);
        $this->assertSame(1, $room->getPeerCount());

        // Leave
        $leaveMsg = $room->leave('p1');
        $this->assertNotNull($leaveMsg);
        $this->assertSame(MultiplayerMessage::TYPE_LEAVE, $leaveMsg->type);
        $this->assertTrue($room->isEmpty());
    }

    public function testMultiplayerHubWebSocketMessageDispatching(): void
    {
        $hub = new MultiplayerHub();

        $sentMessages = [];
        $broadcastMessages = [];

        $sendCallback = function (string $json) use (&$sentMessages) {
            $sentMessages[] = json_decode($json, true);
        };

        $broadcastCallback = function (string $roomId, string $json, ?string $excludePeerId) use (&$broadcastMessages) {
            $broadcastMessages[] = [
                'roomId' => $roomId,
                'json' => json_decode($json, true),
                'exclude' => $excludePeerId,
            ];
        };

        // 1. Client 1 Joins Room
        $joinJson1 = json_encode([
            'type' => 'join',
            'roomId' => 'room_alpha',
            'senderId' => 'peer_1',
            'payload' => [
                'peer' => ['name' => 'Commander Alpha', 'role' => 'admin', 'color' => '#00f2fe'],
            ],
        ]);

        $hub->handleMessage('conn_1', $joinJson1, $sendCallback, $broadcastCallback);

        $this->assertSame(1, $hub->getActiveRoomCount());
        $this->assertSame(1, $hub->getTotalPeerCount());
        $this->assertSame('state_sync', $sentMessages[0]['type']);

        // 2. Client 2 Joins Same Room
        $joinJson2 = json_encode([
            'type' => 'join',
            'roomId' => 'room_alpha',
            'senderId' => 'peer_2',
            'payload' => [
                'peer' => ['name' => 'Pilot Beta', 'role' => 'member', 'color' => '#10b981'],
            ],
        ]);

        $hub->handleMessage('conn_2', $joinJson2, $sendCallback, $broadcastCallback);
        $this->assertSame(2, $hub->getTotalPeerCount());

        // 3. Client 2 Sends Live Cursor Presence
        $presenceJson = json_encode([
            'type' => 'presence',
            'roomId' => 'room_alpha',
            'senderId' => 'peer_2',
            'payload' => ['cursorX' => 720.0, 'cursorY' => 450.0],
        ]);

        $hub->handleMessage('conn_2', $presenceJson, $sendCallback, $broadcastCallback);

        $lastBroadcast = end($broadcastMessages);
        $this->assertSame('presence', $lastBroadcast['json']['type']);
        $this->assertSame('peer_2', $lastBroadcast['exclude']);

        // 4. Client 1 Mutates Shared State
        $mutateJson = json_encode([
            'type' => 'state_mutate',
            'roomId' => 'room_alpha',
            'senderId' => 'peer_1',
            'payload' => ['key' => 'canvasMode', 'value' => 'vector_3d'],
        ]);

        $hub->handleMessage('conn_1', $mutateJson, $sendCallback, $broadcastCallback);
        $lastBroadcast = end($broadcastMessages);
        $this->assertSame('state_mutate', $lastBroadcast['json']['type']);
        $this->assertSame('canvasMode', $lastBroadcast['json']['payload']['key']);
        $this->assertSame('vector_3d', $lastBroadcast['json']['payload']['value']);

        // 5. Client 2 Disconnects
        $disconnected = $hub->handleDisconnect('conn_2', $broadcastCallback);
        $this->assertNotNull($disconnected);
        $this->assertSame('peer_2', $disconnected['peerId']);
        $this->assertSame(1, $hub->getTotalPeerCount());

        // 6. Malformed JSON handling
        $malformedSent = [];
        $hub->handleMessage('conn_bad', '{invalid-json', function ($json) use (&$malformedSent) {
            $malformedSent[] = json_decode($json, true);
        }, $broadcastCallback);

        $this->assertNotEmpty($malformedSent);
        $this->assertSame('error', $malformedSent[0]['type']);
    }

    public function testMultiplayerPresenceWidgetRendering(): void
    {
        $widget = MultiplayerPresenceWidget::create(
            'sovereign-collab-room',
            'ws://127.0.0.1:9090',
            'Sovereign Dev',
            'architect',
            '#00f2fe'
        )->showAvatarStack(true)
         ->showCursorOverlay(true)
         ->showBroadcastDock(true);

        $html = $widget->render();

        $this->assertStringContainsString('sovereign-collab-room', $html);
        $this->assertStringContainsString('ws://127.0.0.1:9090', $html);
        $this->assertStringContainsString('Sovereign Dev', $html);
        $this->assertStringContainsString('#00f2fe', $html);
        $this->assertStringContainsString('ROOM: sovereign-collab-room', $html);
        $this->assertStringContainsString('Online', $html);
        $this->assertStringContainsString('cursor_layer', $html);
        $this->assertStringContainsString('burst_layer', $html);
        $this->assertStringContainsString('polygon points="0,0 24,10 13,13 10,24"', $html);
        $this->assertStringContainsString('mousemove', $html);
        $this->assertStringContainsString('heartbeat', $html);
        $this->assertStringContainsString('state_sync', $html);
        $this->assertStringContainsString('reactions', $html);
    }
}
