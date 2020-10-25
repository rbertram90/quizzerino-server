<?php
namespace rbwebdesigns\quizzerino;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * class Game
 * 
 * This class is passed as a parameter into the WsServer when the server
 * is started. It's job is to take the incoming messages and maintain game
 * state.
 * 
 * Currently nothing is persisted in files or a database, once the server
 * has been stopped all data is lost.
 */
class Game implements MessageComponentInterface
{
    /** @var \SplObjectStorage Collection of currently connected clients (\Ratchet\ConnectionInterface) */
    protected $clients;

    /** @var \rbwebdesigns\quizzerino\PlayerManager */
    protected $playerManager;

    /** @var string */
    protected $status;

    /** @var \rbwebdesigns\quizzerino\Messenger */
    protected $messenger;

    // Which quiz are we running - this is set
    // in game config form
    protected $quizId = null;

    // This will contain the list of quizzes that
    // are available to do on the server
    protected $quizList = null;

    // This will contain the class needed to get
    // questions - dynamically created when ready
    protected $quizController = null;

    // Reference to the game server
    protected $server = null;

    /** @var \React\EventLoop\TimerInterface */
    protected $roundEndTimer = null;

    /** @var int Current question (sequential) */
    public $currentQuestionNumber = 0;

    /** @var array Current question data */
    protected $currentQuestion = null;

    /** @var int  Maximum time for players to answer a question */
    public $timeLimit = 0;

    /** @var int  How many questions are asked in a round */
    public $questionsPerRound = 5;

    // Player statuses
    public const STATUS_IN_PLAY = 'Thinking...';
    public const STATUS_ANSWER_CHOSEN = 'Answer submitted';
    public const STATUS_CONNECTED = 'Connected';
    public const STATUS_DISCONNECTED = 'Disconnected';

    // Game states
    public const GAME_STATUS_AWAITING_START = 0;
    public const GAME_STATUS_PLAYERS_CHOOSING = 1;
    public const GAME_STATUS_ROUND_WON = 3;
    public const GAME_STATUS_GAME_WON = 4;

    // Exception codes
    public const E_DUPLICATE_USERNAME = 1;
    
    /**
     * Game constructor
     */
    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->playerManager = new PlayerManager($this);
        // $this->questionManager = new QuestionManager;
        $this->messenger = new Messenger($this);
        $this->status = self::GAME_STATUS_AWAITING_START;
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
        if (!isset($data['action'])) return;

        echo sprintf('Message incoming (%d): %s'. PHP_EOL, $from->resourceId, $data['action']);

        switch ($data['action']) {
            case 'player_connected':
                $this->addPlayer($from, $data);
                break;

            case 'start_game':
                // Check game state
                if ($this->status !== self::GAME_STATUS_AWAITING_START) break;

                $this->start($data);
                break;

            case 'answer_submit':
                // Check game state
                if ($this->status !== self::GAME_STATUS_PLAYERS_CHOOSING) break;

                $this->answerSubmitted($from, $data);
                break;

            case 'round_expired':
                // Timer has run out - trigger judging
                if ($this->status !== self::GAME_STATUS_PLAYERS_CHOOSING) break;
                
                $this->nextRound();
                break;

            case 'reset_game':
                $this->reset();
                break;
        }
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
     * 
     * @param ConnectionInterface $from
     * @param array $data
     */
    protected function addPlayer($from, $data)
    {
        // Connect player to game
        try {
            $player = $this->playerManager->connectPlayer($data, $from);
        }
        catch (\Exception $e) {
            switch ($e->getCode()) {
                case self::E_DUPLICATE_USERNAME:
                    $from->send('{ "type": "duplicate_username" }');
                    $from->close();
                    return;
            }
        }

        // Send a message to the player with the game state
        $this->messenger->sendMessage($from, [
            'type' => 'connected_game_status',
            'host' => $this->playerManager->getHostPlayer(),
            'game_status' => $this->status,
            'quiz_options' => $this->getQuizList(),
        ]);

        if ($this->status == self::GAME_STATUS_PLAYERS_CHOOSING) {
            $this->messenger->sendMessage($from, [
                'type' => 'round_start',
                'question' => $this->getNextQuestion(),
                'questionNumber' => $this->currentQuestionNumber,
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

    /**
     * Try and start the game - will fail if not enough players are connected
     * 
     * @todo We haven't actually verified that the player that sent this
     * message was the game host!
     */
    protected function start($options)
    {
        $this->quizId = $options['quiz'];
        $this->questionsPerRound = $options['numberOfQuestions'];
        $this->timeLimit = intval($options['timeLimit']);
        if ($this->timeLimit === 0) $this->timeLimit = 999999; // almost infinite!
        else $this->timeLimit *= 10;
        $this->status = self::GAME_STATUS_PLAYERS_CHOOSING;
        $this->nextRound();
    }

    /**
     * Reset the game state
     */
    protected function reset()
    {

    }

    /**
     * Progress to the next round
     */
    protected function nextRound()
    {
        // Note - currentQuestionNumber will be 0 indexed at this point
        if ($this->currentQuestionNumber > $this->questionsPerRound - 1) {
            // Finish quiz here
            return $this->endQuiz();
        }

        $this->playerManager->changeAllPlayersStatus(self::STATUS_IN_PLAY);
        $roundEnd = time() + $this->timeLimit;

        // Set-up timer for round end
        if ($this->timeLimit) {
            $this->roundEndTimer = $this->server->loop->addTimer($this->timeLimit, function() {
                $this->endRound();
            });
        }

        $messageData = [
            'type' => 'round_start',
            'question' => $this->getNextQuestion(),
            'questionNumber' => $this->currentQuestionNumber,
            'roundTime' => $this->timeLimit,
            'roundEndTimeUTC' => $roundEnd,
            'players' => $this->playerManager->getActivePlayers()
        ];

        $this->messenger->sendToAll($messageData);
    }

    /**
     * End the round before everyone has submitted
     */
    protected function endRound() {
        // Maybe do something here?
        $this->nextRound();
    }

    /**
     * All rounds completed - show results
     */
    protected function endQuiz() {
        $messageData = [
            'type' => 'game_end',
            'players' => $this->playerManager->getActivePlayers()
        ];

        $this->messenger->sendToAll($messageData);
    }

    /**
     * Player has submitted answer
     * 
     * @param ConnectionInterface $from
     * @param array $data
     */
    protected function answerSubmitted(ConnectionInterface $from, array $data)
    {
        $player = $this->playerManager->getPlayerByResourceId($from->resourceId);
        $player->status = self::STATUS_ANSWER_CHOSEN;
        $answer = $data['answer'];
        $correctAnswer = $this->currentQuestion['correct_option_index'];

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
        if ($this->allPlayersDone()) {
            if (!is_null($this->roundEndTimer)) {
                $this->server->loop->cancelTimer($this->roundEndTimer);
                $this->roundEndTimer = null;
            }
            $this->nextRound();
        }
    }

    /**
     * Checks if all players have submitted their answer for this round
     * 
     * This could be an option - either fixed time or unlimited: until
     * everyone submits an answer
     * 
     * @return bool have all players submitted an answer
     */
    protected function allPlayersDone()
    {
        foreach ($this->playerManager->getActivePlayers() as $player) {
            if ($player->status !== self::STATUS_ANSWER_CHOSEN) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the next question in the quiz - this in turn
     * calls the quiz controller
     */
    protected function getNextQuestion()
    {
        $this->currentQuestionNumber++;
        // This is the core logic we need to get the next question....
        $controller = $this->getQuizController();
        $this->currentQuestion = $controller->getQuestion();
        return $this->currentQuestion;
    }

    /**
     * Get the controller class instance from the quizId
     * set in config
     */
    protected function getQuizController()
    {
        if (!is_null($this->quizController)) return $this->quizController;
        if (is_null($this->quizList)) Logger::error("Quiz list empty!");

        foreach ($this->quizList as $quiz) {
            if ($quiz->id == $this->quizId) {
                $controllerName = $quiz->controller;
                $data = false;

                if (property_exists($quiz, 'settings')) {
                    $data = $quiz->settings;
                }

                $this->quizController = new $controllerName($data);

                if ($this->quizController && $this->quizController instanceof SourceInterface) {
                    return $this->quizController;
                }
                else {
                    Logger::error("Quiz controller not found/correct - must implement \\rbwebdesigns\\quizzerino\\SourceInterface");
                }
            }
        }
    }

    /**
     * Gets the list of quizzes installed on this server
     * 
     * @return \StdClass[]
     */
    protected function getQuizList() : array
    {
        // Check if we've already run this process since server start
        if (is_null($this->quizList)) {

            // Scan the quizzes directory
            $quizParentFolder = ROOT_DIR .'/'. QUIZ_FOLDER;
            $folders = scandir($quizParentFolder);

            foreach ($folders as $folder) {
                if (in_array($folder, ['.', '..'])) continue;

                $quizFolder = $quizParentFolder .'/'. $folder;

                if (is_dir($quizFolder)) {
                    if (file_exists($quizFolder .'/quizzes.json')) {
                        $quizzesJson = file_get_contents($quizFolder .'/quizzes.json');
                        $this->quizList = json_decode($quizzesJson);
                    }
                }
            }

            // Check if the list is still nothing
            if (is_null($this->quizList)) {
                // Default to empty so we don't needlessly keep checking
                $this->quizList = [];
            }
        }

        return $this->quizList;
    }

    /**
     * Setter method for server - has not been created when class is initialised
     *
     * @param $server
     */
    public function setServer($server) {
        $this->server = $server;
    }

    /**
     * Getter method for server
     *
     * @return \Ratchet\Server\IoServer
     */
    public function getServer() {
        return $this->server;
    }

}
