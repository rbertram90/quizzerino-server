<?php
namespace rbwebdesigns\quizzerino;

use Ratchet\ConnectionInterface;

class Player {

    public $username;
    public $ip;
    public $isGameHost = false;
    public $isActive; // has the player disconnected?
    public $status;
    public $score;
    public $icon;

    /** @var Game */
    protected $game;

    /** @var ConnectionInterface */
    protected $connection;

    /**
     * class Player constructor
     */
    public function __construct($connection, $game)
    {
        $this->connection = $connection;
        $this->isActive = true;
        $this->status = Game::STATUS_CONNECTED;
        $this->game = $game;
        $this->score = 0;
        $this->reset();
    }

    /**
     * Get the connection object
     * 
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Change the connection object
     * 
     * @param ConnectionInterface $connection
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Reset player data for new game
     */
    public function reset() {
        $this->score = 0;
    }

    /**
     * Set the player as inactive
     */
    public function setInactive() {
        $this->status = Game::STATUS_DISCONNECTED;
        $this->isActive = false;
    }

}
