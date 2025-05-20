<?php

namespace Aatis\EventDispatcher\Interface;

use Aatis\EventDispatcher\Event\Event;

interface EventSubscriberInterface
{
    /**
     * @return array<class-string<Event>, string|array{
     *  0: string,
     *  1?: int
     * }|array<string|array{
     *  0: string,
     *  1?: int
     * }>>
     */
    public static function getSubscribedEvents(): array;
}
