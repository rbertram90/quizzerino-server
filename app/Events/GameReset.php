<?php

namespace rbwebdesigns\quizzerino\Events;

use Ratchet\ConnectionInterface;
use rbwebdesigns\quizzerino\Game;

class GameReset implements EventInterface
{
    public function run(ConnectionInterface $from, array $options)
    {
        echo 'Called to the __invoke method';
    }
}