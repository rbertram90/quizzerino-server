<?php

namespace rbwebdesigns\quizzerino\Events;

use Ratchet\ConnectionInterface;
use rbwebdesigns\quizzerino\Enum\GameStatus;
use rbwebdesigns\quizzerino\Game;

/**
 * Handles game_start event.
 * 
 * Try and start the game - will fail if not enough players are connected
 * 
 * @todo We haven't actually verified that the player that sent this
 * message was the game host!
 */
class GameStarted implements EventInterface
{
    public function __construct(protected Game $game) {}

    public function run(ConnectionInterface $from, array $options)
    {
        if ($this->game->status() !== GameStatus::GAME_STATUS_AWAITING_START) {
            return;
        }

        if (($timeLimit = intval($options['timeLimit'])) > 0) {
            // Options: 0,1,2,3 - for Infinite,10,20,30 seconds respectively.
            $timeLimit *= 10;
        }

        $this->game->quizId($options['quiz'])
            ->questionsPerRound($options['numberOfQuestions'])
            ->timeLimit($timeLimit)
            ->status(GameStatus::GAME_STATUS_PLAYERS_CHOOSING)->nextRound();
    }
}
