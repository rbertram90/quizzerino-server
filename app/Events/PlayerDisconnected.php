<?php

namespace rbwebdesigns\quizzerino\Events;

use Ratchet\ConnectionInterface;
use rbwebdesigns\quizzerino\Game;
use rbwebdesigns\quizzerino\Messenger;
use rbwebdesigns\quizzerino\PlayerManager;

/**
 * Event Player Disonnected.
 * 
 * @todo More thought has needs to be given to what should
 * happen when the host disconnects.
 */
class PlayerDisconnected implements EventInterface
{
    public function __construct(
        protected Game $game,
        protected PlayerManager $playerManager,
        protected Messenger $messenger
    ) {}

    /**
     * {@inheritdoc}
     */
    public function run(ConnectionInterface $from, array $data)
    {
        // Disconnect player from game
        $player = $this->playerManager->disconnectPlayer($from->resourceId);

        if (count($player->roundScores) === $this->game->currentQuestionNumber()) {
            // Remove the last element so if they reconnect they can't get the
            // points for the same question.
            $lastScore = array_pop($player->roundScores);

            $player->score -= $lastScore;
        }

        // Notify all players
        $this->messenger->sendToAll([
            'type' => 'player_disconnected',
            'playerName' => $player->username,
            'host' => $player->isGameHost,
            'players' => $this->playerManager->getActivePlayers(),
        ]);
    }
}