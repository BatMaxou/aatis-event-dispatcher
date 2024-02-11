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
        $returnedListeners = [];

        if ($event instanceof Event) {
            foreach ($this->subscribers as $subscriber) {
                foreach ($subscriber->getSubscribedEvents() as $eventClass => $infos) {
                    if ($event instanceof $eventClass) {
                        $returnedListeners = array_merge($this->getListenerInfosFromSubscriberInfos($subscriber, $infos), $returnedListeners);
                    }
                }
            }

            foreach ($this->listeners as $eventClass => $listeners) {
                if ($event instanceof $eventClass) {
                    foreach ($listeners as $listener) {
                        $returnedListeners = array_merge([['method' => $listener(...), 'priority' => 0]], $returnedListeners);
                    }
                }
            }

            $sortedListeners = $this->sortListeners($returnedListeners);

            foreach ($sortedListeners as $listener) {
                yield $listener['method'];
            }
        } else {
            throw new InvalidArgumentException('Event must be an instance of '.Event::class);
        }
    }

    /**
     * @return array<array{
     *  method: callable,
     *  priority: int
     * }>
     */
    private function getListenerInfosFromSubscriberInfos(EventSubscriberInterface $subscriber, mixed $infos): array
    {
        $listenersInfos = [];

        if (!is_array($infos)) {
            $listenersInfos = [
                [
                    'method' => $subscriber->$infos(...),
                    'priority' => 0,
                ],
            ];
        } elseif ($infos[1] && is_int($infos[1])) {
            $listenersInfos = [
                [
                    'method' => $subscriber->{$infos[0]}(...),
                    'priority' => $infos[1],
                ],
            ];
        } else {
            foreach ($infos as $info) {
                $listenersInfos = array_merge($this->getListenerInfosFromSubscriberInfos($subscriber, $info), $listenersInfos);
            }
        }

        return $listenersInfos;
    }

    /**
     * @param array<array{
     *  method: callable,
     *  priority: int
     * }> $listeners
     *
     * @return array<array{
     *  method: callable,
     *  priority: int
     * }>
     */
    private function sortListeners(array $listeners): array
    {
        usort($listeners, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        return $listeners;
    }
}
