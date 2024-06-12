<?php
namespace rbwebdesigns\quizzerino;

use Ratchet\ConnectionInterface;
use rbwebdesigns\quizzerino\Enum\GameStatus;
use rbwebdesigns\quizzerino\Enum\PlayerStatus;
use React\EventLoop\TimerInterface;

/**
 * class Game
 * 
 * This class is passed as a parameter into the WsServer when the server
 * is started. It's job is to take the incoming messages and maintain game
 * state.
 * 
 * Currently nothing is persisted in files or a database, once the server
 * has been stopped all data is lost.
 * 
 * Dynamic getters/setters:
 * 
 * @method self|string quizId(?string $quizId)
 * @method self|int status(?int $status)
 * @method self|int questionsPerRound(?int $questionCount)
 * @method self|int timeLimit(?int $questionCount)
 * @method self|\Ratchet\Server\IoServer server(?\Ratchet\Server\IoServer $server)
 * @method int currentQuestionNumber()
 * @method array|null currentQuestion()
 */
class Game
{
    // This will contain the list of quizzes that
    // are available to do on the server
    protected ?array $quizList = null;

    // Which quiz are we running - this is set
    // in game config form
    protected ?string $quizId = null;

    // This will contain the class needed to get
    // questions - dynamically created when ready
    protected ?SourceInterface $quizController = null;

    // Reference to the game server
    protected $server = null;

    protected ?TimerInterface $roundEndTimer = null;

    protected GameStatus $status;
    protected int $currentQuestionNumber = 0;
    protected ?array $currentQuestion = null;
    protected int $timeLimit = 0;
    protected int $questionsPerRound = 5;

    // Exception codes
    public const E_DUPLICATE_USERNAME = 1;

    /**
     * Game constructor
     * 
     * @todo inject these services.
     */
    public function __construct(
        protected PlayerManager $playerManager,
        protected Messenger $messenger
    )
    {
        $this->status = GameStatus::GAME_STATUS_AWAITING_START;
    }

    /**
     * Progress to the next round
     */
    public function nextRound()
    {
        if (!is_null($this->roundEndTimer)) {
            $this->server->loop->cancelTimer($this->roundEndTimer);
            $this->roundEndTimer = null;
        }

        // Note - currentQuestionNumber will be 0 indexed at this point
        if ($this->currentQuestionNumber > $this->questionsPerRound - 1) {
            // Finish quiz here
            return $this->endQuiz();
        }

        $this->playerManager->changeAllPlayersStatus(PlayerStatus::STATUS_IN_PLAY);
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
    protected function endRound()
    {
        // Maybe do something here?
        $this->nextRound();
    }

    /**
     * All rounds completed - show results
     */
    protected function endQuiz()
    {
        $messageData = [
            'type' => 'game_end',
            'players' => $this->playerManager->getActivePlayers()
        ];

        $this->messenger->sendToAll($messageData);
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
            if ($player->status !== PlayerStatus::STATUS_ANSWER_CHOSEN) {
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
            $this->quizList = [];

            // Scan the quizzes directory
            $quizParentFolder = $_ENV['APP_ROOT'] .'/'. $_ENV['QUIZ_FOLDER'];
            $folders = scandir($quizParentFolder);

            foreach ($folders as $folder) {
                if (in_array($folder, ['.', '..'])) continue;

                $quizFolder = $quizParentFolder .'/'. $folder;

                if (is_dir($quizFolder)) {
                    if (file_exists($quizFolder .'/quizzes.json')) {
                        $quizzesJson = file_get_contents($quizFolder .'/quizzes.json');
                        array_push($this->quizList, ...json_decode($quizzesJson));
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
     * Generic getter/setter function.
     */
    public function __call(string $name, array $arguments)
    {
        $op = count($arguments) === 1 ? 'set' : 'get';

        // We don't want these to be accessible.
        $privateProperties = [
            'quizController',
            'roundEndTimer',
            'quizList',
        ];
        $readOnlyProperties = [
            'currentQuestion',
            'currentQuestionNumber',
        ];

        if (property_exists($this, $name) && !in_array($name, $privateProperties)) {
            if ($op === 'set') {
                if (in_array($name, $readOnlyProperties)) {
                    trigger_error("Unable to $op '$name' property of ".self::class, E_USER_ERROR);
                }
                $this->{$name} = $arguments[0];
                return $this;
            }

            return $this->{$name};
        }

        // Run the method as normal.
        return $this->{$name}(...$arguments);
    }
}
