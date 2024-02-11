<?php

namespace Aatis\EventDispatcher\Service;

use Aatis\EventDispatcher\Event\Event;
use Psr\EventDispatcher\ListenerProviderInterface;
use Aatis\DependencyInjection\Interface\ContainerInterface;
use Aatis\EventDispatcher\Interface\EventSubscriberInterface;
use Aatis\EventDispatcher\Exception\ListenerProvider\InvalidArgumentException;
use Aatis\EventDispatcher\Exception\ListenerProvider\InvalidListenerArgumentException;

class ListenerProvider implements ListenerProviderInterface
{
    /** @var EventSubscriberInterface[] */
    private array $subscribers = [];

    /** @var array<class-string, array<object&callable>> */
    private array $listeners = [];

    public function __construct(private readonly ContainerInterface $container)
    {
        $this->addSubscribers();
        $this->addListeners();
    }

    private function addSubscribers(): void
    {
        /** @var EventSubscriberInterface $subscriber */
        foreach ($this->container->getByInterface(EventSubscriberInterface::class) as $subscriber) {
            $this->addSubscriber($subscriber);
        }
    }

    private function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->subscribers[] = $subscriber;
    }

    private function addListeners(): void
    {
        /** @var object&callable $listener */
        foreach ($this->container->getByTag('event-listener') as $listener) {
            $this->addListener($listener);
        }
    }

    /**
     * @param object&callable $listener
     */
    private function addListener(object $listener): void
    {
        $reflexion = new \ReflectionClass($listener);
        $invokeMethod = $reflexion->getMethod('__invoke');
        $parameters = $invokeMethod->getParameters();

        if (1 !== count($parameters)) {
            throw new InvalidListenerArgumentException('Listener must have only one parameter and it must be an instance of '.Event::class);
        }

        /**
         * @var \ReflectionNamedType|null
         */
        $eventType = $parameters[0]->getType();

        if ($eventType && is_a($eventType->getName(), Event::class, true)) {
            $this->listeners[$eventType->getName()][] = $listener;
        }
    }

    /**
     * @return callable[]
     */
    public function getListenersForEvent(object $event): iterable
    {
        if ($event instanceof Event) {
            foreach ($this->subscribers as $subscriber) {
                foreach ($subscriber->getSubscribedEvents() as $eventClass => $method) {
                    if ($event instanceof $eventClass) {
                        yield $subscriber->$method(...);
                    }
                }
            }

            foreach ($this->listeners as $eventClass => $listeners) {
                if ($event instanceof $eventClass) {
                    foreach ($listeners as $listener) {
                        yield $listener(...);
                    }
                }
            }
        } else {
            throw new InvalidArgumentException('Event must be an instance of '.Event::class);
        }
    }
}
