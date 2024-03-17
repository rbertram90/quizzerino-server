<?php
namespace rbwebdesigns\quizzerino;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use React\EventLoop\TimerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Messenger implements MessageComponentInterface
{
    /** @var \SplObjectStorage Collection of currently connected clients (\Ratchet\ConnectionInterface) */
    protected $clients;

    protected ContainerBuilder $serviceContainer;

    /** @var array  Event Handlers */
    protected static array $events = [];

    /**
     * Messenger constructor.
     */
    public function __construct(protected PlayerManager $playerManager) {
        $this->clients = new \SplObjectStorage;

        self::$events = [
            'player_connected' => 'events.playerconnected',
            'start_game'       => 'events.gamestarted',
            'answer_submit'    => 'events.answersubmitted',
            'round_expired'    => 'events.roundexpired',
            'reset_game'       => 'events.gamereset',
        ];
    }

    /**
     * Give access to the symfony service container.
     */
    public function setContainer(ContainerBuilder $container)
    {
        $this->serviceContainer = $container;
    }

    /**
     * Connection opened callback
     * 
     * @param \Ratchet\ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    /**
     * Message recieved callback
     * 
     * @param \Ratchet\ConnectionInterface $from
     * @param string $msg
     *   json string containing data from client
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);

        if (!isset($data['action'])) {
            return;
        }

        echo sprintf('Message incoming (%d): %s'. PHP_EOL, $from->resourceId, $data['action']);

        $event = $this->serviceContainer->get(self::$events[$data['action']]);
        $event->run($from, $data);
    }

    /**
     * Connection closed / player has disconnected
     * 
     * @param \Ratchet\ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn)
    {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";

        $disconnectedPlayer = $this->playerManager->markPlayerAsInactive($conn->resourceId);
    }

    /**
     * An error has occured with a connected client
     * 
     * @param \Ratchet\ConnectionInterface $conn
     * @param \Exception $e
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Get all the data on the connected clients
     * 
     * @return \SplObjectStorage
     */
    public function getConnectedClients()
    {
        return $this->clients;
    }

    /**
     * Send a message to a single client
     */
    public function sendMessage(ConnectionInterface $client, array $data)
    {
        Logger::debug("Sending message to ({$client->resourceId}): {$data['type']}");

        $client->send(json_encode($data));
    }

    /**
     * Send a message to all connected clients
     * 
     * @param array $data
     */
    public function sendToAll($data)
    {
        $clients = $this->playerManager->getConnectedClients();

        foreach ($clients as $client) {
            $this->sendMessage($client, $data);
        }
    }

    /**
     * Send message to the game host
     */
    public function sendToHost($data)
    {
        $host = $this->playerManager->getHostPlayer();

        $this->sendMessage($host->getConnection(), $data);
    }

    /**
     * Send a delayed message (concept) - not sure if this can work - using server->loop?
     * 
     * @todo Look at again some time.
     *
     * @param ConnectionInterface $client
     * @param mixed[] $data
     * @param float $delay Delay in seconds
     *
     * @return TimerInterface
     */
    /*
    public function sendDelayed($client, $data, $delay) {
        // No reference to game!
        $server = $this->game->getServer();

        print "Queuing message" . PHP_EOL;

        return $server->loop->addTimer($delay, function() use ($client, $data) {
            print "Sending delayed message to ({$client->resourceId}): {$data['type']}".PHP_EOL;
            $msg = json_encode($data);
            $client->send($msg);
        });
    }
    */
}
