<?php
namespace rbwebdesigns\quizzerino;

class PlayerManager {

    /** @var Player[] */
    protected $players;

    /** @var Game */
    protected $game;

    /** @var Player */
    protected $hostPlayer;

    public function __construct($game)
    {
        $this->players = [];
        $this->hostPlayer = null;
        $this->game = $game;
    }

    /**
     * Create a new player or re-connect one that has previously disconnected
     * 
     * @param array $data
     *  Message passed to the server from client
     * @param ConnectionInterface $conn
     *  Connection object from client
     * 
     * @return Player|boolean
     *   If username exists and is connected to game then returns false.
     *   Otherwise returns Player object
     */
    public function connectPlayer($data, $conn)
    {
        $player = $this->getPlayerByUsername($data['username']);
        if (!is_null($player)) {
            if ($player->isActive) {
                throw new \Exception('Duplicate username', Game::E_DUPLICATE_USERNAME);
            }
            $player->setConnection($conn);
            $player->isActive = true;
            $player->status = $this->game::STATUS_CONNECTED;
            return $player;
        }

        $player = new Player($conn, $this->game);
        $player->username = $data['username'];
        $player->icon = $data['icon'];
        $player->ip = $conn->remoteAddress;

        // this needs improving - maybe specified username in config?
        if (count($this->players) == 0) {
            $this->hostPlayer = $player;
            $player->isGameHost = true;
        }

        $this->players[] = $player;
        return $player;
    }

    /**
     * Get a connected player by their username
     * 
     * @return \rbwebdesigns\quizzerino\Player|null
     */
    public function getPlayerByUsername($username)
    {
        foreach ($this->players as $player) {
            if ($player->username == $username) {
                return $player;
            }
        }
        return null;
    }

    /**
     * Get a connected player by their resource (socket) Id
     * 
     * @return \rbwebdesigns\quizzerino\Player|null
     */
    public function getPlayerByResourceId($resourceId)
    {
        foreach ($this->players as $player) {
            if ($player->getConnection()->resourceId == $resourceId) {
                return $player;
            }
        }
        return null;
    }

    /**
     * Get all players in the game (active and not)
     * 
     * @return \rbwebdesigns\quizzerino\Player[]
     */
    public function getAllPlayers()
    {
        return $this->players;
    }

    /**
     * Get all players currently connected
     */
    public function getActivePlayers()
    {
        $active = [];
        foreach ($this->players as $player) {
            if ($player->isActive) {
                $active[] = $player;
            }
        }
        return $active;
    }

    /**
     * When a player disconnects we don't want to completely
     * remove them from the game as it could just be a temporary
     * network issue. There record is kept in game but marked as
     * inactive.
     * 
     * @param int $resourceId
     */
    public function markPlayerAsInactive($resourceId)
    {
        foreach ($this->players as $player) {
            if ($player->getConnection()->resourceId == $resourceId) {
                $player->setInactive();
                return $player;
            }
        }
    }

    /**
     * Reset player data
     */
    public function resetPlayers() {
        $activePlayers = $this->getActivePlayers();

        foreach ($activePlayers as $player) {
            $player->reset();
        }
        $this->currentPlayer = $activePlayers[0];
    }

    /**
     * Check if a player has acheieve enough points to win the game
     * 
     * @return boolean
     */
    public function playerHasWon() {
        foreach ($this->players as $player) {
            if ($player->score >= $this->game->winningScore) {
                return true;
            }
        }
        return false;
    }

    /**
     * Update the status of all players
     */
    public function changeAllPlayersStatus($status) {
        foreach ($this->players as &$player) {
            $player->status = $status;
        }
    }

    /**
     * Get the host player
     */
    public function getHostPlayer() {
        return $this->hostPlayer;
    }

}