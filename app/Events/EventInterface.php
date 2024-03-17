<?php

namespace rbwebdesigns\quizzerino\Events;

use Ratchet\ConnectionInterface;

interface EventInterface
{
    /**
     * Response to the event.
     */
    public function run(ConnectionInterface $from, array $options);
}