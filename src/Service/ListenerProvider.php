<?php

namespace Aatis\EventDispatcher\Service;

use Aatis\DependencyInjection\Component\Service;
use Aatis\DependencyInjection\Enum\ServiceTagOption;
use Aatis\DependencyInjection\Interface\ServiceSubscriberInterface;
use Aatis\DependencyInjection\Service\ServiceInstanciator;
use Aatis\DependencyInjection\Service\ServiceTagBuilder;
use Aatis\DependencyInjection\Trait\ServiceSubscriberTrait;
use Aatis\EventDispatcher\Attribute\EventListener;
use Aatis\EventDispatcher\Event\Event;
use Aatis\EventDispatcher\Exception\ListenerProvider\InvalidArgumentException;
use Aatis\EventDispatcher\Exception\ListenerProvider\InvalidListenerArgumentException;
use Aatis\EventDispatcher\Interface\EventSubscriberInterface;
use Aatis\Tag\Interface\TagBuilderInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

// phpstan-type is used here to fix trait mismatch on the Listener interface with @template
/**
 * @phpstan-type Listener array{method: callable(): mixed, priority: int}
 */
class ListenerProvider implements ListenerProviderInterface, ServiceSubscriberInterface
{
    /**
     * @use ServiceSubscriberTrait<Service<object>, Listener[], array{
     *  event: Event,
     * }>
     */
    use ServiceSubscriberTrait {
        __construct as initServiceSubscriber;
    }

    public function __construct(
        private readonly ServiceTagBuilder $serviceTagBuilder,
        private readonly ServiceInstanciator $serviceInstanciator,
        ContainerInterface $container,
    ) {
        $this->initServiceSubscriber($container);
    }

    public static function getSubscribedServices(TagBuilderInterface $tagBuilder): iterable
    {
        yield $tagBuilder->buildFromInterface(EventSubscriberInterface::class, [ServiceTagOption::SERVICE_TARGETED]);
        yield $tagBuilder->buildFromName('event-listener', [ServiceTagOption::SERVICE_TARGETED]);
    }

    /**
     * @param Service<object> $service
     * @param array{event: Event} $ctx
     */
    protected function pick(mixed $service, array $ctx): bool
    {
        if ($this->isSubscriber($service)) {
            /** @var Service<EventSubscriberInterface> $service */
            $class = $service->getClass();
            $subscribedEvents = array_keys($class::getSubscribedEvents());

            return in_array($ctx['event']::class, $subscribedEvents, true);
        }

        $reflexion = $service->getReflexion();
        $invokeMethod = $reflexion->getMethod('__invoke');
        $parameters = $invokeMethod->getParameters();
        if (1 !== count($parameters)) {
            throw new InvalidListenerArgumentException('Listener must have only one parameter');
        }

        /**
         * @var \ReflectionNamedType|null
         */
        $eventType = $parameters[0]->getType();
        if (!$eventType || !is_a($eventType->getName(), Event::class, true)) {
            throw new InvalidListenerArgumentException('Listener must have only one parameter and it must be an instance of '.Event::class);
        }

        return $ctx['event']::class === $eventType->getName();
    }

    /**
     * @param Service<object> $service
     * @param array{event: Event} $ctx
     *
     * @return Listener[]
     */
    protected function transformOut(mixed $service, array $ctx): mixed
    {
        if ($this->isSubscriber($service)) {
            /** @var Service<EventSubscriberInterface> $service */
            $subscriber = $this->serviceInstanciator->instanciate($service);
            $output = [];

            foreach ($subscriber::getSubscribedEvents() as $eventClass => $infos) {
                if ($ctx['event'] instanceof $eventClass) {
                    /** @var Listener[] $output */
                    $output = array_merge($this->getListenerDataFromSubscriberInfos($subscriber, $infos), $output);
                }
            }

            return $output;
        } else {
            /** @var Service<object&callable> $service */
            return [$this->getListenerData($service)];
        }
    }

    /**
     * @return iterable<callable>
     */
    public function getListenersForEvent(object $event): iterable
    {
        if ($event instanceof Event) {
            $sortedListeners = $this->sortListeners(array_merge(...$this->provide(['event' => $event])));
            foreach ($sortedListeners as $listener) {
                yield $listener['method'];
            }
        } else {
            throw new InvalidArgumentException('Event must be an instance of '.Event::class);
        }
    }

    /**
     * @return Listener[]
     */
    private function getListenerDataFromSubscriberInfos(EventSubscriberInterface $subscriber, mixed $infos): array
    {
        $listenersInfos = [];

        if (!is_array($infos)) {
            $listenersInfos = [
                [
                    'method' => $subscriber->$infos(...),
                    'priority' => 0,
                ],
            ];
        } elseif (isset($infos[1]) && is_int($infos[1])) {
            $listenersInfos = [
                [
                    'method' => $subscriber->{$infos[0]}(...),
                    'priority' => $infos[1],
                ],
            ];
        } else {
            foreach ($infos as $info) {
                $listenersInfos = array_merge($this->getListenerDataFromSubscriberInfos($subscriber, $info), $listenersInfos);
            }
        }

        /** @var Listener[] $listenersInfos */
        return $listenersInfos;
    }

    /**
     * @param Service<object&callable> $service
     *
     * @return Listener
     */
    private function getListenerData(Service $service): array
    {
        $reflexion = $service->getReflexion();

        $priority = 0;
        $attributes = $reflexion->getAttributes(EventListener::class);

        foreach ($attributes as $attribute) {
            $arguments = $attribute->getArguments();

            if (isset($arguments['priority'])) {
                $priority = $arguments['priority'];
            }
        }

        /** @var Listener $listener */
        $listener = [
            'method' => $this->serviceInstanciator->instanciate($service)(...),
            'priority' => $priority,
        ];

        return $listener;
    }

    /**
     * @param Service<object> $service
     */
    private function isSubscriber(Service $service): bool
    {
        return $service->hasTag($this->serviceTagBuilder->buildFromInterface(EventSubscriberInterface::class));
    }

    /**
     * @param Listener[] $listeners
     *
     * @return Listener[]
     */
    private function sortListeners(array $listeners): array
    {
        usort($listeners, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return $listeners;
    }
}
