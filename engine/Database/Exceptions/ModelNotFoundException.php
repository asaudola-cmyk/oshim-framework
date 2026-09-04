<?php
declare(strict_types=1);

namespace Oshim\Database\Exceptions;

class ModelNotFoundException extends DatabaseException
{
    protected string $model;
    protected array $ids;

    public function __construct(string $model = '', array $ids = [])
    {
        $this->model = $model;
        $this->ids = $ids;

        $msg = "No query results for model [{$model}]";
        if (!empty($ids)) {
            $msg .= ' ' . implode(', ', $ids);
        }

        parent::__construct($msg);
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}
