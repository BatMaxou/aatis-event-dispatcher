<?php

namespace Aatis\EventDispatcher\Interface;

use Psr\EventDispatcher\StoppableEventInterface as PsrStoppableEventInterface;

interface StoppableEventInterface extends PsrStoppableEventInterface
{
    public function stopPropagation(): static;
}
