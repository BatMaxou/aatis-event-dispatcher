# Aatis ED

## Advertisement

This package is a part of `Aatis` and can't be used without the following packages :
- `aatis/dependency-injection` (https://github.com/BatMaxou/aatis-dependency-injection)
- `aatis/tag` (https://github.com/BatMaxou/aatis-tag)

## Installation

```bash
composer require aatis/event-dispatcher
```

## Usage

### Requirements

Add the `EventDispatcher` service to the `Container`:

```yaml
# In config/services.yaml file :

include_services:
    - 'Aatis\EventDispatcher\Service\EventDispatcher'
```

### Event

This package provides an abstract `Event` class that can be extended to create custom events like the following :

```php
use Aatis\EventDispatcher\Event\Event;

class ExampleEvent extends Event
{
}
```

### StoppableEvent

This package provides also an abstract `StoppableEvent` class that can be extended to create custom stoppable events like the following :

```php
use Aatis\EventDispatcher\Event\StoppableEvent;

class ExampleStoppableEvent extends StoppableEvent
{
}
```

This class implements a custom `StoppableEventInterface` that extends the `Psr\EventDispatcher\StoppableEventInterface`.

So, with this class, you can access 2 specific methods :
- `isPropagationStopped` that returns a boolean to know if the event propagation is stopped
- `stopPropagation` that set the propagation to `false`, it must be called into a listener or a subscriber method

### Priority

The `EventDispatcher` service can dispatch events with a priority amount that is an integer. The higher the priority, the earlier the event will be dispatched.

If two listeners have the same priority for the same event, the order of execution is for this two listeners is random.

By default, the priority is set to `0`.

### EventListener

A listener is a class that can be called when an event is dispatched. It must contains a `__invoke` method with only one parameter that must be the event listened.

Example of a listener targeting the `ExampleEvent` :

```php
class ExampleListener
{
    public function __invoke(ExampleEvent $event): void
    {
        // Do something
    }
}
```

You can specify the priority of a listener by attaching a `EventListener` attribute to the class with a `priority` parameter :

```php
#[EventListener(priority: 2)]
class ExampleListener
{
    public function __invoke(ExampleEvent $event): void
    {
        // Do something
    }
}
```

Finally, you must inform the container that this class is a listener by adding the `event-listener` tag to the service :

```yaml
# In config/services.yaml file :

services:
    App\Listener\ExampleListener:
        tags:
            - 'event-listener'
```

### EventSubscriber

A subscriber is a class that contains multiple listener methods and subscibe to several events.

It must provide a `getSubscribedEvents` method that returns the array of the events to which the service subscribes and their associated listener method.

Example of a subscriber :

```php
class TestSubscriber implements EventSubscriberInterface
{
    public function onExample(Event $event): void
    {
        // Do something
    }

    public function onExampleBis(Event $event): void
    {
        // Do something
    }

    public function getSubscribedEvents(): iterable
    {
        return [
            ExampleEvent::class => 'onExample',
            ExampleBisEvent::class => 'onExampleBis',
        ];
    }
}
```

You can also specify multiple listeners for the same event by passing an array instead :

```php
public function getSubscribedEvents(): iterable
{
    return [
        ExampleEvent::class => [
            'onExample',
            'onExampleBis'
        ],
        ExampleBisEvent::class => 'onExampleBis',
    ];
}
```

For the priority, you can also pass an array with the listener method and the priority as an integer :

```php
public function getSubscribedEvents(): iterable
{
    return [
        ExampleEvent::class => [
            ['onExample', 2],
            'onExampleBis'
        ],
        ExampleBisEvent::class => ['onExampleBis', 2],
    ];
}
```
