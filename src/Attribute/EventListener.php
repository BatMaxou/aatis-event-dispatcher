<?php

namespace Aatis\EventDispatcher\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class EventListener
{
    public function __construct(private readonly int $priority)
    {
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
