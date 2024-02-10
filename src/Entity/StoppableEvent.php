<?php

namespace Aatis\EventDispatcher\Entity;

use Aatis\EventDispatcher\Interface\StoppableEventInterface;

abstract class StoppableEvent extends Event implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): static
    {
        $this->propagationStopped = true;

        return $this;
    }
}
