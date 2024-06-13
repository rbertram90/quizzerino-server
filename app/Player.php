<?php
namespace rbwebdesigns\quizzerino;

use Ratchet\ConnectionInterface;
use rbwebdesigns\quizzerino\Enum\PlayerStatus;

class Player {

    public $username;
    public $ip;
    public $isGameHost = false;
    // has the player disconnected?
    public $isActive;
    public PlayerStatus $status;
    public int $score = 0;
    public array $roundScores = [];
    public $icon;

    protected ConnectionInterface $connection;

    /**
     * class Player constructor
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->isActive = true;
        $this->status = PlayerStatus::STATUS_CONNECTED;
        $this->reset();
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Change the connection object
     * 
     * @param ConnectionInterface $connection
     */
    public function setConnection(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Reset player data for new game
     */
    public function reset()
    {
        $this->score = 0;
        $this->roundScores = [];
    }

    /**
     * Set the player as inactive
     */
    public function setInactive()
    {
        $this->status = PlayerStatus::STATUS_DISCONNECTED;
        $this->isActive = false;
    }

}
