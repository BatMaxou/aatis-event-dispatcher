<?php

namespace Aatis\EventDispatcher\Service;

use Aatis\DependencyInjection\Interface\ContainerInterface;
use Aatis\EventDispatcher\Entity\Event;
use Aatis\EventDispatcher\Interface\ListenerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

class ListenerProvider implements ListenerProviderInterface
{
    /**
     * @var iterable<ListenerInterface>
     */
    private iterable $listeners = [];

    public function __construct(private readonly ContainerInterface $container)
    {
        /** @var ListenerInterface $listener */
        foreach ($this->container->getByInterface(ListenerInterface::class) as $listener) {
            $this->addListener($listener);
        }
    }

    private function addListener(ListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function getListenersForEvent(Event $event): iterable
    {
        foreach ($this->listeners as $listener) {
            foreach ($listener->getListenedEvents() as $eventClass => $_) {
                if ($event instanceof $eventClass) {
                    yield $listener;
                }
            }
        }
    }
}
