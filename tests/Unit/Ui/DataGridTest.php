<?php
declare(strict_types=1);

namespace Tests\Unit\Ui;

use Oshim\Tests\Harness\TestCase;
use Oshim\Ui\Components\DataGrid;

class DataGridTest extends TestCase
{
    protected array $sampleItems = [
        ['id' => 'vps_1', 'name' => 'Web App Frankfurt', 'cores' => 4, 'memory' => 8192, 'status' => 'running'],
        ['id' => 'vps_2', 'name' => 'Database Primary', 'cores' => 16, 'memory' => 65536, 'status' => 'running'],
        ['id' => 'vps_3', 'name' => 'Redis Cache', 'cores' => 2, 'memory' => 4096, 'status' => 'stopped'],
        ['id' => 'vps_4', 'name' => 'Backup Ingestion', 'cores' => 8, 'memory' => 16384, 'status' => 'idle'],
    ];

    public function testDataGridSortingAscendingDescendingAndToggle(): void
    {
        $grid = new DataGrid([
            'columns' => [
                ['key' => 'id', 'label' => 'ID', 'sortable' => false],
                ['key' => 'name', 'label' => 'Name', 'sortable' => true],
                ['key' => 'cores', 'label' => 'vCPU', 'sortable' => true],
            ],
            'items' => $this->sampleItems,
            'total' => 4,
            'page' => 1,
            'perPage' => 10,
        ]);

        // Initial state
        $html1 = $grid->render();
        $this->assertStringContains('oshim:click="handleSort"', $html1);
        $this->assertStringContains('↕', $html1); // Neutral indicator

        // 1. Sort by name asc
        $grid->handleSort('name');
        $this->assertSame('name', $grid->getState()['sortKey']);
        $this->assertSame('asc', $grid->getState()['sortOrder']);
        $html2 = $grid->render();
        $this->assertStringContains('▲', $html2);

        // 2. Toggle sort by name -> desc
        $grid->handleSort(['key' => 'name']);
        $this->assertSame('name', $grid->getState()['sortKey']);
        $this->assertSame('desc', $grid->getState()['sortOrder']);
        $html3 = $grid->render();
        $this->assertStringContains('▼', $html3);

        // 3. Change sort key to cores -> asc
        $grid->sort(['key' => 'cores']);
        $this->assertSame('cores', $grid->getState()['sortKey']);
        $this->assertSame('asc', $grid->getState()['sortOrder']);
    }

    public function testPaginationCalculationOffsetAndPageCount(): void
    {
        $grid = new DataGrid([
            'columns' => [
                ['key' => 'name', 'label' => 'Name'],
            ],
            'items' => $this->sampleItems,
            'total' => 45,
            'page' => 2,
            'perPage' => 10,
        ]);

        $html = $grid->render();
        $this->assertStringContains('Showing <span class="text-white">11</span> to <span class="text-white">20</span> of <span class="text-white">45</span> entries', $html);
        $this->assertStringContains('oshim:click="handlePage"', $html);
        $this->assertStringContains('oshim-btn--primary', $html); // Active page button
    }

    public function testPaginationBoundaryClamping(): void
    {
        $grid = new DataGrid([
            'columns' => [['key' => 'name', 'label' => 'Name']],
            'items' => $this->sampleItems,
            'total' => 25,
            'perPage' => 10, // 3 total pages
        ]);

        // Out-of-bounds upper clamp
        $grid->handlePage(999);
        $this->assertSame(3, $grid->getState()['page']);

        // Out-of-bounds lower clamp
        $grid->handlePage(-10);
        $this->assertSame(1, $grid->getState()['page']);
    }

    public function testDataGridCaseInsensitiveSubstringFilter(): void
    {
        $grid = new DataGrid([
            'columns' => [['key' => 'name', 'label' => 'Name', 'searchable' => true]],
            'items' => $this->sampleItems,
            'search' => 'Database',
        ]);

        $this->assertSame('Database', $grid->getState()['search']);
        $html = $grid->render();
        $this->assertStringContains('value="Database"', $html);

        // Update search query
        $grid->handleSearch(['query' => 'redis']);
        $this->assertSame('redis', $grid->getState()['search']);
        $this->assertSame(1, $grid->getState()['page']); // Resets to page 1 on search
    }

    public function testBulkSelectionStateManagement(): void
    {
        $grid = new DataGrid([
            'columns' => [['key' => 'id', 'label' => 'ID'], ['key' => 'name', 'label' => 'Name']],
            'items' => $this->sampleItems,
            'selectable' => true,
            'bulkActions' => [
                ['action' => 'deleteNodes', 'label' => 'Delete Selected', 'variant' => 'danger'],
            ],
        ]);

        // Toggle single row
        $grid->handleSelectRow('vps_1');
        $this->assertSame(['vps_1'], $grid->getState()['selectedIds']);

        // Toggle another row
        $grid->handleSelectRow(['id' => 'vps_2']);
        $this->assertSame(['vps_1', 'vps_2'], $grid->getState()['selectedIds']);

        // Untoggle first row
        $grid->handleSelectRow('vps_1');
        $this->assertSame(['vps_2'], $grid->getState()['selectedIds']);

        // Select All
        $grid->handleSelectAll();
        $this->assertCount(4, $grid->getState()['selectedIds']);
        $this->assertTrue(in_array('vps_1', $grid->getState()['selectedIds'], true));
        $this->assertTrue(in_array('vps_4', $grid->getState()['selectedIds'], true));

        // Render with bulk actions toolbar
        $html = $grid->render();
        $this->assertStringContains('4 selected', $html);
        $this->assertStringContains('Delete Selected', $html);

        // Bulk action event trigger
        $grid->handleBulkAction(['action' => 'deleteNodes']);
        $emitted = $grid->getEmittedEvents();
        $this->assertCount(1, $emitted);
        $this->assertSame('bulk_action', $emitted[0]['event']);
        $this->assertSame('deleteNodes', $emitted[0]['payload']['action']);

        // Clear Selection
        $grid->clearSelection();
        $this->assertSame([], $grid->getState()['selectedIds']);
    }
}
