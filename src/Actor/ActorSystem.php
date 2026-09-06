<?php
declare(strict_types=1);

namespace Oshim\Actor;

use Fiber;
use Throwable;
use SplQueue;
use RuntimeException;
use Oshim\Concurrency\Channel;
use Oshim\Concurrency\FiberScheduler;

/**
 * 👑 Sovereign Actor System & Self-Healing Supervisor Engine (Erlang OTP Replacement)
 * 
 * WHY: Provides fault tolerance and self-healing supervision ("Let It Crash").
 * If an actor crashes due to an unhandled exception, the supervisor automatically
 * isolates the failure, executes the recovery protocol, and restarts the actor
 * without crashing other processes or dropping client connections.
 */
final class ActorSystem
{
    private string $name;
    private FiberScheduler $scheduler;
    /** @var array<string, array{ref: ActorRef, mailbox: Channel, factory: callable, actor: Actor, restarts: int}> */
    private array $actors = [];

    public function __construct(string $name = 'sovereign_actor_system')
    {
        $this->name = $name;
        $this->scheduler = new FiberScheduler();
    }

    /**
     * Spawns a supervised actor within the system.
     * 
     * @param string $id Unique identifier for the actor.
     * @param callable(): Actor $actorFactory Factory closure instantiating the actor.
     * @return ActorRef Immutable handle to the spawned actor.
     */
    public function spawn(string $id, callable $actorFactory): ActorRef
    {
        if (isset($this->actors[$id])) {
            throw new RuntimeException("Actor with ID '{$id}' already exists");
        }

        $mailbox = new Channel(256);
        $ref = new ActorRef($id, $mailbox, $this);

        /** @var Actor $actor */
        $actor = $actorFactory();
        $actor->setContext($ref, $this);
        $actor->preStart();

        $this->actors[$id] = [
            'ref' => $ref,
            'mailbox' => $mailbox,
            'factory' => $actorFactory,
            'actor' => $actor,
            'restarts' => 0
        ];

        // Launch supervised coroutine loop
        $this->scheduler->spawn(function () use ($id) {
            $this->actorLoop($id);
        });

        return $ref;
    }

    /**
     * Internal supervised message processing loop.
     */
    private function actorLoop(string $id): void
    {
        while (isset($this->actors[$id])) {
            $actorData = &$this->actors[$id];
            $mailbox = $actorData['mailbox'];

            if ($mailbox->count() > 0) {
                $rawMessage = $mailbox->receive();
                if ($rawMessage === '__ACTOR_POISON_PILL__') {
                    $actorData['actor']->postStop();
                    unset($this->actors[$id]);
                    break;
                }

                $replyChannel = null;
                $message = $rawMessage;

                if (is_array($rawMessage) && isset($rawMessage['reply_to'], $rawMessage['payload'])) {
                    $replyChannel = $rawMessage['reply_to'];
                    $message = $rawMessage['payload'];
                }

                try {
                    // Process message sequentially
                    $actorData['actor']->receive($message);

                    // If ask pattern, reply back if unreplied
                    if ($replyChannel instanceof Channel && $replyChannel->count() === 0) {
                        $replyChannel->send(true);
                    }
                } catch (Throwable $reason) {
                    // 🛡️ SUPERVISORY RECOVERY: Self-healing "Let It Crash" protocol
                    $actorData['restarts']++;
                    if (defined('STDERR')) {
                        fprintf(
                            STDERR,
                            "\033[1;33m[Supervisor Alert]\033[0m Actor '%s' crashed (%s). Triggering self-healing restart #%d\n",
                            $id,
                            $reason->getMessage(),
                            $actorData['restarts']
                        );
                    }

                    $actorData['actor']->preRestart($reason);

                    // Re-instantiate fresh actor state
                    $freshActor = ($actorData['factory'])();
                    $freshActor->setContext($actorData['ref'], $this);
                    $freshActor->preStart();
                    $actorData['actor'] = $freshActor;

                    if ($replyChannel instanceof Channel) {
                        $replyChannel->send([
                            'error' => 'ActorCrashedAndRecovered',
                            'message' => $reason->getMessage()
                        ]);
                    }
                }
            } else {
                if ($this->shouldTerminateWhenIdle && $this->getTotalMailboxCount() === 0) {
                    break;
                }
            }

            FiberScheduler::yield();
        }
    }

    private bool $shouldTerminateWhenIdle = false;

    public function getTotalMailboxCount(): int
    {
        $count = 0;
        foreach ($this->actors as $actor) {
            $count += $actor['mailbox']->count();
        }
        return $count;
    }

    /**
     * Runs until all active actor mailboxes are drained.
     */
    public function runUntilIdle(): int
    {
        $this->shouldTerminateWhenIdle = true;
        return $this->scheduler->run();
    }

    /**
     * Sends a poison pill to stop the actor gracefully.
     */
    public function stop(string $id): void
    {
        if (isset($this->actors[$id])) {
            $this->actors[$id]['mailbox']->send('__ACTOR_POISON_PILL__');
        }
    }

    /**
     * Runs the actor system driving message dispatch continuously.
     */
    public function run(): int
    {
        return $this->scheduler->run();
    }

    public function getActorCount(): int
    {
        return count($this->actors);
    }
}
