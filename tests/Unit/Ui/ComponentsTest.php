<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Http\Request;
use Oshim\Http\Response;
use Oshim\Tests\Harness\TestCase;
use Oshim\Ui\Components\Button;
use Oshim\Ui\Components\Card;
use Oshim\Ui\Components\Table;
use Oshim\Ui\Components\Chart;
use Oshim\Ui\Components\Modal;
use Oshim\Ui\Components\Form;
use Oshim\Ui\Components\Sidebar;
use Oshim\Ui\Components\Navbar;
use Oshim\Ui\Components\Terminal;
use Oshim\Ui\Components\DataGrid;
use Oshim\Ui\Components\StatusBadge;
use Oshim\Ui\ComponentRegistry;
use Oshim\Ui\DiffEngine;
use Oshim\Ui\UiManager;

class ComponentsTest extends TestCase
{
    public function testButtonVariantsSizesIconsAndStates(): void
    {
        // 1. Primary with icon
        $btn1 = new Button([
            'label' => 'Launch Node',
            'variant' => 'primary',
            'size' => 'lg',
            'icon' => 'plus',
            'action' => 'launchNode',
            'payload' => ['region' => 'fra-1'],
        ]);
        $html1 = $btn1->render();
        $this->assertStringContains('oshim-btn--primary', $html1);
        $this->assertStringContains('oshim-btn--lg', $html1);
        $this->assertStringContains('oshim:click="launchNode"', $html1);
        $this->assertStringContains('data-oshim-payload="', $html1);
        $this->assertStringContains('Launch Node', $html1);

        // 2. Loading state
        $btn2 = new Button([
            'label' => 'Saving...',
            'variant' => 'glass',
            'loading' => true,
        ]);
        $html2 = $btn2->render();
        $this->assertStringContains('oshim-btn--loading', $html2);
        $this->assertStringContains('disabled="disabled"', $html2);
        $this->assertStringContains('oshim-spinner-svg', $html2);

        // 3. Danger button disabled
        $btn3 = new Button([
            'label' => 'Terminate',
            'variant' => 'danger',
            'disabled' => true,
        ]);
        $html3 = $btn3->render();
        $this->assertStringContains('oshim-btn--danger', $html3);
        $this->assertStringContains('disabled="disabled"', $html3);
    }

    public function testCardHeaderBodyFooterSlotsAndGlowVariants(): void
    {
        $card = new Card([
            'title' => 'Cluster Utilization',
            'subtitle' => 'EU Central (Frankfurt)',
            'variant' => 'glass',
            'glowColor' => 'cyan',
            'body' => '<div class="metric">Memory: 64 GB / 128 GB</div>',
            'footer' => '<span>Updated 1m ago</span>',
            'footerActions' => '<button>Refresh</button>',
        ]);
        $html = $card->render();

        $this->assertStringContains('oshim-card', $html);
        $this->assertStringContains('oshim-card--glass', $html);
        $this->assertStringContains('oshim-card--glow-cyan', $html);
        $this->assertStringContains('Cluster Utilization', $html);
        $this->assertStringContains('EU Central (Frankfurt)', $html);
        $this->assertStringContains('Memory: 64 GB / 128 GB', $html);
        $this->assertStringContains('Updated 1m ago', $html);
        $this->assertStringContains('Refresh', $html);
    }

    public function testTableColumnsRowsCustomRenderAndEmptyState(): void
    {
        // 1. Populated table
        $table = new Table([
            'columns' => [
                ['key' => 'id', 'label' => 'Instance ID', 'align' => 'left'],
                ['key' => 'name', 'label' => 'Name', 'render' => fn($v) => '<strong>' . htmlspecialchars((string)$v) . '</strong>'],
                ['key' => 'ip', 'label' => 'IP Address'],
                ['key' => 'tag', 'label' => 'Tag'],
            ],
            'data' => [
                ['id' => 'vps-01', 'name' => 'Web App', 'ip' => '192.168.1.1', 'tag' => null],
                ['id' => 'vps-02', 'name' => 'DB Primary', 'ip' => '192.168.1.2', 'tag' => 'production'],
            ],
            'striped' => true,
            'hoverable' => true,
        ]);
        $html = $table->render();

        $this->assertStringContains('oshim-table', $html);
        $this->assertStringContains('oshim-table--striped', $html);
        $this->assertStringContains('oshim-table--hover', $html);
        $this->assertStringContains('<strong>Web App</strong>', $html);
        $this->assertStringContains('<span class="oshim-null">NULL</span>', $html);
        $this->assertStringContains('production', $html);

        // 2. Empty table
        $emptyTable = new Table([
            'columns' => ['ID', 'Name'],
            'data' => [],
            'emptyMessage' => 'No active nodes found.',
        ]);
        $emptyHtml = $emptyTable->render();
        $this->assertStringContains('No active nodes found.', $emptyHtml);
        $this->assertStringContains('colspan="2"', $emptyHtml);
    }

    public function testChartSplineBarGaugeAndSparklineRendering(): void
    {
        // 1. Spline Area Chart
        $splineChart = new Chart([
            'type' => 'area',
            'title' => 'Bandwidth Usage',
            'unit' => ' MB/s',
            'data' => [
                'labels' => ['10:00', '11:00', '12:00', '13:00'],
                'datasets' => [
                    ['label' => 'Inbound', 'values' => [12.5, 45.0, 32.0, 88.5], 'color' => '#00f2fe'],
                ],
            ],
        ]);
        $splineHtml = $splineChart->render();
        $this->assertStringContains('<svg', $splineHtml);
        $this->assertStringContains('oshim-chart--spline', $splineHtml);
        $this->assertStringContains('linearGradient', $splineHtml);
        $this->assertStringContains('d="M', $splineHtml);

        // 2. Bar Chart
        $barChart = new Chart([
            'type' => 'bar',
            'data' => [
                'labels' => ['Mon', 'Tue', 'Wed'],
                'datasets' => [
                    ['label' => 'Reqs', 'values' => [120, 300, 250], 'color' => '#00e676'],
                ],
            ],
        ]);
        $barHtml = $barChart->render();
        $this->assertStringContains('oshim-chart--bar', $barHtml);
        $this->assertStringContains('<rect', $barHtml);

        // 3. Radial Gauge Chart
        $gaugeChart = new Chart([
            'type' => 'gauge',
            'title' => 'CPU Load',
            'unit' => '%',
            'data' => ['value' => 67.5],
        ]);
        $gaugeHtml = $gaugeChart->render();
        $this->assertStringContains('oshim-chart--gauge', $gaugeHtml);
        $this->assertStringContains('67.5%', $gaugeHtml);
        $this->assertStringContains('CPU Load', $gaugeHtml);

        // 4. Sparkline
        $sparkline = new Chart([
            'type' => 'sparkline',
            'data' => ['values' => [10, 20, 15, 35, 25, 50]],
            'width' => 120,
            'height' => 40,
        ]);
        $sparkHtml = $sparkline->render();
        $this->assertStringContains('oshim-sparkline', $sparkHtml);
    }

    public function testModalComponentOpenCloseToggleAndBackdrop(): void
    {
        $modal = new Modal([
            'name' => 'reboot_modal',
            'title' => 'Confirm Reboot',
            'subtitle' => 'Graceful ACPI reboot',
            'size' => 'md',
            'body' => '<p>Are you sure you want to reboot?</p>',
            'footer' => '<button>Cancel</button><button>Confirm</button>',
        ]);

        $this->assertStringContains('oshim-modal--closed', $modal->render());
        $this->assertStringContains('hidden', $modal->render());

        $modal->open();
        $this->assertStringContains('oshim-modal--open', $modal->render());
        $this->assertStringContains('active', $modal->render());
        $this->assertStringContains('Confirm Reboot', $modal->render());
        $this->assertStringContains('Graceful ACPI reboot', $modal->render());
        $this->assertStringContains('Are you sure you want to reboot?', $modal->render());

        $modal->close();
        $this->assertStringContains('oshim-modal--closed', $modal->render());

        $modal->toggle();
        $this->assertStringContains('oshim-modal--open', $modal->render());
    }

    public function testFormComponentControlsValidationAndCsrf(): void
    {
        $form = new Form([
            'action' => '/api/instances/create',
            'method' => 'POST',
            'fields' => [
                'hostname' => ['label' => 'Hostname', 'type' => 'text', 'placeholder' => 'srv-01'],
                'plan'     => ['label' => 'Plan', 'type' => 'select', 'options' => ['std_1' => '1 vCPU 2GB', 'std_2' => '2 vCPU 4GB']],
                'backup'   => ['label' => 'Enable Backups', 'type' => 'toggle', 'default' => 1],
                'notes'    => ['label' => 'Notes', 'type' => 'textarea'],
            ],
            'values' => [
                'hostname' => 'web-node-01',
                'plan' => 'std_2',
            ],
            'errors' => [
                'hostname' => 'Hostname already registered.',
            ],
        ]);

        $html = $form->render();
        $this->assertStringContains('action="/api/instances/create"', $html);
        $this->assertStringContains('name="_csrf"', $html);
        $this->assertStringContains('value="web-node-01"', $html);
        $this->assertStringContains('std_2', $html);
        $this->assertStringContains('Hostname already registered.', $html);
        $this->assertStringContains('oshim-toggle', $html);

        // Test field update action
        $form->setFieldValue(['field' => 'hostname', 'value' => 'web-node-02']);
        $this->assertSame('web-node-02', $form->getState()['values']['hostname']);
        $this->assertArrayNotHasKey('hostname', $form->getState()['errors']);
    }

    public function testSidebarCollapsibleNavigationAndActiveRoute(): void
    {
        $sidebar = new Sidebar([
            'brand' => ['name' => 'OSHIM CLOUD', 'url' => '/dashboard'],
            'activeRoute' => '/instances',
            'items' => [
                ['label' => 'Dashboard', 'url' => '/dashboard', 'icon' => '<span>🏠</span>'],
                [
                    'group' => 'Infrastructure',
                    'items' => [
                        ['label' => 'Compute Instances', 'url' => '/instances', 'badge' => 8, 'badgeVariant' => 'cyan'],
                        ['label' => 'Block Volumes', 'url' => '/volumes'],
                    ]
                ]
            ],
            'userProfile' => ['name' => 'Alex Developer', 'role' => 'System Architect'],
        ]);

        $html = $sidebar->render();
        $this->assertStringContains('OSHIM CLOUD', $html);
        $this->assertStringContains('Infrastructure', $html);
        $this->assertStringContains('Compute Instances', $html);
        $this->assertStringContains('oshim-sidebar__item--active', $html);
        $this->assertStringContains('Alex Developer', $html);
        $this->assertStringContains('System Architect', $html);

        $sidebar->toggleCollapse();
        $this->assertStringContains('oshim-sidebar--collapsed', $sidebar->render());
    }

    public function testNavbarBreadcrumbsSearchAndUserDropdown(): void
    {
        $navbar = new Navbar([
            'breadcrumbs' => [
                ['label' => 'Instances', 'url' => '/instances'],
                ['label' => 'vps-frankfurt-01', 'url' => ''],
            ],
            'showSearch' => true,
            'statusIndicator' => ['status' => 'running', 'label' => 'All Systems Operational'],
            'notifications' => ['count' => 4],
            'user' => ['name' => 'Sarah Admin', 'avatar' => '/avatars/sarah.png'],
        ]);

        $html = $navbar->render();
        $this->assertStringContains('oshim-navbar', $html);
        $this->assertStringContains('Instances', $html);
        $this->assertStringContains('vps-frankfurt-01', $html);
        $this->assertStringContains('oshim-navbar__search', $html);
        $this->assertStringContains('All Systems Operational', $html);
        $this->assertStringContains('Sarah Admin', $html);
    }

    public function testTerminalViewportToolbarAndWsAttributes(): void
    {
        $termSsh = new Terminal([
            'type' => 'ssh',
            'instanceId' => 'srv_abc123',
            'title' => 'WebSSH - Production App',
            'status' => 'connected',
            'wsEndpoint' => '/ws/ssh/srv_abc123',
        ]);
        $htmlSsh = $termSsh->render();
        $this->assertStringContains('oshim-terminal--ssh', $htmlSsh);
        $this->assertStringContains('data-oshim-ws="/ws/ssh/srv_abc123"', $htmlSsh);
        $this->assertStringContains('WebSSH - Production App', $htmlSsh);
        $this->assertStringContains('oshim-terminal__screen', $htmlSsh);

        $termVnc = new Terminal([
            'type' => 'vnc',
            'instanceId' => 'srv_abc123',
            'status' => 'connecting',
        ]);
        $htmlVnc = $termVnc->render();
        $this->assertStringContains('oshim-terminal--vnc', $htmlVnc);
        $this->assertStringContains('oshim-terminal__canvas', $htmlVnc);
    }

    public function testStatusBadgeAllStatusesAndPulsingDots(): void
    {
        $statuses = ['running', 'stopped', 'warning', 'error', 'provisioning', 'rebooting', 'idle', 'active', 'healthy'];
        foreach ($statuses as $st) {
            $badge = new StatusBadge(['status' => $st, 'pulse' => true]);
            $html = $badge->render();
            $this->assertStringContains("oshim-badge--{$st}", $html);
            $this->assertStringContains('oshim-badge--pulse', $html);
            $this->assertStringContains('oshim-badge__dot', $html);
            $this->assertStringContains(strtoupper($st), $html);
        }
    }

    public function testComponentRegistryAndUiManagerWorkflow(): void
    {
        $registry = new ComponentRegistry();
        $this->assertTrue($registry->has('button'));
        $this->assertTrue($registry->has('card'));
        $this->assertTrue($registry->has('datagrid'));
        $this->assertTrue($registry->has('status-badge'));

        $diffEngine = new DiffEngine();
        $uiManager = new UiManager($registry, $diffEngine);

        // Test server render via UiManager
        $btnHtml = $uiManager->render('button', ['label' => 'Registry Render']);
        $this->assertStringContains('Registry Render', $btnHtml);

        // Test processAction with valid HMAC
        $modal = $registry->resolve('modal', ['name' => 'diag_modal']);
        $state = ['open' => false];
        $sig = $modal->generateSignature($state);

        $response = $uiManager->processAction('modal', $modal->getId(), 'open', [], $state, $sig);
        $this->assertTrue($response['success']);
        $this->assertSame('modal', $response['component']);
        $this->assertSame('open', $response['action']);
        $this->assertTrue($response['state']['open']);
        $this->assertNotEmpty($response['sig']);
        $this->assertNotEmpty($response['html']);
        $this->assertCount(1, $response['patches']);
    }

    public function testHandleActionWithRealJsonRequest(): void
    {
        $registry = new ComponentRegistry();
        $diffEngine = new DiffEngine();
        $uiManager = new UiManager($registry, $diffEngine);

        $modal = $registry->resolve('modal', ['name' => 'action_modal']);
        $initialState = ['open' => false];
        $sig = $modal->generateSignature($initialState);

        $req = new Request(
            method: 'POST',
            uri: '/oshim/ui/action',
            headers: ['Content-Type' => 'application/json'],
            rawBody: (string)json_encode([
                'component' => 'modal',
                'id'        => $modal->getId(),
                'action'    => 'open',
                'state'     => base64_encode((string)json_encode($initialState)),
                'sig'       => $sig,
            ])
        );

        $response = $uiManager->handleAction($req);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('modal', $data['component']);
        $this->assertSame('open', $data['action']);
        $this->assertTrue($data['state']['open']);
        $this->assertNotEmpty($data['sig']);
        $this->assertStringContains('oshim-modal--open', $data['html']);
        $this->assertCount(1, $data['patches']);
    }

    public function testHandleActionWithRealFormPostRequest(): void
    {
        $registry = new ComponentRegistry();
        $diffEngine = new DiffEngine();
        $uiManager = new UiManager($registry, $diffEngine);

        $sidebar = $registry->resolve('sidebar', ['brand' => ['name' => 'OSHIM']]);
        $initialState = ['collapsed' => false];
        $sig = $sidebar->generateSignature($initialState);

        $req = new Request(
            method: 'POST',
            uri: '/oshim/ui/action',
            headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
            post: [
                'component' => 'sidebar',
                'id'        => $sidebar->getId(),
                'action'    => 'toggleCollapse',
                'state'     => json_encode($initialState),
                'sig'       => $sig,
            ]
        );

        $response = $uiManager->handleAction($req);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['state']['collapsed']);
        $this->assertStringContains('oshim-sidebar--collapsed', $data['html']);
    }

    public function testHandleActionDataGridLifecyclePreservesItemsAndState(): void
    {
        $registry = new ComponentRegistry();
        $diffEngine = new DiffEngine();
        $uiManager = new UiManager($registry, $diffEngine);

        $items = [
            ['id' => 's1', 'name' => 'Bravo Node', 'ram' => 16],
            ['id' => 's2', 'name' => 'Alpha Node', 'ram' => 32],
            ['id' => 's3', 'name' => 'Charlie Node', 'ram' => 8],
        ];
        $cols = [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'name', 'label' => 'Name', 'sortable' => true],
            ['key' => 'ram', 'label' => 'RAM (GB)', 'sortable' => true],
        ];

        $grid = $registry->resolve('datagrid', [
            'columns' => $cols,
            'items'   => $items,
            'total'   => 3,
        ]);

        $initialState = $grid->getState();
        $sig = $grid->generateSignature($initialState);

        // 1. Dispatch sort by name via handleAction with JSON request
        $reqSort = new Request(
            method: 'POST',
            uri: '/oshim/ui/action',
            headers: ['Content-Type' => 'application/json'],
            rawBody: (string)json_encode([
                'component' => 'datagrid',
                'id'        => $grid->getId(),
                'action'    => 'sort',
                'payload'   => ['key' => 'name'],
                'state'     => base64_encode((string)json_encode($initialState)),
                'sig'       => $sig,
            ])
        );

        $respSort = $uiManager->handleAction($reqSort);
        $this->assertSame(Response::HTTP_OK, $respSort->getStatusCode());

        $dataSort = json_decode($respSort->getContent(), true);
        $this->assertTrue($dataSort['success']);
        $this->assertSame('name', $dataSort['state']['sortKey']);
        $this->assertSame('asc', $dataSort['state']['sortOrder']);
        // Verify items were preserved and sorted: Alpha Node should appear before Bravo Node
        $htmlSort = $dataSort['html'];
        $posAlpha = strpos($htmlSort, 'Alpha Node');
        $posBravo = strpos($htmlSort, 'Bravo Node');
        $this->assertTrue($posAlpha !== false);
        $this->assertTrue($posBravo !== false);
        $this->assertLessThan($posBravo, $posAlpha);

        // 2. Dispatch row selection on sorted state
        $reqSelect = new Request(
            method: 'POST',
            uri: '/oshim/ui/action',
            headers: ['Content-Type' => 'application/json'],
            rawBody: (string)json_encode([
                'component' => 'datagrid',
                'id'        => $grid->getId(),
                'action'    => 'toggleSelect',
                'payload'   => ['id' => 's2'],
                'state'     => $dataSort['statePayload'],
                'sig'       => $dataSort['sig'],
            ])
        );

        $respSelect = $uiManager->handleAction($reqSelect);
        $this->assertSame(Response::HTTP_OK, $respSelect->getStatusCode());

        $dataSelect = json_decode($respSelect->getContent(), true);
        $this->assertTrue($dataSelect['success']);
        $this->assertSame(['s2'], $dataSelect['state']['selectedIds']);
        $this->assertStringContains('oshim-table__row--selected', $dataSelect['html']);
    }

    public function testHandleActionErrorResponses(): void
    {
        $registry = new ComponentRegistry();
        $diffEngine = new DiffEngine();
        $uiManager = new UiManager($registry, $diffEngine);

        // 1. Missing component or action -> 400
        $reqEmpty = new Request(method: 'POST', uri: '/oshim/ui/action', headers: ['Content-Type' => 'application/json'], rawBody: (string)json_encode([]));
        $respEmpty = $uiManager->handleAction($reqEmpty);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $respEmpty->getStatusCode());

        // 2. Unregistered component -> 404
        $req404 = new Request(
            method: 'POST',
            uri: '/oshim/ui/action',
            headers: ['Content-Type' => 'application/json'],
            rawBody: (string)json_encode([
                'component' => 'ghost_widget',
                'id'        => 'cmp_ghost',
                'action'    => 'doSomething',
                'state'     => [],
                'sig'       => 'some_sig',
            ])
        );
        $resp404 = $uiManager->handleAction($req404);
        $this->assertSame(Response::HTTP_NOT_FOUND, $resp404->getStatusCode());

        // 3. Forged HMAC signature -> 403
        $modal = $registry->resolve('modal');
        $req403 = new Request(
            method: 'POST',
            uri: '/oshim/ui/action',
            headers: ['Content-Type' => 'application/json'],
            rawBody: (string)json_encode([
                'component' => 'modal',
                'id'        => $modal->getId(),
                'action'    => 'open',
                'state'     => ['open' => false],
                'sig'       => 'forged_invalid_signature_hash',
            ])
        );
        $resp403 = $uiManager->handleAction($req403);
        $this->assertSame(Response::HTTP_FORBIDDEN, $resp403->getStatusCode());

        // 4. Malformed state string -> 403
        $reqMalformed = new Request(
            method: 'POST',
            uri: '/oshim/ui/action',
            headers: ['Content-Type' => 'application/json'],
            rawBody: (string)json_encode([
                'component' => 'modal',
                'id'        => $modal->getId(),
                'action'    => 'open',
                'state'     => 'NOT_VALID_BASE64_OR_JSON!@@#',
                'sig'       => 'any_sig',
            ])
        );
        $respMalformed = $uiManager->handleAction($reqMalformed);
        $this->assertSame(Response::HTTP_FORBIDDEN, $respMalformed->getStatusCode());

        // 5. Blocked lifecycle action -> 400
        $btn = $registry->resolve('button');
        $validSig = $btn->generateSignature([]);
        $reqBlocked = new Request(
            method: 'POST',
            uri: '/oshim/ui/action',
            headers: ['Content-Type' => 'application/json'],
            rawBody: (string)json_encode([
                'component' => 'button',
                'id'        => $btn->getId(),
                'action'    => 'mount',
                'state'     => [],
                'sig'       => $validSig,
            ])
        );
        $respBlocked = $uiManager->handleAction($reqBlocked);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $respBlocked->getStatusCode());
        $dataBlocked = json_decode($respBlocked->getContent(), true);
        $this->assertSame('UI_ERROR', $dataBlocked['code']);
    }
}
