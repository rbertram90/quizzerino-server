# Quizzerino server

## What is it?
Quiz server built using PHP + WebSockets

## What does it do?
Allow multiplayer quizzes to take place in the browser - currently only supports multiple choice questions.

Developers can add there own question set repositories through composer (or manually). Documentation yet to be added!

## What is the project status
Early development, started May 2020, missing many features & documentation.

## Server installation
Clone this repository

Copy config_default.json to config.json and configure variables

Change directory into project root

Add your question source(s) - see below

Run `composer update`

## Starting server
Simply run `php start-server.php [port-number]` from the project root directory, the port number argument is optional
and defaults to 8080.

Remember to setup port forwarding from your router.

## Question source
This server repository does not come with any questions, these can be added in via. composer.

Currently the only source of questions can be found at https://github.com/rbertram90/quizzerino-open-trivia-source
this adds in all quiz categories from the Open Trivia DB.

```
...
"repositories": {
  ...
  {
    "type": "vcs",
    "url": "https://github.com/rbertram90/quizzerino-open-trivia-source.git"
  }
}
"require": {
  ...
  "rbwebdesigns/quizzerino-open-trivia-db-source": "dev-master"
}
```

## Connecting through browser
The front end is also open source see: https://github.com/rbertram90/quizzerino-client

The easiest option is to use the hosted client at: http://quizzerino.rbwebdesigns.co.uk/.

You will not be able to connect to a server running locally if using https connection to the website,
unless using WSS (secure web socket).
