<?php
declare(strict_types=1);

namespace Oshim\Database\ORM\Traits;

trait HasTimestamps
{
    public bool $timestamps = true;
    public string $createdAtColumn = 'created_at';
    public string $updatedAtColumn = 'updated_at';

    public function updateTimestamps(): void
    {
        $time = date('Y-m-d H:i:s');

        if (!$this->exists && !isset($this->attributes[$this->createdAtColumn])) {
            $this->setAttribute($this->createdAtColumn, $time);
        }

        $this->setAttribute($this->updatedAtColumn, $time);
    }
}
