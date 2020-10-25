<?php
namespace rbwebdesigns\quizzerino;

use Ratchet\ConnectionInterface;
use React\EventLoop\TimerInterface;

class Messenger {

    /** @var Game */
    protected $game;

    /**
     * Messenger constructor.
     * @param Game $game
     */
    public function __construct($game) {
        $this->game = $game;
    }

    /**
     * Send a message to a single client
     * 
     * @param ConnectionInterface $client
     * @param array $data
     */
    public function sendMessage($client, $data)
    {
        print "Sending message to ({$client->resourceId}): {$data['type']}".PHP_EOL;
        $msg = json_encode($data);
        $client->send($msg);
    }

    /**
     * Send a message to all connected clients
     * 
     * @param array $data
     */
    public function sendToAll($data)
    {
        $clients = $this->game->getConnectedClients();

        foreach ($clients as $client) {
            $this->sendMessage($client, $data);
        }
    }

    /**
     * Send message to the game host
     */
    public function sendToHost($data)
    {
        $clients = $this->game->getConnectedClients();

        // Send to host - assuming host is always client 0
        $clients->rewind();
        $this->sendMessage($clients->current(), $data);
    }

    /**
     * Send a delayed message (concept) - not sure if this can work - using server->loop?
     *
     * @param ConnectionInterface $client
     * @param mixed[] $data
     * @param float $delay Delay in seconds
     *
     * @return TimerInterface
     */
    public function sendDelayed($client, $data, $delay) : TimerInterface {
        $server = $this->game->getServer();

        print "Queuing message" . PHP_EOL;

        return $server->loop->addTimer($delay, function() use ($client, $data) {
            print "Sending delayed message to ({$client->resourceId}): {$data['type']}".PHP_EOL;
            $msg = json_encode($data);
            $client->send($msg);
        });
    }

}
