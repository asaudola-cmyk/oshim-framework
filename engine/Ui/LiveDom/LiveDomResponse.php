<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

use JsonSerializable;
use Oshim\Http\Response;

/**
 * Standardized response returned from a LiveDOM action execution.
 */
class LiveDomResponse implements JsonSerializable
{
    public function __construct(
        protected bool $success,
        protected string $id,
        protected string $html,
        protected array $snapshot,
        protected array $patches = [],
        protected array $events = [],
        protected ?string $redirect = null,
        protected array $errors = [],
        protected mixed $result = null
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function getPatches(): array
    {
        return $this->patches;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getRedirect(): ?string
    {
        return $this->redirect;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function toArray(): array
    {
        return [
            'success'   => $this->success,
            'id'        => $this->id,
            'html'      => $this->html,
            'snapshot'  => $this->snapshot,
            'patches'   => $this->patches,
            'events'    => $this->events,
            'redirect'  => $this->redirect,
            'errors'    => $this->errors,
            'result'    => $this->result,
        ];
    }

    public function toJson(int $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
    {
        return (string)json_encode($this->toArray(), $flags);
    }

    public function toHttpResponse(int $statusCode = 200): Response
    {
        return Response::json($this->toArray(), $statusCode);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
