<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use rbwebdesigns\quizzerino\Game;
use rbwebdesigns\quizzerino\Logger;

/**
 * Quizzerino game server
 * 
 * This is the main entrypoint to starting the PHP Ratchet web server
 * before running this script the configuration file must have been
 * created and configured with the local environment details.
 *
 * @author R Bertram <ricky@rbwebdesigns.co.uk>
 */

$version = '2020-05-11';

require __DIR__ . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    Logger::error("Server must be run from command line");
    exit;
}

if (!file_exists(__DIR__ . '/config.json')) {
    Logger::error("Please copy config_default.json to config.json and check server variables");
    exit;
}

print "***********************************\n\n";
print "   Welcome to Quizzerino!          \n\n";
print "   Build {$version}                \n\n";
print "***********************************\n\n";

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

Logger::info("Loaded configuration:");

foreach ($config as $key => $value) {
    define(strtoupper($key), $value);
    Logger::info(strtoupper($key) .' = '. $value);
}

define('ROOT_DIR', __DIR__);

/**
 * Create the server class, each of the layers provide part of the request
 * process, the following has been extracted from the documentation for quick
 * reference:
 * 
 * 1) IoServer
 *    The IoServer should be the base of your application. This is the
 *    core of the events driven from client actions. It handles receiving
 *    new connections, reading/writing to those connections, closing the
 *    connections, and handles all errors from your application.
 * 
 *    @see http://socketo.me/docs/server
 *    
 * 2) HttpServer
 *    This component is responsible for parsing incoming HTTP requests.
 *    It's purpose is to buffer data until it receives a full HTTP header
 *    request and pass it on. You can use this as a raw HTTP server (not
 *    advised) but it's meant for upgrading WebSocket requests.
 * 
 *    @see http://socketo.me/docs/http
 * 
 * 3) WsServer
 *    This component allows your server to communicate with web browsers
 *    that use the W3C WebSocket API
 * 
 *    @see http://socketo.me/docs/websocket
 * 
 * Finally we end up at the class that is implemented in this project; class Game.
 * It implements the MessageComponentInterface so is required to provide an implementation
 * for onOpen, onClose, onMessage and onError methods.
 */
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Game()
        )
    ),
    SERVER_PORT
);

Logger::info("Game server ready, awaiting new connections...");

$server->run();