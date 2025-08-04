<?php

namespace Aatis\EventDispatcher\Service;

use Aatis\EventDispatcher\Event\Event;
use Aatis\EventDispatcher\Event\StoppableEvent;
use Aatis\EventDispatcher\Exception\EventDispatcher\InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(private readonly ListenerProviderInterface $listenerProvider)
    {
    }

    public function dispatch(object $event): Event
    {
        if ($event instanceof Event) {
            foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
                if ($event instanceof StoppableEvent && $event->isPropagationStopped()) {
                    continue;
                }

                $listener($event);
            }
        } else {
            throw new InvalidArgumentException('Event must be an instance of '.Event::class);
        }

        return $event;
    }
}
