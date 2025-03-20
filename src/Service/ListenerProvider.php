<?php

namespace Aatis\EventDispatcher\Service;

use Aatis\DependencyInjection\Service\ServiceTagBuilder;
use Aatis\EventDispatcher\Attribute\EventListener;
use Aatis\EventDispatcher\Event\Event;
use Aatis\EventDispatcher\Exception\ListenerProvider\InvalidArgumentException;
use Aatis\EventDispatcher\Exception\ListenerProvider\InvalidListenerArgumentException;
use Aatis\EventDispatcher\Interface\EventSubscriberInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

class ListenerProvider implements ListenerProviderInterface
{
    /** @var EventSubscriberInterface[] */
    private array $subscribers = [];

    /**
     * @var array<class-string, array<array{
     *  listener: object&callable,
     *  priority: int
     * }>>
     */
    private array $listeners = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ServiceTagBuilder $serviceTagBuilder,
    ) {
        $this->addSubscribers();
        $this->addListeners();
    }

    private function addSubscribers(): void
    {
        /** @var EventSubscriberInterface[] $subscribers */
        $subscribers = $this->container->get($this->serviceTagBuilder->buildFromInterface(EventSubscriberInterface::class));
        foreach ($subscribers as $subscriber) {
            $this->addSubscriber($subscriber);
        }
    }

    private function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->subscribers[] = $subscriber;
    }

    private function addListeners(): void
    {
        /** @var (object&callable)[] $listeners */
        $listeners = $this->container->get($this->serviceTagBuilder->buildFromName('event-listener'));
        foreach ($listeners as $listener) {
            $this->addListener($listener);
        }
    }

    /**
     * @param object&callable $listener
     */
    private function addListener(object $listener): void
    {
        $reflexion = new \ReflectionClass($listener);

        $priority = 0;
        $attributes = $reflexion->getAttributes(EventListener::class);

        foreach ($attributes as $attribute) {
            $arguments = $attribute->getArguments();

            if (isset($arguments['priority'])) {
                $priority = $arguments['priority'];
            }
        }

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
            $this->listeners[$eventType->getName()][] = [
                'listener' => $listener,
                'priority' => $priority,
            ];
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

            foreach ($this->listeners as $eventClass => $listenersInfos) {
                if ($event instanceof $eventClass) {
                    foreach ($listenersInfos as $listenerInfos) {
                        $returnedListeners = array_merge([['method' => $listenerInfos['listener'](...), 'priority' => $listenerInfos['priority']]], $returnedListeners);
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
