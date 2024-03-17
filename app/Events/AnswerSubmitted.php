<?php

namespace rbwebdesigns\quizzerino\Events;

use Ratchet\ConnectionInterface;
use rbwebdesigns\quizzerino\Enum\GameStatus;
use rbwebdesigns\quizzerino\Enum\PlayerStatus;
use rbwebdesigns\quizzerino\Game;
use rbwebdesigns\quizzerino\Messenger;
use rbwebdesigns\quizzerino\PlayerManager;

class AnswerSubmitted implements EventInterface
{
    public function __construct(
        protected Game $game,
        protected PlayerManager $playerManager,
        protected Messenger $messenger
    ) {}

    public function run(ConnectionInterface $from, array $data)
    {
        if ($this->game->status() !== GameStatus::GAME_STATUS_PLAYERS_CHOOSING) {
            return;
        }

        $player = $this->playerManager->getPlayerByResourceId($from->resourceId);
        $player->status = PlayerStatus::STATUS_ANSWER_CHOSEN;
        $answer = $data['answer'];
        $correctAnswer = $this->game->currentQuestion()['correct_option_index'];

        if ($answer == $correctAnswer) {
            $player->score++;
        }

        // send message to all clients that user has submitted
        $this->messenger->sendToAll([
            'type' => 'player_submitted',
            'playerName' => $player->username,
            'players' => $this->playerManager->getActivePlayers(),
        ]);

        // Check if all players have submitted cards
        if ($this->game->allPlayersDone()) {
            $this->game->nextRound();
        }
    }
}