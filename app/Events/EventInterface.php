<?php

namespace rbwebdesigns\quizzerino\Events;

use Ratchet\ConnectionInterface;

interface EventInterface
{
    /**
     * Response to the event.
     * 
     * @param ConnectionInterface $from
     * @param array $options Settings from the quiz definition json (json decoded using associative flag)
     */
    public function run(ConnectionInterface $from, array $options);
}