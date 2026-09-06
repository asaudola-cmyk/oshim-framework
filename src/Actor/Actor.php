<?php
declare(strict_types=1);

namespace Oshim\Actor;

use Throwable;

/**
 * 🛡️ Sovereign Actor Base Class (Erlang / Akka Replacement)
 * 
 * WHY: Replaces shared-memory concurrency locks with isolated state.
 * Each actor possesses private state and processes messages strictly sequentially
 * from its own mailbox, eliminating data races by mathematical definition.
 */
abstract class Actor
{
    protected ?ActorRef $self = null;
    protected ?ActorSystem $system = null;

    public function setContext(ActorRef $self, ActorSystem $system): void
    {
        $this->self = $self;
        $this->system = $system;
    }

    /**
     * Handles incoming messages from the mailbox.
     */
    abstract public function receive(mixed $message): void;

    /**
     * Lifecycle callback invoked before processing any messages.
     */
    public function preStart(): void {}

    /**
     * Lifecycle callback invoked after the actor has terminated.
     */
    public function postStop(): void {}

    /**
     * Lifecycle callback invoked before restarting after an uncaught exception.
     */
    public function preRestart(Throwable $reason): void {}
}
