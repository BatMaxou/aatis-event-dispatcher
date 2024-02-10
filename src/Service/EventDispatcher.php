<?php

namespace Aatis\EventDispatcher\Service;

use Aatis\EventDispatcher\Entity\Event;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(private readonly ListenerProviderInterface $listenerProvider)
    {
    }

    public function dispatch(Event $event): Event
    {
        return $event;
    }
}
