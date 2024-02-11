<?php

namespace Aatis\EventDispatcher\Interface;

interface EventSubscriberInterface
{
    /**
     * @return array<class-string, callable|array{
     *  0: callable,
     *  1?: int
     * }|array<callable|array{
     *  0: callable,
     *  1?: int
     * }>>
     */
    public function getSubscribedEvents(): iterable;
}
