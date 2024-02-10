<?php

namespace Aatis\EventDispatcher\Interface;

interface ListenerInterface
{
    public function getListenedEvents(): iterable;
}
