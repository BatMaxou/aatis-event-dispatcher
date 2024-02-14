<?php

namespace Aatis\EventDispatcher\Interface;

interface EventSubscriberInterface
{
    /**
     * @return array<class-string, string|array{
     *  0: string,
     *  1?: int
     * }|array<string|array{
     *  0: string,
     *  1?: int
     * }>>
     */
    public function getSubscribedEvents(): iterable;
}
