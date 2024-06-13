<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use rbwebdesigns\quizzerino\Logger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Quizzerino game server
 * 
 * This is the main entrypoint to starting the PHP Ratchet web server
 * before running this script the configuration file must have been
 * created and configured with the local environment details.
 *
 * @author R Bertram <ricky@rbwebdesigns.co.uk>
 */

$version = '2024-06-13';

require __DIR__ . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    Logger::error("Server must be run from command line");
    exit;
}

$container = new ContainerBuilder();
$loader = new YamlFileLoader($container, new FileLocator(__DIR__));
$loader->load('services.yml');

print "***********************************\n\n";
print "   Welcome to Quizzerino!          \n\n";
print "   Build {$version}                \n\n";
print "***********************************\n\n";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$_ENV['APP_ROOT'] = __DIR__;

$messenger = $container->get('messenger');
$messenger->setContainer($container);

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
        new WsServer($messenger)
    ),
    $_ENV['SERVER_PORT'] ?? 8080
);

$game = $container->get('game');
$game->server($server);

Logger::info("Game server ready, awaiting new connections...");

$server->run();
