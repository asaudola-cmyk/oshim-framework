<?php
declare(strict_types=1);

namespace Oshim\Cron;

use Closure;

class Scheduler
{
    private static ?self $instance = null;
    /** @var list<ScheduleEvent> */
    private array $events = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function call(Closure|callable $callback, string $description = ''): ScheduleEvent
    {
        $event = new ScheduleEvent($callback, $description ?: 'Anonymous Callback');
        self::getInstance()->events[] = $event;
        return $event;
    }

    public static function command(string $command): ScheduleEvent
    {
        return self::call(function() use ($command) {
            // Execute CLI command in sub-process
            $bin = dirname(__DIR__, 2) . '/bin/oshim';
            if (file_exists($bin)) {
                exec("php {$bin} {$command}");
            }
        }, "Command: {$command}");
    }

    /**
     * Run all due events.
     */
    public function runDue(int $timestamp = 0): array
    {
        $results = [];
        foreach ($this->events as $event) {
            if ($event->isDue($timestamp)) {
                $start = microtime(true);
                $event->run();
                $elapsed = round((microtime(true) - $start) * 1000, 2);
                $results[] = [
                    'description' => $event->getDescription(),
                    'expression' => $event->getExpression(),
                    'duration_ms' => $elapsed,
                    'status' => 'SUCCESS',
                ];
            }
        }
        return $results;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function clear(): void
    {
        $this->events = [];
    }
}
