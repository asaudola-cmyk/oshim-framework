<?php
declare(strict_types=1);

namespace Oshim\Ui\Components;

use Oshim\Ui\Component;

class DataGrid extends Component
{
    /** @var array<array{key: string, label?: string, sortable?: bool, searchable?: bool, width?: string, align?: string, render?: callable}> */
    protected array $columns = [];
    protected array $items = [];
    protected int $total = 0;
    protected array $perPageOptions = [10, 25, 50, 100];
    protected bool $selectable = true;
    protected string $idKey = 'id';
    protected array $bulkActions = []; // array of ['action' => '', 'label' => '', 'variant' => 'danger|primary']
    protected string $emptyMessage = 'No records found matching your criteria.';
    protected string $class = '';

    public function mount(array $props): void
    {
        $this->columns = (array)($props['columns'] ?? []);
        $this->items = (array)($props['items'] ?? []);
        $this->total = max(0, (int)($props['total'] ?? count($this->items)));
        $this->perPageOptions = (array)($props['perPageOptions'] ?? [10, 25, 50, 100]);
        $this->selectable = (bool)($props['selectable'] ?? true);
        $this->idKey = (string)($props['idKey'] ?? 'id');
        $this->bulkActions = (array)($props['bulkActions'] ?? []);
        $this->emptyMessage = (string)($props['emptyMessage'] ?? 'No records found matching your criteria.');
        $this->class = (string)($props['class'] ?? '');

        if (!isset($this->state['columns'])) {
            $this->state['columns'] = $this->columns;
        }
        if (!isset($this->state['items'])) {
            $this->state['items'] = $this->items;
        }
        if (!isset($this->state['total'])) {
            $this->state['total'] = $this->total;
        }
        if (!isset($this->state['perPageOptions'])) {
            $this->state['perPageOptions'] = $this->perPageOptions;
        }
        if (!isset($this->state['selectable'])) {
            $this->state['selectable'] = $this->selectable;
        }
        if (!isset($this->state['idKey'])) {
            $this->state['idKey'] = $this->idKey;
        }
        if (!isset($this->state['bulkActions'])) {
            $this->state['bulkActions'] = $this->bulkActions;
        }
        if (!isset($this->state['emptyMessage'])) {
            $this->state['emptyMessage'] = $this->emptyMessage;
        }
        if (!isset($this->state['class'])) {
            $this->state['class'] = $this->class;
        }

        if (!isset($this->state['page'])) {
            $this->state['page'] = max(1, (int)($props['page'] ?? 1));
        }
        if (!isset($this->state['perPage'])) {
            $pp = (int)($props['perPage'] ?? 10);
            $this->state['perPage'] = in_array($pp, [5, 10, 25, 50, 100], true) ? $pp : 10;
        }
        if (!isset($this->state['sortKey'])) {
            $this->state['sortKey'] = $props['sortKey'] ?? null;
        }
        if (!isset($this->state['sortOrder'])) {
            $this->state['sortOrder'] = strtolower((string)($props['sortOrder'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        }
        if (!isset($this->state['search'])) {
            $this->state['search'] = (string)($props['search'] ?? '');
        }
        if (!isset($this->state['selectedIds'])) {
            $this->state['selectedIds'] = (array)($props['selectedIds'] ?? []);
        }
    }

    public function hydrate(array $state): void
    {
        parent::hydrate($state);

        if (isset($this->state['columns'])) {
            $this->columns = (array)$this->state['columns'];
        }
        if (isset($this->state['items'])) {
            $this->items = (array)$this->state['items'];
        }
        if (isset($this->state['total'])) {
            $this->total = (int)$this->state['total'];
        }
        if (isset($this->state['perPageOptions'])) {
            $this->perPageOptions = (array)$this->state['perPageOptions'];
        }
        if (isset($this->state['selectable'])) {
            $this->selectable = (bool)$this->state['selectable'];
        }
        if (isset($this->state['idKey'])) {
            $this->idKey = (string)$this->state['idKey'];
        }
        if (isset($this->state['bulkActions'])) {
            $this->bulkActions = (array)$this->state['bulkActions'];
        }
        if (isset($this->state['emptyMessage'])) {
            $this->emptyMessage = (string)$this->state['emptyMessage'];
        }
        if (isset($this->state['class'])) {
            $this->class = (string)$this->state['class'];
        }
    }

    public function handleSort(array|string $payload): void
    {
        $key = is_array($payload) ? (string)($payload['key'] ?? ($payload['col'] ?? '')) : (string)$payload;
        if ($key === '') {
            return;
        }

        if (($this->state['sortKey'] ?? null) === $key) {
            $this->state['sortOrder'] = ($this->state['sortOrder'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
        } else {
            $this->state['sortKey'] = $key;
            $this->state['sortOrder'] = 'asc';
        }
        $this->state['page'] = 1;
    }

    public function sort(array|string $payload): void
    {
        $this->handleSort($payload);
    }

    public function handlePage(array|int $payload): void
    {
        $targetPage = is_array($payload) ? (int)($payload['page'] ?? 1) : (int)$payload;
        $perPage = max(1, (int)($this->state['perPage'] ?? 10));
        $maxPage = max(1, (int)ceil($this->total / $perPage));
        $this->state['page'] = max(1, min($maxPage, $targetPage));
    }

    public function setPage(array|int $payload): void
    {
        $this->handlePage($payload);
    }

    public function page(array|int $payload): void
    {
        $this->handlePage($payload);
    }

    public function handleSearch(array|string $payload): void
    {
        $query = is_array($payload) ? (string)($payload['query'] ?? ($payload['search'] ?? ($payload['value'] ?? ''))) : (string)$payload;
        $this->state['search'] = trim($query);
        $this->state['page'] = 1;
    }

    public function search(array|string $payload): void
    {
        $this->handleSearch($payload);
    }

    public function handleSelectRow(array|string|int $payload): void
    {
        $id = is_array($payload) ? (string)($payload['id'] ?? '') : (string)$payload;
        if ($id === '') {
            return;
        }

        $selected = (array)($this->state['selectedIds'] ?? []);
        $key = array_search($id, $selected, false);
        if ($key !== false) {
            unset($selected[$key]);
        } else {
            $selected[] = $id;
        }
        $this->state['selectedIds'] = array_values($selected);
    }

    public function toggleSelect(array|string|int $payload): void
    {
        $this->handleSelectRow($payload);
    }

    public function handleSelectAll(array $payload = []): void
    {
        $selected = (array)($this->state['selectedIds'] ?? []);
        $visibleIds = [];
        foreach ($this->items as $item) {
            if (isset($item[$this->idKey])) {
                $visibleIds[] = (string)$item[$this->idKey];
            }
        }

        if (count($selected) >= count($visibleIds) && !empty($visibleIds)) {
            $this->state['selectedIds'] = [];
        } else {
            $this->state['selectedIds'] = $visibleIds;
        }
    }

    public function selectAll(array $payload = []): void
    {
        $this->handleSelectAll($payload);
    }

    public function clearSelection(array $payload = []): void
    {
        $this->state['selectedIds'] = [];
    }

    public function handleBulkAction(array $payload): void
    {
        $action = (string)($payload['action'] ?? '');
        $this->emit('bulk_action', [
            'action' => $action,
            'ids'    => $this->state['selectedIds'] ?? [],
        ]);
    }

    public function render(): string
    {
        $perPage = max(1, (int)($this->state['perPage'] ?? 10));
        $searchVal = trim((string)($this->state['search'] ?? ''));
        $sortKey = $this->state['sortKey'] ?? null;
        $sortOrder = $this->state['sortOrder'] ?? 'asc';
        $selectedIds = (array)($this->state['selectedIds'] ?? []);

        // Filter items in-memory if search is provided
        $filteredItems = $this->items;
        if ($searchVal !== '' && !empty($filteredItems)) {
            $filteredItems = array_values(array_filter($filteredItems, function ($row) use ($searchVal) {
                foreach ((array)$row as $v) {
                    if (is_scalar($v) && stripos((string)$v, $searchVal) !== false) {
                        return true;
                    }
                }
                return false;
            }));
        }

        // Sort items in-memory if sortKey is provided
        if ($sortKey !== null && !empty($filteredItems)) {
            usort($filteredItems, function ($a, $b) use ($sortKey, $sortOrder) {
                $valA = is_array($a) ? ($a[$sortKey] ?? '') : ($a->$sortKey ?? '');
                $valB = is_array($b) ? ($b[$sortKey] ?? '') : ($b->$sortKey ?? '');
                if (is_numeric($valA) && is_numeric($valB)) {
                    $cmp = $valA <=> $valB;
                } else {
                    $cmp = strcasecmp((string)$valA, (string)$valB);
                }
                return $sortOrder === 'desc' ? -$cmp : $cmp;
            });
        }

        // If explicitly set in state/props, use total; otherwise use count of filtered items
        $totalItems = isset($this->state['total']) ? (int)$this->state['total'] : (!empty($this->items) ? count($filteredItems) : $this->total);
        $this->total = $totalItems;
        $maxPage = max(1, (int)ceil($totalItems / $perPage));
        $page = max(1, min($maxPage, (int)($this->state['page'] ?? 1)));
        $this->state['page'] = $page;

        // Slice items for pagination if full item set is in memory (and total items equals in-memory items count)
        $displayItems = $filteredItems;
        if (count($filteredItems) > $perPage && count($filteredItems) === $totalItems) {
            $displayItems = array_slice($filteredItems, ($page - 1) * $perPage, $perPage);
        }

        $startItem = $totalItems > 0 ? ($page - 1) * $perPage + 1 : 0;
        $endItem = min($totalItems, $page * $perPage);

        $encodedState = base64_encode(json_encode($this->state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $sig = $this->generateSignature($this->state);

        $html = '<div class="oshim-datagrid ' . $this->escape($this->class) . '" data-oshim-id="' . $this->escape($this->id) . '" data-oshim-component="' . static::getComponentAlias() . '" data-oshim-state="' . $this->escape($encodedState) . '" data-oshim-sig="' . $this->escape($sig) . '">';

        // Top Toolbar
        $html .= '<div class="oshim-datagrid__toolbar">';

        // Live Search Input
        $html .= '<div class="oshim-datagrid__search">';
        $html .= '<svg class="oshim-search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
        $html .= '<input type="search" class="oshim-input oshim-datagrid__search-input" placeholder="Search..." value="' . $this->escape($searchVal) . '" oshim:input="handleSearch" data-oshim-action="search" data-oshim-debounce="300">';
        $html .= '</div>';

        // Bulk Actions
        if (!empty($this->bulkActions) && !empty($selectedIds)) {
            $html .= '<div class="oshim-datagrid__bulk-actions">';
            $html .= '<span class="oshim-badge oshim-badge--cyan oshim-badge--sm">' . count($selectedIds) . ' selected</span>';
            foreach ($this->bulkActions as $ba) {
                $variant = $ba['variant'] ?? 'secondary';
                $html .= '<button type="button" class="oshim-btn oshim-btn--xs oshim-btn--' . $this->escape($variant) . '" oshim:click="handleBulkAction" data-oshim-action="handleBulkAction" data-oshim-payload=\'{"action":"' . $this->escape($ba['action']) . '"}\'>' . $this->escape($ba['label']) . '</button>';
            }
            $html .= '</div>';
        }

        $html .= '</div>'; // End toolbar

        // Table
        $html .= '<div class="oshim-table-wrapper"><table class="oshim-table oshim-glass-table oshim-table--hover oshim-table--bordered">';
        $html .= '<thead class="oshim-table__head"><tr>';

        if ($this->selectable) {
            $allSelected = !empty($displayItems) && count($selectedIds) >= count($displayItems);
            $html .= '<th class="oshim-table__th oshim-table__th--checkbox" style="width: 40px;"><input type="checkbox" class="oshim-checkbox" ' . ($allSelected ? 'checked="checked"' : '') . ' oshim:change="handleSelectAll" data-oshim-action="selectAll"></th>';
        }

        foreach ($this->columns as $col) {
            $key = $col['key'] ?? '';
            $sortable = !empty($col['sortable']);
            $align = in_array($col['align'] ?? '', ['left', 'center', 'right'], true) ? $col['align'] : 'left';
            $width = !empty($col['width']) ? ' style="width: ' . $this->escape($col['width']) . ';"' : '';

            $html .= '<th class="oshim-table__th text-' . $align . '"' . $width . '>';
            if ($sortable) {
                $isCurrentSort = ($sortKey === $key);
                $sortIndicator = $isCurrentSort ? ($sortOrder === 'asc' ? ' ▲' : ' ▼') : ' ↕';
                $html .= '<button type="button" class="oshim-datagrid__sort-btn' . ($isCurrentSort ? ' oshim-datagrid__sort-btn--active' : '') . '" oshim:click="handleSort" data-oshim-action="sort" data-oshim-payload=\'{"key":"' . $this->escape($key) . '"}\'>';
                $html .= $this->escape($col['label'] ?? $key);
                $html .= '<span class="oshim-datagrid__sort-icon">' . $sortIndicator . '</span>';
                $html .= '</button>';
            } else {
                $html .= $this->escape($col['label'] ?? $key);
            }
            $html .= '</th>';
        }
        $html .= '</tr></thead>';

        // Body
        $html .= '<tbody class="oshim-table__body">';
        if (empty($displayItems)) {
            $colSpan = count($this->columns) + ($this->selectable ? 1 : 0);
            $html .= '<tr class="oshim-table__empty-row"><td colspan="' . max(1, $colSpan) . '" class="oshim-table__empty-cell">';
            $html .= '<div class="oshim-empty-state"><p class="oshim-empty-state__text">' . $this->escape($this->emptyMessage) . '</p></div>';
            $html .= '</td></tr>';
        } else {
            foreach ($displayItems as $row) {
                $rowId = (string)($row[$this->idKey] ?? '');
                $isSelected = in_array($rowId, $selectedIds, false);

                $html .= '<tr class="oshim-table__row' . ($isSelected ? ' oshim-table__row--selected' : '') . '">';
                if ($this->selectable) {
                    $html .= '<td class="oshim-table__td oshim-table__td--checkbox"><input type="checkbox" class="oshim-checkbox" value="' . $this->escape($rowId) . '" ' . ($isSelected ? 'checked="checked"' : '') . ' oshim:change="handleSelectRow" data-oshim-action="toggleSelect" data-oshim-payload=\'{"id":"' . $this->escape($rowId) . '"}\'></td>';
                }
                foreach ($this->columns as $col) {
                    $key = $col['key'] ?? '';
                    $val = $row[$key] ?? null;
                    $align = in_array($col['align'] ?? '', ['left', 'center', 'right'], true) ? $col['align'] : 'left';
                    $cellHtml = isset($col['render']) && is_callable($col['render'])
                        ? (string)$col['render']($val, $row)
                        : ($val === null ? '<span class="oshim-null">NULL</span>' : $this->escape((string)$val));
                    $html .= '<td class="oshim-table__td text-' . $align . '">' . $cellHtml . '</td>';
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table></div>';

        // Bottom Pagination & Results Summary
        $html .= '<div class="oshim-datagrid__pagination">';
        $html .= '<div class="oshim-datagrid__summary">Showing <span class="text-white">' . ($totalItems > 0 ? $startItem : 0) . '</span> to <span class="text-white">' . $endItem . '</span> of <span class="text-white">' . $totalItems . '</span> entries</div>';

        // Page Buttons
        $html .= '<div class="oshim-pagination">';
        $html .= '<button type="button" class="oshim-btn oshim-btn--xs oshim-btn--glass" ' . ($page <= 1 ? 'disabled="disabled"' : '') . ' oshim:click="handlePage" data-oshim-action="page" data-oshim-payload=\'{"page":1}\'>«</button>';
        $html .= '<button type="button" class="oshim-btn oshim-btn--xs oshim-btn--glass" ' . ($page <= 1 ? 'disabled="disabled"' : '') . ' oshim:click="handlePage" data-oshim-action="page" data-oshim-payload=\'{"page":' . max(1, $page - 1) . '}\'>‹</button>';

        // Render Page Number Jumps (max 5 buttons)
        $startP = max(1, $page - 2);
        $endP = min($maxPage, $startP + 4);
        if ($endP - $startP < 4) {
            $startP = max(1, $endP - 4);
        }

        for ($p = $startP; $p <= $endP; $p++) {
            $isCur = ($p === $page);
            $html .= '<button type="button" class="oshim-btn oshim-btn--xs ' . ($isCur ? 'oshim-btn--primary' : 'oshim-btn--glass') . '" oshim:click="handlePage" data-oshim-action="page" data-oshim-payload=\'{"page":' . $p . '}\'>' . $p . '</button>';
        }

        $html .= '<button type="button" class="oshim-btn oshim-btn--xs oshim-btn--glass" ' . ($page >= $maxPage ? 'disabled="disabled"' : '') . ' oshim:click="handlePage" data-oshim-action="page" data-oshim-payload=\'{"page":' . min($maxPage, $page + 1) . '}\'>›</button>';
        $html .= '<button type="button" class="oshim-btn oshim-btn--xs oshim-btn--glass" ' . ($page >= $maxPage ? 'disabled="disabled"' : '') . ' oshim:click="handlePage" data-oshim-action="page" data-oshim-payload=\'{"page":' . $maxPage . '}\'>»</button>';
        $html .= '</div></div>';

        $html .= '</div>';
        return $html;
    }
}
