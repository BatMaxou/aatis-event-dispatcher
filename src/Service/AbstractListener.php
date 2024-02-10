<?php

namespace Aatis\EventDispatcher\Service;

use Aatis\EventDispatcher\Interface\ListenerInterface;

abstract class AbstractListener implements ListenerInterface
{
    public function getListenedEvents(): iterable
    {
        return [];
    }
}
