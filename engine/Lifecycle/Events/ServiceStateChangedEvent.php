<?php
declare(strict_types=1);

namespace Oshim\Lifecycle\Events;

class ServiceStateChangedEvent
{
    public function __construct(
        public string $serviceId,
        public ?string $oldState,
        public string $newState,
        public string $action,
        public ?int $actorId = null,
        public string $reason = '',
        public int $timestamp = 0
    ) {
        if ($this->timestamp === 0) {
            $this->timestamp = time();
        }
    }

    public function toArray(): array
    {
        return [
            'service_id' => $this->serviceId,
            'old_state' => $this->oldState,
            'new_state' => $this->newState,
            'action' => $this->action,
            'actor_id' => $this->actorId,
            'reason' => $this->reason,
            'timestamp' => $this->timestamp,
        ];
    }
}
