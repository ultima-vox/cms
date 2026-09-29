<?php

declare(strict_types=1);

namespace Core\Extension\Api;

use Closure;
use LogicException;
use RuntimeException;

final class EventsApi
{
    /** @var array<class-string, list<Closure>> */
    private array $listeners = [];

    private bool $frozen = false;

    /** @param class-string $eventClass */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->assertMutable();
        $eventClass = trim($eventClass);

        if ($eventClass === '' || (!class_exists($eventClass) && !interface_exists($eventClass))) {
            throw new RuntimeException(sprintf('Event class "%s" does not exist.', $eventClass));
        }

        $this->listeners[$eventClass][] = Closure::fromCallable($listener);
    }

    public function dispatch(object $event): object
    {
        foreach ($this->listeners as $eventClass => $listeners) {
            if (!$event instanceof $eventClass) {
                continue;
            }

            foreach ($listeners as $listener) {
                $listener($event);
            }
        }

        return $event;
    }

    public function freeze(): void
    {
        $this->frozen = true;
    }

    private function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('Event registry is frozen. Listener registration is only allowed during application boot.');
        }
    }
}
