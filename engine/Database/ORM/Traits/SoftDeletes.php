<?php
declare(strict_types=1);

namespace Oshim\Database\ORM\Traits;

trait SoftDeletes
{
    public string $deletedAtColumn = 'deleted_at';

    public function isSoftDeleting(): bool
    {
        return true;
    }

    public function trashed(): bool
    {
        return $this->getAttribute($this->deletedAtColumn) !== null;
    }

    public function restore(): bool
    {
        $this->setAttribute($this->deletedAtColumn, null);
        return $this->save();
    }

    public function getDeletedAtColumn(): string
    {
        return $this->deletedAtColumn ?? 'deleted_at';
    }
}
