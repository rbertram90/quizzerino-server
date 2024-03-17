<?php

namespace rbwebdesigns\quizzerino\Events;

use Ratchet\ConnectionInterface;
use rbwebdesigns\quizzerino\Enum\GameStatus;
use rbwebdesigns\quizzerino\Game;

class RoundExpired implements EventInterface
{
    public function __construct(protected Game $game) {}

    public function run(ConnectionInterface $from, array $options)
    {
        if ($this->game->status() !== GameStatus::GAME_STATUS_PLAYERS_CHOOSING) {
            return;
        }

        $this->game->nextRound();
    }
}
