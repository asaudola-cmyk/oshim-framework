<?php
declare(strict_types=1);

namespace Oshim\Ui;

class DiffEngine
{
    /**
     * Compute atomic patch operations between old and new HTML snippets.
     *
     * @return array<array{op: string, id: string, key?: string, val?: string, text?: string, html?: string, eventName?: string, detail?: mixed}>
     */
    public function diff(string $oldHtml, string $newHtml, string $rootId): array
    {
        if ($oldHtml === $newHtml) {
            return [];
        }

        return [
            [
                'op'   => 'REPLACE_HTML',
                'id'   => $rootId,
                'html' => $newHtml,
            ],
        ];
    }

    /**
     * Helper to create a custom atomic patch operation.
     */
    public function createPatch(string $op, string $id, array $params = []): array
    {
        return array_merge(['op' => $op, 'id' => $id], $params);
    }
}
