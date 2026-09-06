<?php
declare(strict_types=1);

namespace Oshim\Actor;

use Oshim\Concurrency\Channel;

/**
 * 📬 Sovereign Actor Reference
 * 
 * WHY: Provides an immutable proxy handle to an actor. Ensures that callers
 * can never directly access or mutate the actor's internal private state.
 */
final class ActorRef
{
    private string $id;
    private Channel $mailbox;
    private ActorSystem $system;

    public function __construct(string $id, Channel $mailbox, ActorSystem $system)
    {
        $this->id = $id;
        $this->mailbox = $mailbox;
        $this->system = $system;
    }

    /**
     * Asynchronously delivers a message to the actor's mailbox (Fire-and-Forget).
     */
    public function tell(mixed $message): void
    {
        $this->mailbox->send($message);
    }

    /**
     * Synchronously queries the actor and awaits a response on a private channel (Ask pattern).
     */
    public function ask(mixed $message): mixed
    {
        $replyChannel = new Channel(1);
        $envelope = [
            'payload' => $message,
            'reply_to' => $replyChannel
        ];
        $this->mailbox->send($envelope);
        return $replyChannel->receive();
    }

    public function getId(): string
    {
        return $this->id;
    }
}
