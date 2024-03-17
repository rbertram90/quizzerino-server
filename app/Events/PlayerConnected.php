<?php

namespace rbwebdesigns\quizzerino\Events;

use Ratchet\ConnectionInterface;
use rbwebdesigns\quizzerino\Enum\GameStatus;
use rbwebdesigns\quizzerino\Game;
use rbwebdesigns\quizzerino\Logger;
use rbwebdesigns\quizzerino\Messenger;
use rbwebdesigns\quizzerino\PlayerManager;

/**
 * Event Player Connected.
 * 
 * Add a player into game, this is triggered by a message from
 * the client after they have connected into the server. I.e.
 * not in the same process as when they are first connected,
 * this is because we are unable to pass the username in the
 * initial connect call.
 * 
 * Note the player connecting may already be known by username
 * as they may have had an internet outage so this function
 * looks to see if a username match exists and is not in use
 * by a current player.
 * 
 * The game allows a player to join midway through a round and
 * still be able to submit cards.
 * 
 * @todo More thought has needs to be given to what should
 * happen when the host disconnects.
 */
class PlayerConnected implements EventInterface
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
        // Connect player to game
        try {
            $player = $this->playerManager->connectPlayer($data, $from);
        }
        catch (\Exception $e) {
            switch ($e->getCode()) {
                case Game::E_DUPLICATE_USERNAME:
                    $from->send('{ "type": "duplicate_username" }');
                    $from->close();
                    return;
            }
        }

        // Send a message to the player with the game state
        $this->messenger->sendMessage($from, [
            'type' => 'connected_game_status',
            'host' => $this->playerManager->getHostPlayer(),
            'game_status' => $this->game->status(),
            'quiz_options' => $this->game->getQuizList(),
        ]);

        if ($this->game->status() == GameStatus::GAME_STATUS_PLAYERS_CHOOSING) {
            $this->messenger->sendMessage($from, [
                'type' => 'round_start',
                'question' => $this->game->getNextQuestion(),
                'questionNumber' => $this->game->currentQuestionNumber(),
                // 'roundTime' => $this->roundTime,
                'players' => $this->playerManager->getActivePlayers()
            ]);
        }

        // If they're reconnecting, then send them the data
        // if (count($player->cards) > 0) {
        //     $this->messenger->sendMessage($player->getConnection(), []);
        // }

        // Notify all players
        $this->messenger->sendToAll([
            'type' => 'player_connected',
            'playerName' => $player->username,
            'host' => $player->isGameHost,
            'players' => $this->playerManager->getActivePlayers(),
        ]);
    }
}